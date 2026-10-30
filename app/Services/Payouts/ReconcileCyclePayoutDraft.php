<?php

namespace App\Services\Payouts;

use App\Models\CyclePayoutDraft;
use App\Models\CyclePayoutReconciliation;
use App\Models\Society;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReconcileCyclePayoutDraft
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
    ): CyclePayoutReconciliation {
        $this->authorizer->check(
            $society,
            $actor,
            'payouts.reconcile'
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

            /*
             * Idempotent replay of the same reconciliation.
             */
            $existing = CyclePayoutReconciliation::query()
                ->where('society_id', $society->id)
                ->where('request_key', $data['request_key'])
                ->first();

            $confirmed = [
                'confirmed_cash_cents' =>
                    Money::toCents($data['cash_amount']),

                'confirmed_bank_cents' =>
                    Money::toCents($data['bank_amount']),

                'confirmed_mobile_money_cents' =>
                    Money::toCents($data['mobile_money_amount']),
            ];

            $cashNotes = trim($data['cash_count_notes']);
            $bankReference = trim(
                $data['bank_statement_reference'] ?? ''
            );
            $mobileReference = trim(
                $data['mobile_money_reference'] ?? ''
            );
            $notes = trim($data['notes'] ?? '');

            if ($existing) {
                $same =
                    (int) $existing->cycle_payout_draft_id
                        === (int) $draft->id

                    && (int) $existing->confirmed_by
                        === (int) $actor->id

                    && (int) $existing->confirmed_cash_cents
                        === $confirmed['confirmed_cash_cents']

                    && (int) $existing->confirmed_bank_cents
                        === $confirmed['confirmed_bank_cents']

                    && (int) $existing->confirmed_mobile_money_cents
                        === $confirmed['confirmed_mobile_money_cents']

                    && $existing->cash_count_notes === $cashNotes

                    && ($existing->bank_statement_reference ?? '')
                        === $bankReference

                    && ($existing->mobile_money_reference ?? '')
                        === $mobileReference

                    && ($existing->notes ?? '') === $notes;

                if (! $same) {
                    throw ValidationException::withMessages([
                        'request_key' =>
                            'This reconciliation request key ' .
                            'was already used for different details.',
                    ]);
                }

                return $existing;
            }

            if ($draft->status !== 'draft') {
                throw ValidationException::withMessages([
                    'draft' =>
                        'Only unapproved drafts can be reconciled.',
                ]);
            }

            if ($draft->reconciliation()->exists()) {
                throw ValidationException::withMessages([
                    'draft' =>
                        'This draft is already reconciled. ' .
                        'Create a new draft if the figures changed.',
                ]);
            }

            $fresh = $this->calculator->build(
                $society,
                $draft->cycle_id,
                $draft->as_of_date->toDateString()
            );

            $draft->load('items');

            $this->verifier->execute($draft, $fresh);

            $ledger = $this->cashBalances->build($society);

            if (
                $ledger['total_cents']
                !== (int) $draft->liquid_assets_cents
            ) {
                throw ValidationException::withMessages([
                    'balances' =>
                        'Recorded cash balances have changed. ' .
                        'Prepare a new payout draft.',
                ]);
            }

            $pairs = [
                'cash_amount' => [
                    $ledger['cash_cents'],
                    $confirmed['confirmed_cash_cents'],
                ],
                'bank_amount' => [
                    $ledger['bank_cents'],
                    $confirmed['confirmed_bank_cents'],
                ],
                'mobile_money_amount' => [
                    $ledger['mobile_money_cents'],
                    $confirmed['confirmed_mobile_money_cents'],
                ],
            ];

            foreach ($pairs as $field => [$recorded, $counted]) {
                if ($recorded < 0 || $counted < 0) {
                    throw ValidationException::withMessages([
                        $field =>
                            'Negative balances must be investigated.',
                    ]);
                }

                if ($recorded !== $counted) {
                    throw ValidationException::withMessages([
                        $field =>
                            'Confirmed balance differs from the ' .
                            'recorded ledger balance. Reconcile ' .
                            'the difference before proceeding.',
                    ]);
                }
            }

            if (
                $confirmed['confirmed_bank_cents'] > 0
                && $bankReference === ''
            ) {
                throw ValidationException::withMessages([
                    'bank_statement_reference' =>
                        'Enter a bank statement reference.',
                ]);
            }

            if (
                $confirmed['confirmed_mobile_money_cents'] > 0
                && $mobileReference === ''
            ) {
                throw ValidationException::withMessages([
                    'mobile_money_reference' =>
                        'Enter a mobile-money statement reference.',
                ]);
            }

            return CyclePayoutReconciliation::create([
                'society_id' => $society->id,
                'cycle_payout_draft_id' => $draft->id,
                'reconciled_on' =>
                    now('Africa/Maseru')->toDateString(),

                'ledger_cash_cents' => $ledger['cash_cents'],
                'ledger_bank_cents' => $ledger['bank_cents'],
                'ledger_mobile_money_cents' =>
                    $ledger['mobile_money_cents'],

                ...$confirmed,

                'cash_count_notes' => $cashNotes,
                'bank_statement_reference' =>
                    $bankReference ?: null,
                'mobile_money_reference' =>
                    $mobileReference ?: null,
                'notes' => $notes ?: null,

                'source_hash' => $draft->source_hash,
                'request_key' => $data['request_key'],
                'confirmed_by' => $actor->id,
            ]);
        }, 3);
    }
}
