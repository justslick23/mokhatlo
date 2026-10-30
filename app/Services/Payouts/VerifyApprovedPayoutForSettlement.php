<?php

namespace App\Services\Payouts;

use App\Models\CyclePayoutApproval;
use App\Models\CyclePayoutDraft;
use App\Models\Society;
use App\Models\LedgerTransaction;
use Illuminate\Validation\ValidationException;

final class VerifyApprovedPayoutForSettlement
{
    public function __construct(
        private readonly CalculateCyclePayout $calculator
    ) {}

    public function execute(
        Society $society,
        CyclePayoutDraft $draft,
        CyclePayoutApproval $approval
    ): void {
        if (
            (int) $draft->society_id !== (int) $society->id
            || (int) $approval->cycle_payout_draft_id
                !== (int) $draft->id
            || $draft->status !== 'approved'
            || ! hash_equals(
                (string) $draft->source_hash,
                (string) $approval->approved_source_hash
            )
        ) {
            throw ValidationException::withMessages([
                'payout' => 'The approved payout is inconsistent.',
            ]);
        }

        if (
            LedgerTransaction::query()
                ->where('society_id', $society->id)
                ->where('cycle_id', $draft->cycle_id)
                ->where('created_at', '>', $approval->approved_at)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'payout' =>
                    'Financial postings occurred after approval. ' .
                    'The changes require reconciliation before settlement.',
            ]);
        }

        /*
         * Calculate the current position, rather than
         * using the old cutoff date. The previously
         * approved financial amounts must still agree.
         */
        $fresh = $this->calculator->build(
            $society,
            $draft->cycle_id
        );

        if (count($fresh['blockers']) > 0) {
            throw ValidationException::withMessages([
                'payout' =>
                    'The savings cycle has unresolved financial blockers.',
            ]);
        }

        $expected = [
            'savings_total_cents' =>
                $fresh['totals']['savings_cents'],
            'interest_total_cents' =>
                $fresh['totals']['interest_refund_cents'],
            'penalty_pool_cents' =>
                $fresh['penalty_pool_cents'],
            'penalty_distributed_cents' =>
                $fresh['penalty_distributed_cents'],
            'penalty_reserved_cents' =>
                $fresh['penalty_reserved_cents'],
            'gross_total_cents' =>
                $fresh['totals']['gross_payout_cents'],
            'liquid_assets_cents' =>
                $fresh['liquid_assets_cents'],
        ];

        foreach ($expected as $column => $value) {
            if ((int) $draft->{$column} !== (int) $value) {
                throw ValidationException::withMessages([
                    'payout' =>
                        "The approved {$column} has changed. " .
                        'Settlement cannot begin.',
                ]);
            }
        }

        $draft->load('items');

        $rows = collect($fresh['rows'])
            ->keyBy('membership_id');

        if ($draft->items->count() !== $rows->count()) {
            throw ValidationException::withMessages([
                'payout' => 'The member payout roster has changed.',
            ]);
        }

        foreach ($draft->items as $item) {
            $row = $rows->get(
                (int) $item->society_membership_id
            );

            if (! $row) {
                throw ValidationException::withMessages([
                    'payout' => 'An approved payout member is missing.',
                ]);
            }

            foreach ([
                'savings_cents',
                'interest_refund_cents',
                'penalty_share_cents',
                'gross_payout_cents',
            ] as $field) {
                if ((int) $item->{$field} !== (int) $row[$field]) {
                    throw ValidationException::withMessages([
                        'payout' =>
                            'An approved member payout amount has changed.',
                    ]);
                }
            }

            if (
                $item->member_name !== $row['member_name']
                || $item->member_number !== $row['member_number']
                || $item->membership_status !== $row['membership_status']
            ) {
                throw ValidationException::withMessages([
                    'payout' =>
                        'Member identity or eligibility has changed.',
                ]);
            }
        }
    }
}
