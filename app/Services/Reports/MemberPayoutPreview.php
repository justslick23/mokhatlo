<?php

namespace App\Services\Reports;

use App\Models\Society;
use App\Services\Legacy\LegacyReconciliationStatus;
use App\Services\Payouts\CalculateCyclePayout;

final class MemberPayoutPreview
{
    public function __construct(
        private readonly CalculateCyclePayout $calculator,
        private readonly LegacyReconciliationStatus $legacy
    ) {}

    public function build(Society $society, int $cycleId): array
    {
        $preview = $this->calculator->build($society, $cycleId);
        $status = $this->legacy->forSociety($society);
        if ($status['imported'] && (int) $status['cycle_id'] === $cycleId) {
            $preview['blockers'] = array_values(array_unique(array_merge(
                $preview['blockers'], $status['blockers']
            )));
        }
        return $preview;
    }
}
