<?php

namespace App\Services\Payouts;

use App\Models\CyclePayoutApproval;
use App\Models\CyclePayoutDraft;
use App\Models\Society;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApproveCyclePayoutDraft
{
    public function __construct(
        private readonly AuthorizePayoutAction $authorizer,
        private readonly CalculateCyclePayout $calculator,
        private readonly VerifyPayoutDraftSnapshot $verifier,
        private readonly CurrentSocietyCashBalances $cashBalances
    ) {}

    public function execute(
        Society $society,
        User $actor,
        int $draftId,
        array $data
    ): CyclePayoutApproval {
        $this->authorizer->check(
            $society,
            $actor,
            'payouts.approve'
        );

        
\Illuminate\Support\Facades\Validator::make($data, [
    'cash_amount' => [
        'required',
        'regex:/^\d{1,15}(\.\d{1,2})?$/',
    ],
    'bank_amount' => [
        'required',
        'regex:/^\d{1,15}(\.\d{1,2})?$/',
    ],
    'mobile_money_amount' => [
        'required',
        'regex:/^\d{1,15}(\.\d{1,2})?$/',
    ],
    'cash_count_notes' => [
        'required', 'string', 'min:10', 'max:2000',
    ],
    'bank_statement_reference' => [
        'nullable', 'string', 'max:160',
    ],
    'mobile_money_reference' => [
        'nullable', 'string', 'max:160',
    ],
    'notes' => ['nullable', 'string', 'max:2000'],
    'request_key' => ['required', 'uuid'],
    'confirmed' => ['accepted'],
])->validate();


        return DB::transaction(function () use (
            $society,
            $actor,
            $draftId,
            $data
        ) {
            $lockedSociety = Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedSociety->status === 'active', 422);

            $draft = CyclePayoutDraft::query()
                ->where('society_id', $society->id)
                ->whereKey($draftId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = CyclePayoutApproval::query()
                ->where('society_id', $society->id)
                ->where('request_key', $data['request_key'])
                ->first();

            $notes = trim($data['approval_notes']);

            if ($existing) {
                if (
                    (int) $existing->cycle_payout_draft_id
                        !== (int) $draft->id

                    || (int) $existing->approved_by
                        !== (int) $actor->id

                    || $existing->approval_notes !== $notes
                ) {
                    throw ValidationException::withMessages([
                        'request_key' =>
                            'This approval request identifier ' .
                            'has already been used.',
                    ]);
                }

                return $existing;
            }

            if ($draft->status !== 'draft') {
                throw ValidationException::withMessages([
                    'draft' => 'This draft is not awaiting approval.',
                ]);
            }

            /*
             * Require independent approval.
             */
            if ((int) $draft->created_by === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'approval' =>
                        'You cannot approve a draft you prepared.',
                ]);
            }

            $reconciliation = $draft->reconciliation()
                ->lockForUpdate()
                ->first();

            if (! $reconciliation) {
                throw ValidationException::withMessages([
                    'reconciliation' =>
                        'The payout must be reconciled first.',
                ]);
            }

            if (
                (int) $reconciliation->confirmed_by
                === (int) $actor->id
            ) {
                throw ValidationException::withMessages([
                    'approval' =>
                        'The person who reconciled the funds ' .
                        'cannot also approve this payout.',
                ]);
            }

            if (
                $reconciliation->reconciled_on->toDateString()
                !== now('Africa/Maseru')->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'reconciliation' =>
                        'The reconciliation is no longer current. ' .
                        'Prepare a new payout draft.',
                ]);
            }

            if (
                ! hash_equals(
                    (string) $draft->source_hash,
                    (string) $reconciliation->source_hash
                )
            ) {
                throw ValidationException::withMessages([
                    'reconciliation' =>
                        'The reconciliation does not match this draft.',
                ]);
            }

            /*
             * Only the latest revision can be approved.
             */
            $latestRevision = (int) CyclePayoutDraft::query()
                ->where('society_id', $society->id)
                ->where('cycle_id', $draft->cycle_id)
                ->max('revision');

            if ($latestRevision !== (int) $draft->revision) {
                throw ValidationException::withMessages([
                    'draft' =>
                        'A newer payout draft exists. ' .
                        'Review the latest revision instead.',
                ]);
            }

            /*
             * A society/cycle can have only one approved payout.
             */
            if (
                CyclePayoutApproval::query()
                    ->where('cycle_id', $draft->cycle_id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'approval' =>
                        'A payout has already been approved ' .
                        'for this savings cycle.',
                ]);
            }

            $cycle = $society->cycles()
                ->whereKey($draft->cycle_id)
                ->firstOrFail();

            if ($cycle->status !== 'active') {
                throw ValidationException::withMessages([
                    'cycle' =>
                        'Only an active cycle awaiting closure ' .
                        'may have its payout approved.',
                ]);
            }

            $fresh = $this->calculator->build(
                $society,
                $cycle->id,
                $draft->as_of_date->toDateString()
            );

            $draft->load('items');

            $this->verifier->execute(
                $draft,
                $fresh,
                requireReady: true
            );

            /*
             * Re-verify each account, not only the aggregate.
             */
            $current = $this->cashBalances->build($society);

            if (
                $current['cash_cents']
                    !== (int) $reconciliation->ledger_cash_cents
                || $current['bank_cents']
                    !== (int) $reconciliation->ledger_bank_cents
                || $current['mobile_money_cents']
                    !== (int) $reconciliation->ledger_mobile_money_cents
            ) {
                throw ValidationException::withMessages([
                    'balances' =>
                        'Account balances changed after reconciliation.',
                ]);
            }

            if (
                $reconciliation->confirmed_cash_cents
                    !== $reconciliation->ledger_cash_cents
                || $reconciliation->confirmed_bank_cents
                    !== $reconciliation->ledger_bank_cents
                || $reconciliation->confirmed_mobile_money_cents
                    !== $reconciliation->ledger_mobile_money_cents
            ) {
                throw ValidationException::withMessages([
                    'balances' =>
                        'The confirmed account balances do not match.',
                ]);
            }

            $approval = CyclePayoutApproval::create([
                'society_id' => $society->id,
                'cycle_id' => $cycle->id,
                'cycle_payout_draft_id' => $draft->id,
                'cycle_payout_reconciliation_id' =>
                    $reconciliation->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
                'approved_source_hash' => $draft->source_hash,
                'request_key' => $data['request_key'],
            ]);

            // Change workflow status, not financial snapshot.
            $draft->update([
                'status' => 'approved',
            ]);

            return $approval;
        }, 3);
    }
}
