<?php

namespace App\Services\Payouts;

use App\Models\CyclePayoutBatch;
use App\Models\CyclePayoutItem;
use App\Models\CyclePayoutPayment;
use App\Models\LedgerTransaction;
use App\Models\Society;
use App\Models\User;
use App\Services\Accounting\SocietyAssetAccounts;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RecordCyclePayoutPayment
{
    public function __construct(
        private readonly AuthorizePayoutAction $authorizer,
        private readonly PayoutLedgerAccounts $accounts,
        private readonly SocietyAssetAccounts $assets
    ) {}

    public function execute(
        Society $society,
        User $actor,
        int $batchId,
        int $itemId,
        array $data
    ): CyclePayoutPayment {
        $this->authorizer->check(
            $society,
            $actor,
            'payouts.execute'
        );

        $data = Validator::make($data, [
            'amount' => [
                'required',
                'regex:/^\d{1,15}(\.\d{1,2})?$/',
            ],
            'paid_on' => [
                'required',
                'date_format:Y-m-d',
            ],
            'payment_method' => [
                'required',
                Rule::in([
                    'cash',
                    'bank_transfer',
                    'mobile_money',
                ]),
            ],
            'payment_reference' => [
                'nullable', 'string', 'max:120',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
            'confirmed' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use (
            $society,
            $actor,
            $batchId,
            $itemId,
            $data
        ) {
            Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            $batch = CyclePayoutBatch::query()
                ->where('society_id', $society->id)
                ->whereKey($batchId)
                ->lockForUpdate()
                ->firstOrFail();

            $item = CyclePayoutItem::query()
                ->where('cycle_payout_draft_id',
                    $batch->cycle_payout_draft_id)
                ->where('society_id', $society->id)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            $amountCents = Money::toCents($data['amount']);

            $reference = trim(
                $data['payment_reference'] ?? ''
            );

            $notes = trim($data['notes'] ?? '');

            $existing = CyclePayoutPayment::query()
                ->where('society_id', $society->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                $same =
                    (int) $existing->cycle_payout_batch_id
                        === (int) $batch->id
                    && (int) $existing->cycle_payout_item_id
                        === (int) $item->id
                    && (int) $existing->recorded_by
                        === (int) $actor->id
                    && (int) $existing->amount_cents
                        === $amountCents
                    && $existing->paid_on->toDateString()
                        === $data['paid_on']
                    && $existing->payment_method
                        === $data['payment_method']
                    && ($existing->payment_reference ?? '')
                        === $reference
                    && ($existing->notes ?? '') === $notes;

                if (! $same) {
                    throw ValidationException::withMessages([
                        'idempotency_key' =>
                            'This request key was used for another payout.',
                    ]);
                }

                return $existing;
            }

            if ($batch->status !== 'processing') {
                throw ValidationException::withMessages([
                    'payout' => 'The payout batch is not processing.',
                ]);
            }

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount must be greater than zero.',
                ]);
            }

            $paidOn = $data['paid_on'];

            if (
                $paidOn < $batch->started_at->toDateString()
                || $paidOn > now('Africa/Maseru')->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'paid_on' =>
                        'Payment must be dated from batch start ' .
                        'through today.',
                ]);
            }

            if (
                $data['payment_method'] !== 'cash'
                && $reference === ''
            ) {
                throw ValidationException::withMessages([
                    'payment_reference' =>
                        'A bank or mobile-money reference is required.',
                ]);
            }

            $member = $society->memberships()
                ->findOrFail($item->society_membership_id);

            // An official cannot record their own annual payout.
            if ((int) $member->user_id === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'payout' =>
                        'Another authorised official must record ' .
                        'your own payout.',
                ]);
            }

            /*
             * Reject unexpected non-payout ledger postings
             * after settlement began.
             */
            $unexpectedPosting = LedgerTransaction::query()
                ->where('society_id', $society->id)
                ->where('cycle_id', $batch->cycle_id)
                ->where('created_at', '>', $batch->started_at)
                ->whereNotIn('source_type', [
                    'cycle_payout_penalty_allocation',
                    'cycle_payout_payment',
                ])
                ->exists();

            if ($unexpectedPosting) {
                throw ValidationException::withMessages([
                    'payout' =>
                        'Financial activity was detected after payout ' .
                        'settlement began. Investigate before continuing.',
                ]);
            }

            $previous = CyclePayoutPayment::query()
                ->where('society_id', $society->id)
                ->where('cycle_payout_batch_id', $batch->id)
                ->where('cycle_payout_item_id', $item->id)
                ->get();

            $paidSavings = $previous->sum('savings_cents');
            $paidInterest = $previous->sum('interest_cents');
            $paidPenalties = $previous->sum('penalty_cents');

                        
                $allocation = app(
                    \App\Services\Payouts\AllocateMemberPayoutPayment::class
                )->execute(
                    [
                        'savings' => (int) $item->savings_cents,
                        'interest' => (int) $item->interest_refund_cents,
                        'penalty' => (int) $item->penalty_share_cents,
                    ],
                    [
                        'savings' => (int) $paidSavings,
                        'interest' => (int) $paidInterest,
                        'penalty' => (int) $paidPenalties,
                    ],
                    $amountCents
                );

                $savingsCents = $allocation['savings'];
                $interestCents = $allocation['interest'];
                $penaltyCents = $allocation['penalty'];


            try {
                $cash = $this->assets->forMethod(
                    $society,
                    $data['payment_method']
                );

                if (
                    $this->assets->balanceCents($cash)
                    < $amountCents
                ) {
                    throw new DomainException(
                        'Insufficient recorded funds in the ' .
                        'selected payment account.'
                    );
                }

                $savingsAccount = $savingsCents > 0
                    ? $this->accounts->find(
                        $society,
                        'savings_payable',
                        'liability'
                    )
                    : null;

                $interestAccount = $interestCents > 0
                    ? $this->accounts->find(
                        $society,
                        'interest_payable',
                        'liability'
                    )
                    : null;

                $penaltyAccount = $penaltyCents > 0
                    ? $this->accounts->find(
                        $society,
                        'penalties_payable',
                        'liability'
                    )
                    : null;

                foreach ([
                    [$savingsAccount, $savingsCents],
                    [$interestAccount, $interestCents],
                    [$penaltyAccount, $penaltyCents],
                ] as [$account, $required]) {
                    if ($required > 0) {
                        $this->accounts->assertCreditAtLeast(
                            $account,
                            $required
                        );
                    }
                }

                $payment = CyclePayoutPayment::create([
                    'society_id' => $society->id,
                    'cycle_payout_batch_id' => $batch->id,
                    'cycle_payout_item_id' => $item->id,
                    'amount_cents' => $amountCents,
                    'savings_cents' => $savingsCents,
                    'interest_cents' => $interestCents,
                    'penalty_cents' => $penaltyCents,
                    'paid_on' => $paidOn,
                    'payment_method' => $data['payment_method'],
                    'payment_reference' => $reference ?: null,
                    'notes' => $notes ?: null,
                    'idempotency_key' => $data['idempotency_key'],
                    'recorded_by' => $actor->id,
                ]);

                $journal = LedgerTransaction::create([
                    'society_id' => $society->id,
                    'cycle_id' => $batch->cycle_id,
                    'source_type' => 'cycle_payout_payment',
                    'source_id' => $payment->id,
                    'posting_date' => $paidOn,
                    'description' =>
                        'Year-end payout to ' . $item->member_name,
                    'created_by' => $actor->id,
                ]);

                foreach ([
                    [$savingsAccount, $savingsCents],
                    [$interestAccount, $interestCents],
                    [$penaltyAccount, $penaltyCents],
                ] as [$account, $cents]) {
                    if ($cents <= 0) {
                        continue;
                    }

                    $journal->lines()->create([
                        'ledger_account_id' => $account->id,
                        'debit_amount' =>
                            Money::fromCents($cents),
                        'credit_amount' => '0.00',
                    ]);
                }

                $journal->lines()->create([
                    'ledger_account_id' => $cash->id,
                    'debit_amount' => '0.00',
                    'credit_amount' =>
                        Money::fromCents($amountCents),
                ]);

                $payment->update([
                    'ledger_transaction_id' => $journal->id,
                ]);

                return $payment;
            } catch (DomainException $exception) {
                throw ValidationException::withMessages([
                    'ledger' => $exception->getMessage(),
                ]);
            }
        }, 3);
    }
}
