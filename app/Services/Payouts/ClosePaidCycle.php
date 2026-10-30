<?php

namespace App\Services\Payouts;

use App\Models\CyclePayoutBatch;
use App\Models\LedgerTransaction;
use App\Models\Society;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClosePaidCycle
{
    public function __construct(
        private readonly AuthorizePayoutAction $authorizer
    ) {}

    public function execute(
        Society $society,
        User $actor,
        int $batchId
    ): CyclePayoutBatch {
        $this->authorizer->check(
            $society,
            $actor,
            'payouts.close'
        );

        return DB::transaction(function () use (
            $society,
            $actor,
            $batchId
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

            if ($batch->status === 'completed') {
                return $batch;
            }

            if ($batch->status !== 'processing') {
                throw ValidationException::withMessages([
                    'payout' => 'This batch is not processing.',
                ]);
            }

            if ((int) $batch->started_by === (int) $actor->id) {
                throw ValidationException::withMessages([
                    'payout' =>
                        'Another authorised official must ' .
                        'complete the cycle closure.',
                ]);
            }

            $cycle = $society->cycles()
                ->whereKey($batch->cycle_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cycle->status !== 'active') {
                throw ValidationException::withMessages([
                    'cycle' =>
                        'The savings cycle is not active.',
                ]);
            }

            $draft = $batch->draft()
                ->with(['items.payments', 'approval'])
                ->firstOrFail();

            if (
                $draft->status !== 'approved'
                || ! $draft->approval
                || ! hash_equals(
                    (string) $batch->approved_source_hash,
                    (string) $draft->source_hash
                )
            ) {
                throw ValidationException::withMessages([
                    'payout' =>
                        'The approved payout snapshot is inconsistent.',
                ]);
            }

            $unexpectedPosting = LedgerTransaction::query()
                ->where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('created_at', '>', $batch->started_at)
                ->whereNotIn('source_type', [
                    'cycle_payout_penalty_allocation',
                    'cycle_payout_payment',
                ])
                ->exists();

            if ($unexpectedPosting) {
                throw ValidationException::withMessages([
                    'ledger' =>
                        'Unexpected financial postings were recorded ' .
                        'after payout settlement began.',
                ]);
            }

            $paidTotal = 0;
            $paidSavings = 0;
            $paidInterest = 0;
            $paidPenalties = 0;

            foreach ($draft->items as $item) {
                $payments = $item->payments
                    ->where('cycle_payout_batch_id', $batch->id);

                $memberTotal = $payments->sum('amount_cents');

                if (
                    $memberTotal
                    !== (int) $item->gross_payout_cents
                ) {
                    throw ValidationException::withMessages([
                        'payout' =>
                            "Member {$item->member_name} has not " .
                            'received their full recorded payout.',
                    ]);
                }

                $memberSavings = $payments->sum('savings_cents');
                $memberInterest = $payments->sum('interest_cents');
                $memberPenalties = $payments->sum('penalty_cents');

                if (
                    $memberSavings !== (int) $item->savings_cents
                    || $memberInterest
                        !== (int) $item->interest_refund_cents
                    || $memberPenalties
                        !== (int) $item->penalty_share_cents
                ) {
                    throw ValidationException::withMessages([
                        'payout' =>
                            'A member payout allocation does not match ' .
                            'the approved entitlement.',
                    ]);
                }

                foreach ($payments as $payment) {
                    $journal = $payment->ledgerTransaction()
                        ->with('lines')
                        ->first();

                    if (
                        ! $journal
                        || $journal->source_type
                            !== 'cycle_payout_payment'
                        || (int) $journal->source_id
                            !== (int) $payment->id
                        || (int) $journal->society_id
                            !== (int) $society->id
                        || (int) $journal->cycle_id
                            !== (int) $cycle->id
                    ) {
                        throw ValidationException::withMessages([
                            'ledger' =>
                                'A payout payment has a missing ' .
                                'or invalid ledger transaction.',
                        ]);
                    }

                    $debits = $journal->lines->sum(
                        fn ($line) => Money::toCents(
                            $line->debit_amount
                        )
                    );

                    $credits = $journal->lines->sum(
                        fn ($line) => Money::toCents(
                            $line->credit_amount
                        )
                    );

                    if (
                        $debits !== $credits
                        || $debits !== (int) $payment->amount_cents
                    ) {
                        throw ValidationException::withMessages([
                            'ledger' =>
                                'A payout ledger transaction is unbalanced.',
                        ]);
                    }
                }

                $paidTotal += $memberTotal;
                $paidSavings += $memberSavings;
                $paidInterest += $memberInterest;
                $paidPenalties += $memberPenalties;
            }

            if (
                $paidTotal !== (int) $draft->gross_total_cents
                || $paidSavings !== (int) $draft->savings_total_cents
                || $paidInterest !== (int) $draft->interest_total_cents
                || $paidPenalties
                    !== (int) $draft->penalty_distributed_cents
            ) {
                throw ValidationException::withMessages([
                    'payout' =>
                        'The paid totals do not reconcile ' .
                        'with the approved payout.',
                ]);
            }

            if ($batch->penalty_distributed_cents > 0) {
                $journal = $batch->penaltyJournal()
                    ->with('lines')
                    ->first();

                if (
                    ! $journal
                    || $journal->source_type
                        !== 'cycle_payout_penalty_allocation'
                    || (int) $journal->source_id
                        !== (int) $batch->id
                ) {
                    throw ValidationException::withMessages([
                        'ledger' =>
                            'The penalty allocation journal is missing.',
                    ]);
                }

                $debit = $journal->lines->sum(
                    fn ($line) => Money::toCents(
                        $line->debit_amount
                    )
                );

                $credit = $journal->lines->sum(
                    fn ($line) => Money::toCents(
                        $line->credit_amount
                    )
                );

                if (
                    $debit !== $credit
                    || $debit !== $batch->penalty_distributed_cents
                ) {
                    throw ValidationException::withMessages([
                        'ledger' =>
                            'Penalty distribution journal is unbalanced.',
                    ]);
                }
            }

            /*
             * End the cycle without deleting its history.
             * Clearing the active slot permits a future cycle.
             */
            $cycle->update([
                'status' => 'closed',
                'active_slot' => null,
            ]);

            $batch->update([
                'status' => 'completed',
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ]);

            return $batch->refresh();
        }, 3);
    }
}
