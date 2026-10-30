<?php

namespace App\Services\Payouts;

use App\Models\CyclePayoutApproval;
use App\Models\CyclePayoutBatch;
use App\Models\Society;
use Illuminate\Validation\ValidationException;

final class GuardApprovedCycle
{
    public function assertWritable(
        Society $society,
        int $cycleId
    ): void {
        $cycle = $society->cycles()
            ->whereKey($cycleId)
            ->firstOrFail();

        if ($cycle->status !== 'active') {
            throw ValidationException::withMessages([
                'cycle' =>
                    'This savings cycle is not open for financial changes.',
            ]);
        }

        if (
            CyclePayoutApproval::query()
                ->where('society_id', $society->id)
                ->where('cycle_id', $cycleId)
                ->exists()
            || CyclePayoutBatch::query()
                ->where('society_id', $society->id)
                ->where('cycle_id', $cycleId)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'cycle' =>
                    'This savings cycle has an approved payout. ' .
                    'Financial changes are locked.',
            ]);
        }
    }
}
