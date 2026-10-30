<?php

namespace App\Services\Penalties;

use App\Models\ContributionObligation;
use App\Models\ContributionPenalty;
use App\Models\Society;
use App\Support\Money;
use App\Support\Percentage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AssessContributionPenalty
{
    public function execute(
        int $obligationId
    ): ?ContributionPenalty {
        return DB::transaction(function () use ($obligationId) {

            $initial = ContributionObligation::query()
                ->findOrFail($obligationId);

            /*
            | Lock society first, matching the lock order
            | used by the contribution recording service.
            */
            $society = Society::query()
                ->whereKey($initial->society_id)
                ->lockForUpdate()
                ->firstOrFail();

            $obligation = ContributionObligation::query()
                ->where('society_id', $society->id)
                ->whereKey($obligationId)
                ->lockForUpdate()
                ->firstOrFail();

            $obligation->load('cycle.policy');

            $cycle = $obligation->cycle;
            $policy = $cycle->policy;

            if (
                $society->status !== 'active'
                || $cycle->status !== 'active'
                || ! $policy
            ) {
                return null;
            }

            // Recheck after acquiring database locks.
            $existing = $obligation->penalty()->first();

            if ($existing) {
                return $existing;
            }

            $cutoff = CarbonImmutable::parse(
                $obligation->due_date->toDateString(),
                $society->timezone
            )->addDays(
                (int) $policy->contribution_grace_days
            );

            $today = CarbonImmutable::today(
                $society->timezone
            );

            if ($today->lessThanOrEqualTo($cutoff)) {
                return null;
            }

            $rate = (string)
                $policy->contribution_penalty_percent;

            if (Percentage::applyToCents(10000, $rate) === 0) {
                return null;
            }

            /*
            | Only payments actually dated on or before
            | the grace-period cutoff reduce the penalty base.
            */
            $paymentsAtCutoff = $obligation->payments()
                ->whereDate(
                    'paid_on',
                    '<=',
                    $cutoff->toDateString()
                )
                ->get(['amount']);

            $receivedCents = $paymentsAtCutoff->sum(
                fn ($payment) => Money::toCents(
                    $payment->amount
                )
            );

            $requiredCents = Money::toCents(
                $obligation->required_amount
            );

            $outstandingCents = max(
                0,
                $requiredCents - $receivedCents
            );

            if ($outstandingCents === 0) {
                return null;
            }

            $penaltyCents = Percentage::applyToCents(
                $outstandingCents,
                $rate
            );

            if ($penaltyCents === 0) {
                return null;
            }

            return $obligation->penalty()->create([
                'society_id' => $society->id,
                'cycle_id' => $cycle->id,
                'base_amount' => Money::fromCents(
                    $outstandingCents
                ),
                'rate_percent' => $rate,
                'assessed_amount' => Money::fromCents(
                    $penaltyCents
                ),
                'cutoff_date' => $cutoff->toDateString(),
                'assessed_on' => $today->toDateString(),
                'calculation_method' =>
                    'one_time_unpaid_minimum',
            ]);
        }, 3);
    }
}
