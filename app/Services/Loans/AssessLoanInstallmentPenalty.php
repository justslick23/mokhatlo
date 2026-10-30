<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPenalty;
use App\Models\Society;
use App\Support\Money;
use App\Support\Percentage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssessLoanInstallmentPenalty
{
    public function execute(int $installmentId): ?LoanPenalty
    {
        return DB::transaction(function () use ($installmentId) {
            $locator = LoanInstallment::query()
                ->select(['id', 'loan_id', 'society_id'])
                ->findOrFail($installmentId);

            // Keep the same lock order as the repayment service.
            $society = Society::query()
                ->whereKey($locator->society_id)
                ->lockForUpdate()
                ->firstOrFail();

            $loan = Loan::query()
                ->where('society_id', $society->id)
                ->whereKey($locator->loan_id)
                ->lockForUpdate()
                ->firstOrFail();

            $installment = LoanInstallment::query()
                ->where('society_id', $society->id)
                ->where('loan_id', $loan->id)
                ->whereKey($installmentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($installment->penalty()->exists()) {
                return $installment->penalty()->first();
            }

            if (
                $society->status !== 'active'
                || ! in_array(
                    $loan->status,
                    ['disbursed', 'settled'],
                    true
                )
                || ! $loan->disbursement()->exists()
            ) {
                return null;
            }

            $dueDate = $installment->due_date->toDateString();

            $today = CarbonImmutable::today(
                $society->timezone ?: 'Africa/Maseru'
            );

            if ($today->toDateString() <= $dueDate) {
                return null;
            }

            /*
             * Prefer the immutable rate stored on the loan.
             * Older records may need a verified policy backfill.
             */
            $rate = $loan->loan_penalty_rate_percent;

          
                if ($rate === null) {
                    throw ValidationException::withMessages([
                        'loan' =>
                            'Loan penalty rate snapshot is missing. ' .
                            'Verify the original loan policy before assessing penalties.',
                    ]);
                }


            $rate = (string) $rate;

            if (preg_match('/^0+(?:\.0+)?$/', $rate)) {
                return null;
            }

            /*
             * Read each allocation with its payment date.
             * A repayment received after the due date
             * must not reduce the historical penalty base.
             */
            $allocations = DB::table('loan_repayment_allocations as a')
                ->join(
                    'loan_repayments as r',
                    'r.id',
                    '=',
                    'a.loan_repayment_id'
                )
                ->where('a.loan_installment_id', $installment->id)
                ->where('a.loan_id', $loan->id)
                ->where('r.society_id', $society->id)
                ->whereDate('r.paid_on', '<=', $dueDate)
                ->select([
                    'a.principal_amount',
                    'a.interest_amount',
                ])
                ->get();

            $paidCents = $allocations->sum(
                fn ($row) =>
                    Money::toCents($row->principal_amount)
                    + Money::toCents($row->interest_amount)
            );

            $dueCents = Money::toCents($installment->total_due);

            if ($paidCents > $dueCents) {
                throw ValidationException::withMessages([
                    'loan' => 'Installment allocations are inconsistent.',
                ]);
            }

            $baseCents = $dueCents - $paidCents;

            if ($baseCents <= 0) {
                return null;
            }

            $penaltyCents = Percentage::applyToCents(
                $baseCents,
                $rate
            );

            if ($penaltyCents <= 0) {
                return null;
            }

            return LoanPenalty::create([
                'society_id' => $society->id,
                'loan_id' => $loan->id,
                'loan_installment_id' => $installment->id,
                'base_amount' => Money::fromCents($baseCents),
                'rate_percent' => $rate,
                'assessed_amount' => Money::fromCents($penaltyCents),
                'cutoff_date' => $dueDate,
                'assessed_on' => $today->toDateString(),
                'calculation_method' =>
                    'one_time_unpaid_installment',
            ]);
        }, 3);
    }
}
