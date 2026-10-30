<?php

namespace App\Services\Loans;

use App\Models\LoanPenalty;
use App\Models\LoanPenaltyPayment;
use App\Models\LoanPenaltyWaiver;
use App\Models\Society;
use App\Models\User;
use App\Services\Accounting\PostLoanPenaltyPaymentToLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageLoanPenalty
{
    public function __construct(
        private readonly PostLoanPenaltyPaymentToLedger $ledger
    ) {
    }

    public function pay(
        Society $society,
        User $actor,
        int $penaltyId,
        array $data
    ): LoanPenaltyPayment {
        return DB::transaction(function () use (
            $society, $actor, $penaltyId, $data
        ) {
            $this->lockSocietyAndCheckActor($society, $actor);

            $penalty = LoanPenalty::query()
                ->where('society_id', $society->id)
                ->whereKey($penaltyId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = LoanPenaltyPayment::query()
                ->where('society_id', $society->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                $same =
                    $existing->loan_penalty_id === $penalty->id
                    && $existing->recorded_by === $actor->id
                    && Money::toCents($existing->amount)
                        === Money::toCents($data['amount'])
                    && $existing->paid_on->toDateString()
                        === $data['paid_on']
                    && $existing->payment_method
                        === $data['payment_method']
                    && $existing->payment_reference
                        === ($data['payment_reference'] ?? null);

                if (! $same) {
                    throw ValidationException::withMessages([
                        'payment' => 'This payment request key is already used.',
                    ]);
                }

                return $existing;
            }

            $penalty->load(['payments', 'waivers']);

            $amountCents = Money::toCents($data['amount']);

            if (
                $amountCents <= 0
                || $amountCents > $penalty->outstandingCents()
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'Amount must be positive and within the outstanding penalty.',
                ]);
            }

            if (
                $data['paid_on']
                < $penalty->assessed_on->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'paid_on' =>
                        'Payment date cannot precede assessment.',
                ]);
            }

            $payment = LoanPenaltyPayment::create([
                'society_id' => $society->id,
                'loan_penalty_id' => $penalty->id,
                'amount' => Money::fromCents($amountCents),
                'paid_on' => $data['paid_on'],
                'payment_method' => $data['payment_method'],
                'payment_reference' =>
                    $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'recorded_by' => $actor->id,
            ]);

            $this->ledger->execute($payment);

            return $payment;
        }, 3);
    }

    public function waive(
        Society $society,
        User $actor,
        int $penaltyId,
        array $data
    ): LoanPenaltyWaiver {
        return DB::transaction(function () use (
            $society, $actor, $penaltyId, $data
        ) {
            $this->lockSocietyAndCheckActor($society, $actor);

            $penalty = LoanPenalty::query()
                ->where('society_id', $society->id)
                ->whereKey($penaltyId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = LoanPenaltyWaiver::query()
                ->where('society_id', $society->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                $same =
                    $existing->loan_penalty_id === $penalty->id
                    && $existing->waived_by === $actor->id
                    && Money::toCents($existing->amount)
                        === Money::toCents($data['amount'])
                    && $existing->reason === trim($data['reason']);

                if (! $same) {
                    throw ValidationException::withMessages([
                        'waiver' => 'This waiver request key is already used.',
                    ]);
                }

                return $existing;
            }

            $penalty->load(['payments', 'waivers']);

            $amountCents = Money::toCents($data['amount']);

            if (
                $amountCents <= 0
                || $amountCents > $penalty->outstandingCents()
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'Waiver must be positive and within the outstanding penalty.',
                ]);
            }

            return LoanPenaltyWaiver::create([
                'society_id' => $society->id,
                'loan_penalty_id' => $penalty->id,
                'amount' => Money::fromCents($amountCents),
                'reason' => trim($data['reason']),
                'idempotency_key' => $data['idempotency_key'],
                'waived_by' => $actor->id,
            ]);
        }, 3);
    }

    private function lockSocietyAndCheckActor(
        Society $society,
        User $actor
    ): void {
        $locked = Society::query()
            ->whereKey($society->id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless($locked->status === 'active', 422);

        abort_unless(
            $society->memberships()
                ->where('user_id', $actor->id)
                ->where('status', 'active')
                ->exists(),
            403
        );
    }
}
