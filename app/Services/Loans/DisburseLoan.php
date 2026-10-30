<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\Society;
use App\Models\User;
use App\Services\Accounting\PostLoanToLedger;
use App\Services\Accounting\SocietyAssetAccounts;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DisburseLoan
{
    public function __construct(
        private readonly SocietyAssetAccounts $assets,
        private readonly PostLoanToLedger $ledger,
        private readonly GenerateLoanSchedule $schedule
    ) {}

    public function execute(
        Society $society,
        User $actor,
        int $loanId,
        array $data
    ): LoanDisbursement {
        return DB::transaction(function () use (
            $society,
            $actor,
            $loanId,
            $data
        ) {
            $lockedSociety = Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSociety->status !== 'active') {
                throw ValidationException::withMessages([
                    'society' => 'Society is not active.',
                ]);
            }

            $actorIsMember = $society->memberships()
                ->where('user_id', $actor->id)
                ->where('status', 'active')
                ->exists();

            abort_unless($actorIsMember, 403);

            $loan = Loan::query()
                ->where('society_id', $society->id)
                ->whereKey($loanId)
                ->lockForUpdate()
                ->firstOrFail();

            $loan->load([
                'society',
                'cycle',
                'membership',
                'disbursement',
            ]);

            /*
             * Retry-safe operation.
             */
            $existing = LoanDisbursement::query()
                ->where('society_id', $society->id)
                ->where(
                    'idempotency_key',
                    $data['idempotency_key']
                )
                ->first();

            if ($existing) {
                $sameRequest =
                    (int) $existing->loan_id === (int) $loan->id

                    && (int) $existing->recorded_by
                        === (int) $actor->id

                    && $existing->disbursed_on->toDateString()
                        === $data['disbursed_on']

                    && $existing->payment_method
                        === $data['payment_method']

                    && $existing->payment_reference
                        === ($data['payment_reference'] ?? null)

                    && $existing->notes
                        === ($data['notes'] ?? null);

                if (! $sameRequest) {
                    throw ValidationException::withMessages([
                        'disbursement' =>
                            'This disbursement request key is already used.',
                    ]);
                }

                return $existing;
            }

            if ($loan->status !== 'approved') {
                throw ValidationException::withMessages([
                    'loan' =>
                        'This loan is not awaiting disbursement.',
                ]);
            }

            if ($loan->cycle->status !== 'active') {
                throw ValidationException::withMessages([
                    'cycle' =>
                        'The loan cycle must be active to disburse.',
                ]);
            }

            if ($loan->membership->status !== 'active') {
                throw ValidationException::withMessages([
                    'member' => 'Borrower is not an active member.',
                ]);
            }

            /*
             * Finance control:
             * A user cannot record disbursement of their
             * own loan, even if they are an Owner/Treasurer.
             */
            if (
                (int) $loan->membership->user_id
                === (int) $actor->id
            ) {
                throw ValidationException::withMessages([
                    'loan' =>
                        'You cannot record disbursement of your own loan.',
                ]);
            }

            $disbursementDate = CarbonImmutable::parse(
                $data['disbursed_on']
            )->toDateString();

            $cycleStart = CarbonImmutable::parse(
                $loan->cycle->start_date
            )->toDateString();

            if ($disbursementDate < $cycleStart) {
                throw ValidationException::withMessages([
                    'disbursed_on' =>
                        'Disbursement date cannot precede the cycle start.',
                ]);
            }

            if (
                $disbursementDate
                > now('Africa/Maseru')->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'disbursed_on' =>
                        'Disbursement cannot be dated in the future.',
                ]);
            }

            if ($loan->disbursement) {
                throw ValidationException::withMessages([
                    'loan' => 'This loan has already been disbursed.',
                ]);
            }

            $cashAccount = $this->assets->forMethod(
                $society,
                $data['payment_method']
            );

            $amountCents = Money::toCents(
                $loan->principal_amount
            );

            $availableCents = $this->assets->balanceCents(
                $cashAccount
            );

            if ($availableCents < $amountCents) {
                throw ValidationException::withMessages([
                    'payment_method' =>
                        'Insufficient recorded funds. Available: M' .
                        Money::fromCents(max(0, $availableCents)),
                ]);
            }

            $disbursement = LoanDisbursement::create([
                'society_id' => $society->id,
                'loan_id' => $loan->id,
                'amount' => $loan->principal_amount,
                'disbursed_on' => $disbursementDate,
                'payment_method' => $data['payment_method'],
                'payment_reference' =>
                    $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'recorded_by' => $actor->id,
            ]);

            $this->schedule->execute(
                $loan,
                $disbursementDate
            );

            $this->ledger->disbursement(
                $disbursement->load('loan.society')
            );

            $loan->update([
                'status' => 'disbursed',
                'disbursed_at' => now(),
            ]);

            $loan->events()->create([
                'society_id' => $society->id,
                'actor_id' => $actor->id,
                'event_type' => 'disbursed',
                'from_status' => 'approved',
                'to_status' => 'disbursed',
                'notes' =>
                    'Disbursement recorded using ' .
                    str_replace('_', ' ', $data['payment_method']),
            ]);

            return $disbursement;
        }, 3);
    }
}
