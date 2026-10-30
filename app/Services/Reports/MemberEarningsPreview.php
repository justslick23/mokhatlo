<?php

namespace App\Services\Reports;

use App\Models\Society;
use App\Services\Legacy\LegacyReconciliationStatus;
use App\Services\Payouts\CalculateCyclePayout;

final class MemberEarningsPreview
{
    public function __construct(
        private readonly CalculateCyclePayout $calculator,
        private readonly LegacyReconciliationStatus $legacy
    ) {}

    public function build(Society $society, int $cycleId): array
    {
        $preview = $this->calculator->build($society, $cycleId);
        $status = $this->legacy->forSociety($society);
        $legacyBlockers = ($status['imported'] && (int) $status['cycle_id'] === $cycleId)
            ? $status['blockers'] : [];
        $members = array_map(fn (array $row) => [
            'membership_id' => $row['membership_id'],
            'interest_cents' => $row['interest_refund_cents'],
            'penalty_share_cents' => $row['penalty_share_cents'],
            'total_cents' => $row['interest_refund_cents'] + $row['penalty_share_cents'],
        ], $preview['rows']);

        return [
            'members' => $members,
            'interest_collected_cents' => $preview['totals']['interest_refund_cents'],
            'penalty_pool_cents' => $preview['penalty_pool_cents'],
            'distribution_method' => $preview['distribution_method'],
            'eligible_member_count' => count($members),
            'blockers' => array_values(array_unique(array_merge($preview['blockers'], $legacyBlockers))),
            'provisional' => true,
        ];
    }
}
