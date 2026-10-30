<?php

namespace App\Services\Contributions;

use App\Models\Cycle;
use App\Models\SocietyMembership;
use Carbon\CarbonImmutable;
use DomainException;

final class GenerateContributionObligations
{
    public function forCycle(Cycle $cycle): void
    {
        $cycle->loadMissing('policy');

        if (! $cycle->policy) {
            throw new DomainException(
                'The savings cycle has no financial policy.'
            );
        }

        $memberships = SocietyMembership::query()
            ->where('society_id', $cycle->society_id)
            ->where('status', 'active')
            ->get();

        foreach ($memberships as $membership) {
            $this->forMembership($cycle, $membership);
        }
    }

    public function forMembership(
        Cycle $cycle,
        SocietyMembership $membership
    ): void {
        if (
            $cycle->society_id !== $membership->society_id
            || $membership->status !== 'active'
        ) {
            throw new DomainException(
                'Membership does not belong to this active society.'
            );
        }

        $cycle->loadMissing('policy');

        $policy = $cycle->policy;

        if (! $policy) {
            throw new DomainException(
                'Cycle financial policy is missing.'
            );
        }

        $start = CarbonImmutable::parse(
            $cycle->start_date->toDateString()
        );

        $end = CarbonImmutable::parse(
            $cycle->end_date->toDateString()
        );

        $joined = $membership->joined_at
            ? CarbonImmutable::parse(
                $membership->joined_at->toDateString()
            )
            : $start;

        $month = $start->startOfMonth();

        while ($month->lessThanOrEqualTo($end)) {
            $dueDate = $month->setDay(
                (int) $policy->contribution_due_day
            );

            // The final month's deadline cannot exceed cycle end.
            if ($dueDate->greaterThan($end)) {
                $dueDate = $end;
            }

            // A member shouldn't owe a month before joining.
            $eligibleFrom = $start->greaterThan($joined)
                ? $start
                : $joined;

            if (
                $month->lessThanOrEqualTo($end)
                && $eligibleFrom->lessThanOrEqualTo($dueDate)
            ) {
                $cycle->contributionObligations()->firstOrCreate(
                    [
                        'society_membership_id' => $membership->id,
                        'period_start' => $month->toDateString(),
                    ],
                    [
                        'society_id' => $cycle->society_id,
                        'due_date' => $dueDate->toDateString(),
                        'required_amount' =>
                            $policy->minimum_monthly_contribution,
                    ]
                );
            }

            $month = $month->addMonthNoOverflow()
                ->startOfMonth();
        }
    }
}
