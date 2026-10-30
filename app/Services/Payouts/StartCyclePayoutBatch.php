<?php

namespace App\Services\Payouts;

use App\Models\ContributionPenaltyPayment;
use App\Models\CyclePayoutApproval;
use App\Models\CyclePayoutBatch;
use App\Models\CyclePayoutDraft;
use App\Models\LedgerTransaction;
use App\Models\LoanPenaltyPayment;
use App\Models\Society;
use App\Models\User;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StartCyclePayoutBatch
{
    public function __construct(
        private readonly AuthorizePayoutAction $authorizer,
        private readonly VerifyApprovedPayoutForSettlement $verifier,
        private readonly PayoutLedgerAccounts $accounts
    ) {}

    public function execute(
        Society $society,
        User $actor,
        int $draftId,
        string $requestKey
    ): CyclePayoutBatch {
        $this->authorizer->check(
            $society,
            $actor,
            'payouts.execute'
        );

        if (! Str::isUuid($requestKey)) {
            throw ValidationException::withMessages([
                'request_key' => 'Invalid payout request key.',
            ]);
        }

        return DB::transaction(function () use (
            $society,
            $actor,
            $draftId,
            $requestKey
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

            $existing = CyclePayoutBatch::query()
                ->where('society_id', $society->id)
                ->where('request_key', $requestKey)
                ->first();

            if ($existing) {
                if (
                    (int) $existing->cycle_payout_draft_id
                        !== (int) $draft->id
                    || (int) $existing->started_by
                        !== (int) $actor->id
                ) {
                    throw ValidationException::withMessages([
                        'request_key' =>
                            'This payout request key was used previously.',
                    ]);
                }

                return $existing;
            }

            if (
                CyclePayoutBatch::query()
                    ->where('cycle_id', $draft->cycle_id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'payout' =>
                        'This cycle already has a settlement batch.',
                ]);
            }

            $cycle = $society->cycles()
                ->whereKey($draft->cycle_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cycle->status !== 'active') {
                throw ValidationException::withMessages([
                    'cycle' =>
                        'Only an active cycle can begin payout settlement.',
                ]);
            }

            $approval = CyclePayoutApproval::query()
                ->where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('cycle_payout_draft_id', $draft->id)
                ->firstOrFail();

            $this->verifier->execute(
                $society,
                $draft,
                $approval
            );

            /*
             * Verify that savings and interest already
             * exist in the correct payable accounts.
             */
            try {
                $savings = $this->accounts->find(
                    $society,
                    'savings_payable',
                    'liability'
                );

                $interest = $this->accounts->find(
                    $society,
                    'interest_payable',
                    'liability'
                );

                $savingsPosted = $this->accounts->sourceNetCredit(
                    $society,
                    $cycle->id,
                    'contribution_payment',
                    $savings
                );

                $interestPosted = $this->accounts->sourceNetCredit(
                    $society,
                    $cycle->id,
                    'loan_repayment',
                    $interest
                );

                if (
                    $savingsPosted !== (int) $draft->savings_total_cents
                    || $interestPosted !== (int) $draft->interest_total_cents
                ) {
                    throw new DomainException(
                        'Savings or interest payable entries do not ' .
                        'reconcile with the approved payout.'
                    );
                }

                $this->accounts->assertCreditAtLeast(
                    $savings,
                    (int) $draft->savings_total_cents
                );

                $this->accounts->assertCreditAtLeast(
                    $interest,
                    (int) $draft->interest_total_cents
                );

                /*
                 * Verify penalty income against actual
                 * receipts recorded in the cycle.
                 */
                $contributionCollected = 0;

                ContributionPenaltyPayment::query()
                    ->where('society_id', $society->id)
                    ->whereHas('penalty', fn ($q) =>
                        $q->where('cycle_id', $cycle->id)
                    )
                    ->get(['amount'])
                    ->each(function ($payment) use (
                        &$contributionCollected
                    ) {
                        $contributionCollected +=
                            Money::toCents($payment->amount);
                    });

                $loanCollected = 0;

                LoanPenaltyPayment::query()
                    ->where('society_id', $society->id)
                    ->whereHas('loanPenalty.loan', fn ($q) =>
                        $q->where('cycle_id', $cycle->id)
                    )
                    ->get(['amount'])
                    ->each(function ($payment) use (
                        &$loanCollected
                    ) {
                        $loanCollected +=
                            Money::toCents($payment->amount);
                    });

                if (
                    $contributionCollected + $loanCollected
                    !== (int) $draft->penalty_pool_cents
                ) {
                    throw new DomainException(
                        'Penalty receipts do not match the approved pool.'
                    );
                }

                $contributionIncome = $contributionCollected > 0
                    ? $this->accounts->find(
                        $society,
                        'contribution_penalty_income',
                        'income'
                    )
                    : null;

                $loanIncome = $loanCollected > 0
                    ? $this->accounts->find(
                        $society,
                        'loan_penalty_income',
                        'income'
                    )
                    : null;

                if (
                    $contributionIncome
                    && $this->accounts->sourceNetCredit(
                        $society,
                        $cycle->id,
                        'contribution_penalty_payment',
                        $contributionIncome
                    ) !== $contributionCollected
                ) {
                    throw new DomainException(
                        'Contribution penalty income is not reconciled.'
                    );
                }

                if (
                    $loanIncome
                    && $this->accounts->sourceNetCredit(
                        $society,
                        $cycle->id,
                        'loan_penalty_payment',
                        $loanIncome
                    ) !== $loanCollected
                ) {
                    throw new DomainException(
                        'Loan penalty income is not reconciled.'
                    );
                }

                $distributed = (int)
                    $draft->penalty_distributed_cents;

                $fromContribution = min(
                    $distributed,
                    $contributionCollected
                );

                $fromLoan = $distributed - $fromContribution;

                if ($fromLoan > $loanCollected) {
                    throw new DomainException(
                        'Penalty distribution exceeds collected income.'
                    );
                }

                if ($fromContribution > 0) {
                    $this->accounts->assertCreditAtLeast(
                        $contributionIncome,
                        $fromContribution
                    );
                }

                if ($fromLoan > 0) {
                    $this->accounts->assertCreditAtLeast(
                        $loanIncome,
                        $fromLoan
                    );
                }

                $batch = CyclePayoutBatch::create([
                    'society_id' => $society->id,
                    'cycle_id' => $cycle->id,
                    'cycle_payout_draft_id' => $draft->id,
                    'cycle_payout_approval_id' => $approval->id,
                    'status' => 'processing',
                    'approved_source_hash' =>
                        $draft->source_hash,
                    'gross_total_cents' =>
                        $draft->gross_total_cents,
                    'penalty_distributed_cents' =>
                        $distributed,
                    'request_key' => $requestKey,
                    'started_by' => $actor->id,
                    'started_at' => now(),
                ]);

                if ($distributed > 0) {
                    $penaltyPayable = $this->accounts
                        ->penaltyPayable($society);

                    $journal = LedgerTransaction::create([
                        'society_id' => $society->id,
                        'cycle_id' => $cycle->id,
                        'source_type' =>
                            'cycle_payout_penalty_allocation',
                        'source_id' => $batch->id,
                        'posting_date' =>
                            now('Africa/Maseru')->toDateString(),
                        'description' =>
                            'Allocate shared penalties for cycle payout',
                        'created_by' => $actor->id,
                    ]);

                    if ($fromContribution > 0) {
                        $journal->lines()->create([
                            'ledger_account_id' =>
                                $contributionIncome->id,
                            'debit_amount' =>
                                Money::fromCents($fromContribution),
                            'credit_amount' => '0.00',
                        ]);
                    }

                    if ($fromLoan > 0) {
                        $journal->lines()->create([
                            'ledger_account_id' =>
                                $loanIncome->id,
                            'debit_amount' =>
                                Money::fromCents($fromLoan),
                            'credit_amount' => '0.00',
                        ]);
                    }

                    $journal->lines()->create([
                        'ledger_account_id' =>
                            $penaltyPayable->id,
                        'debit_amount' => '0.00',
                        'credit_amount' =>
                            Money::fromCents($distributed),
                    ]);

                    $batch->update([
                        'penalty_journal_id' => $journal->id,
                    ]);
                }

                return $batch;
            } catch (DomainException $exception) {
                throw ValidationException::withMessages([
                    'ledger' => $exception->getMessage(),
                ]);
            }
        }, 3);
    }
}
