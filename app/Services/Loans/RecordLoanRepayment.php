<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Society;
use App\Models\User;
use App\Services\Accounting\PostLoanToLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordLoanRepayment
{
    public function __construct(
        private readonly PostLoanToLedger $ledger,
        private readonly LoanBalances $balances
    ) {
    }

    public function execute(
        Society $society,
        User $actor,
        int $loanId,
        array $data
    ): LoanRepayment {
        return DB::transaction(function () use (
            $society, $actor, $loanId, $data
        ) {
            $lockedSociety = Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $lockedSociety->status === 'active',
                422
            );

            abort_unless(
                $society->memberships()
                    ->where('user_id', $actor->id)
                    ->where('status', 'active')
                    ->exists(),
                403
            );

            $loan = Loan::query()
                ->where('society_id', $society->id)
                ->whereKey($loanId)
                ->lockForUpdate()
                ->firstOrFail();

            $loan->load(['society', 'disbursement']);

            $existing = LoanRepayment::query()
                ->where('society_id', $society->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                $same = $existing->loan_id === $loan->id
                    && $existing->recorded_by === $actor->id
                    && Money::toCents($existing->amount)
                        === Money::toCents($data['amount'])
                    && $existing->paid_on->toDateString()
                        === $data['paid_on']
                    && $existing->payment_method
                        === $data['payment_method']
                    && $existing->payment_reference
                        === ($data['payment_reference'] ?? null)
                    && $existing->notes
                        === ($data['notes'] ?? null);

                if (! $same) {
                    throw ValidationException::withMessages([
                        'payment' => 'This repayment request key has already been used.',
                    ]);
                }

                return $existing;
            }

            if (
                $loan->status !== 'disbursed'
                || ! $loan->disbursement
            ) {
                throw ValidationException::withMessages([
                    'loan' => 'The loan is not available for repayment.',
                ]);
            }

            if (
                $data['paid_on']
                < $loan->disbursement->disbursed_on->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'paid_on' => 'Repayment cannot predate disbursement.',
                ]);
            }

            // Maintain chronological payment allocation.
            $latestDate = LoanRepayment::query()
    ->where('society_id', $society->id)
    ->where('loan_id', $loan->id)
    ->max('paid_on');

            if (
                $latestDate
                && $data['paid_on'] < substr((string) $latestDate, 0, 10)
            ) {
                throw ValidationException::withMessages([
                    'paid_on' =>
                        'Payment date cannot precede a previously recorded repayment.',
                ]);
            }

            $amountCents = Money::toCents($data['amount']);
            $balance = $this->balances->summary($loan);

            
if (
    $balance['principal_due']
        !== Money::toCents($loan->principal_amount)
    || $balance['interest_due']
        !== Money::toCents($loan->quoted_interest_amount)
) {
    throw ValidationException::withMessages([
        'loan' =>
            'The repayment schedule does not reconcile with the loan contract.',
    ]);
}


            if (
                $amountCents <= 0
                || $amountCents > $balance['total_outstanding']
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'Amount must be positive and cannot exceed the loan balance.',
                ]);
            }

            $repayment = LoanRepayment::create([
                'society_id' => $society->id,
                'loan_id' => $loan->id,
                'amount' => Money::fromCents($amountCents),
                'paid_on' => $data['paid_on'],
                'payment_method' => $data['payment_method'],
                'payment_reference' =>
                    $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'recorded_by' => $actor->id,
            ]);

            $remaining = $amountCents;
            $principalAllocated = 0;
            $interestAllocated = 0;

            $installments = $loan->installments()
                ->with('allocations')
                ->orderBy('installment_number')
                ->get();

            foreach ($installments as $installment) {
                if ($remaining === 0) {
                    break;
                }

                $principalDue = Money::toCents(
                    $installment->principal_due
                );

                $interestDue = Money::toCents(
                    $installment->interest_due
                );

                $previousPrincipal = $installment->allocations->sum(
                    fn ($row) => Money::toCents(
                        $row->principal_amount
                    )
                );

                $previousInterest = $installment->allocations->sum(
                    fn ($row) => Money::toCents(
                        $row->interest_amount
                    )
                );

                if (
                    $previousPrincipal > $principalDue
                    || $previousInterest > $interestDue
                ) {
                    throw ValidationException::withMessages([
                        'loan' => 'Repayment schedule is inconsistent.',
                    ]);
                }

                $interestPay = min(
                    $remaining,
                    $interestDue - $previousInterest
                );

                $remaining -= $interestPay;

                $principalPay = min(
                    $remaining,
                    $principalDue - $previousPrincipal
                );

                $remaining -= $principalPay;

                if ($interestPay + $principalPay === 0) {
                    continue;
                }

                $repayment->allocations()->create([
                    'loan_id' => $loan->id,
                    'loan_installment_id' => $installment->id,
                    'principal_amount' => Money::fromCents(
                        $principalPay
                    ),
                    'interest_amount' => Money::fromCents(
                        $interestPay
                    ),
                ]);

                $principalAllocated += $principalPay;
                $interestAllocated += $interestPay;
            }

            if ($remaining !== 0) {
                throw ValidationException::withMessages([
                    'loan' => 'Repayment could not be fully allocated.',
                ]);
            }

            $this->ledger->repayment(
                $repayment->load('loan.society'),
                $principalAllocated,
                $interestAllocated
            );

            $newBalance = $this->balances->summary($loan);

            if ($newBalance['total_outstanding'] === 0) {
                $loan->update([
                    'status' => 'settled',
                    'settled_at' => now(),
                ]);

                $loan->events()->create([
                    'society_id' => $society->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'settled',
                    'from_status' => 'disbursed',
                    'to_status' => 'settled',
                    'notes' => 'Loan fully repaid.',
                ]);
            } else {
                $loan->events()->create([
                    'society_id' => $society->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'repayment_recorded',
                    'from_status' => 'disbursed',
                    'to_status' => 'disbursed',
                    'notes' =>
                        'Repayment received: M' .
                        Money::fromCents($amountCents),
                ]);
            }

            return $repayment;
        }, 3);
    }
}
