<?php

namespace App\Services\Payouts;

use App\Models\CyclePayoutApproval;
use App\Models\CyclePayoutDraft;
use App\Models\Society;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateCyclePayoutDraft
{
    public function __construct(
        private readonly CalculateCyclePayout $calculator,
        private readonly AuthorizePayoutAction $authorizer
    ) {}

    public function execute(
        Society $society,
        User $actor,
        int $cycleId,
        string $requestKey,
        ?string $asOfDate = null
    ): CyclePayoutDraft {
        if (! Str::isUuid($requestKey)) {
            throw ValidationException::withMessages([
                'request_key' => 'Invalid payout request key.',
            ]);
        }

        $this->authorizer->check(
            $society,
            $actor,
            'payouts.prepare'
        );

        return DB::transaction(function () use (
            $society,
            $actor,
            $cycleId,
            $requestKey,
            $asOfDate
        ) {
            $lockedSociety = Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $lockedSociety->status === 'active',
                422
            );

            $cycle = $society->cycles()
                ->whereKey($cycleId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = CyclePayoutDraft::query()
                ->where('society_id', $society->id)
                ->where('request_key', $requestKey)
                ->first();

            if ($existing) {
                if (
                    (int) $existing->cycle_id !== (int) $cycle->id
                    || (int) $existing->created_by !== (int) $actor->id
                    || (
                        $asOfDate !== null
                        && $existing->as_of_date->toDateString()
                            !== $asOfDate
                    )
                ) {
                    throw ValidationException::withMessages([
                        'request_key' =>
                            'This request key belongs to another draft.',
                    ]);
                }

                return $existing->load('items');
            }

            if (
                CyclePayoutApproval::query()
                    ->where('society_id', $society->id)
                    ->where('cycle_id', $cycle->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'cycle' =>
                        'This cycle already has an approved payout. ' .
                        'New drafts cannot be prepared.',
                ]);
            }

            if ($cycle->status !== 'active') {
                throw ValidationException::withMessages([
                    'cycle' =>
                        'Only active cycles awaiting closure ' .
                        'can receive new payout drafts.',
                ]);
            }

            $result = $this->calculator->build(
                $society,
                $cycle->id,
                $asOfDate
            );

            $revision = (int) CyclePayoutDraft::query()
                ->where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->max('revision') + 1;

            $draft = CyclePayoutDraft::create([
                'society_id' => $society->id,
                'cycle_id' => $cycle->id,
                'revision' => $revision,
                'as_of_date' => $result['as_of_date'],
                'status' => 'draft',
                'distribution_method' =>
                    $result['distribution_method'],

                'savings_total_cents' =>
                    $result['totals']['savings_cents'],

                'interest_total_cents' =>
                    $result['totals']['interest_refund_cents'],

                'penalty_pool_cents' =>
                    $result['penalty_pool_cents'],

                'penalty_distributed_cents' =>
                    $result['penalty_distributed_cents'],

                'penalty_reserved_cents' =>
                    $result['penalty_reserved_cents'],

                'gross_total_cents' =>
                    $result['totals']['gross_payout_cents'],

                'liquid_assets_cents' =>
                    $result['liquid_assets_cents'],

                'blockers' => $result['blockers'],
                'source_hash' => $result['source_hash'],
                'request_key' => $requestKey,
                'created_by' => $actor->id,
            ]);

            foreach ($result['rows'] as $row) {
                $draft->items()->create([
                    'society_id' => $society->id,
                    'society_membership_id' =>
                        $row['membership_id'],
                    'member_name' => $row['member_name'],
                    'member_number' => $row['member_number'],
                    'membership_status' =>
                        $row['membership_status'],
                    'savings_cents' => $row['savings_cents'],
                    'interest_refund_cents' =>
                        $row['interest_refund_cents'],
                    'penalty_share_cents' =>
                        $row['penalty_share_cents'],
                    'gross_payout_cents' =>
                        $row['gross_payout_cents'],
                ]);
            }

            return $draft->load('items');
        }, 3);
    }
}
