<?php

namespace App\Services\Payouts;

use App\Models\CyclePayoutDraft;
use Illuminate\Validation\ValidationException;

final class VerifyPayoutDraftSnapshot
{
    public function execute(
        CyclePayoutDraft $draft,
        array $fresh,
        bool $requireReady = false
    ): void {
        $today = now('Africa/Maseru')->toDateString();

        if ($draft->as_of_date->toDateString() !== $today) {
            throw ValidationException::withMessages([
                'draft' =>
                    'This payout draft is from an earlier day. ' .
                    'Prepare a new draft using today’s balances.',
            ]);
        }

        if (
            (int) $fresh['society_id'] !== (int) $draft->society_id
            || (int) $fresh['cycle_id'] !== (int) $draft->cycle_id
        ) {
            throw ValidationException::withMessages([
                'draft' => 'The payout context has changed.',
            ]);
        }

        if (! hash_equals(
            (string) $draft->source_hash,
            (string) $fresh['source_hash']
        )) {
            throw ValidationException::withMessages([
                'draft' =>
                    'Financial data has changed since this draft was saved. ' .
                    'Prepare and reconcile a new revision.',
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

        foreach ($expected as $column => $amount) {
            if ((int) $draft->{$column} !== (int) $amount) {
                throw ValidationException::withMessages([
                    'draft' =>
                        'Saved payout totals do not reconcile.',
                ]);
            }
        }

        $items = $draft->items;

        $expectedRows = collect($fresh['rows'])
            ->keyBy('membership_id');

        if ($items->count() !== $expectedRows->count()) {
            throw ValidationException::withMessages([
                'draft' => 'The saved member roster has changed.',
            ]);
        }

        foreach ($items as $item) {
            $row = $expectedRows->get(
                (int) $item->society_membership_id
            );

            if (! $row) {
                throw ValidationException::withMessages([
                    'draft' => 'A saved payout member is missing.',
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
                        'draft' =>
                            'A saved member payout amount has changed.',
                    ]);
                }
            }

            if (
                $item->member_name !== $row['member_name']
                || $item->member_number !== $row['member_number']
                || $item->membership_status !== $row['membership_status']
            ) {
                throw ValidationException::withMessages([
                    'draft' =>
                        'A saved member identity or status has changed.',
                ]);
            }
        }

        if ($requireReady) {
            if (
                count($draft->blockers ?? []) > 0
                || count($fresh['blockers']) > 0
            ) {
                throw ValidationException::withMessages([
                    'approval' =>
                        'All payout reconciliation blockers must ' .
                        'be resolved before approval.',
                ]);
            }

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'approval' =>
                        'Cannot approve an empty payout draft.',
                ]);
            }
        }
    }
}
