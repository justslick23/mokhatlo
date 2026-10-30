<?php

namespace App\Services\Payouts;

use App\Models\ContributionObligation;
use App\Models\ContributionPayment;
use App\Models\ContributionPenalty;
use App\Models\LedgerAccount;
use App\Models\Loan;
use App\Models\LoanInterestCredit;
use App\Models\LoanPenalty;
use App\Models\LoanRepaymentAllocation;
use App\Models\Society;
use App\Services\Accounting\SocietyAssetAccounts;
use App\Services\Loans\LoanBalances;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Validation\ValidationException;

final class CalculateCyclePayout
{
    public function __construct(
        private readonly SplitPenaltyPool $splitter,
        private readonly LoanBalances $balances,
        private readonly SocietyAssetAccounts $assets
    ) {}

    public function build(
        Society $society,
        int $cycleId,
        ?string $asOfDate = null
    ): array {
        $cycle = $society->cycles()
            ->with('policy')
            ->findOrFail($cycleId);

        if (! $cycle->policy) {
            throw ValidationException::withMessages([
                'cycle' => 'This cycle has no financial policy.',
            ]);
        }

        $timezone = $society->timezone ?: 'Africa/Maseru';

        $today = CarbonImmutable::today(
            $timezone
        )->toDateString();

        $asOf = CarbonImmutable::parse(
            $asOfDate ?: $today
        )->toDateString();

        $cycleStart = CarbonImmutable::parse(
            $cycle->start_date
        )->toDateString();

        $cycleEnd = CarbonImmutable::parse(
            $cycle->end_date
        )->toDateString();

        
            if ($asOf !== $today) {
                throw ValidationException::withMessages([
                    'as_of_date' =>
                        'This version only supports current-date payout snapshots. ' .
                        'Historical reconciliation is not implemented yet.',
                ]);
            }

            if ($asOf < $cycleStart) {
                throw ValidationException::withMessages([
                    'cycle' =>
                        'The savings cycle has not started yet.',
                ]);
            }


        $blockers = [];

        if ($asOf < $cycleEnd) {
            $blockers[] =
                'The savings cycle has not reached its end date.';
        }

        if ($cycle->status === 'closed') {
            $blockers[] =
                'This cycle has already been closed.';
        }

        if ($society->status !== 'active') {
            $blockers[] =
                'The society is not active.';
        }

        /*
         * Include all members who joined by the cutoff.
         * Do not silently discard former members' entitlements.
         */
        $members = $society->memberships()
            ->with('user')
            ->orderBy('id')
            ->get()
            ->filter(function ($member) use ($asOf) {
                $joined = $member->joined_at
                    ?? $member->created_at;

                return ! $joined
                    || CarbonImmutable::parse($joined)
                        ->toDateString() <= $asOf;
            });

        $rows = [];

        foreach ($members as $member) {
            $rows[(int) $member->id] = [
                'membership_id' => (int) $member->id,
                'member_name' =>
                    $member->user?->name ?? 'Unknown Member',
                'member_number' =>
                    $member->member_number,
                'membership_status' => $member->status,
                'savings_cents' => 0,
                'interest_refund_cents' => 0,
                'penalty_share_cents' => 0,
                'gross_payout_cents' => 0,
            ];

            if ($member->status !== 'active') {
                $blockers[] =
                    "Membership {$member->id} is not active; " .
                    'confirm payout eligibility before approval.';
            }
        }

        if (count($rows) === 0) {
            $blockers[] =
                'There are no members eligible for this cutoff date.';
        }

        /*
         * Member contributions.
         */
        $payments = ContributionPayment::query()
            ->where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->whereDate('paid_on', '<=', $asOf)
            ->get([
                'society_membership_id',
                'amount',
            ]);

        foreach ($payments as $payment) {
            $id = (int) $payment->society_membership_id;

            if (! isset($rows[$id])) {
                $blockers[] =
                    "Contribution belongs to membership {$id}, " .
                    'which is outside the payout roster.';
                continue;
            }

            $rows[$id]['savings_cents'] +=
                Money::toCents($payment->amount);
        }

        /*
         * Interest credits belong to the member who
         * borrowed the loan and paid that interest.
         */
        $credits = LoanInterestCredit::query()
            ->where('society_id', $society->id)
            ->whereHas('loan', fn ($query) =>
                $query->where('society_id', $society->id)
                    ->where('cycle_id', $cycle->id)
            )
            ->whereHas('repayment', fn ($query) =>
                $query->whereDate('paid_on', '<=', $asOf)
            )
            ->with(['loan', 'repayment'])
            ->get();

        $recordedInterestCents = 0;

        foreach ($credits as $credit) {
            $amount = Money::toCents($credit->amount);

            $recordedInterestCents += $amount;

            $borrowerId = (int)
                $credit->loan->society_membership_id;

            $beneficiaryId = (int)
                $credit->beneficiary_membership_id;

            if ($borrowerId !== $beneficiaryId) {
                $blockers[] =
                    "Interest credit {$credit->id} belongs to " .
                    'the wrong beneficiary. Reconcile it first.';
                continue;
            }

            if (! isset($rows[$borrowerId])) {
                $blockers[] =
                    "Interest credit {$credit->id} refers to " .
                    'a member outside the payout roster.';
                continue;
            }

            $rows[$borrowerId]['interest_refund_cents'] +=
                $amount;
        }

        /*
         * Check that interest paid through repayments
         * agrees with the interest-credit subledger.
         */
        $interestAllocations = LoanRepaymentAllocation::query()
            ->whereHas('repayment', function ($query) use (
                $society,
                $cycle,
                $asOf
            ) {
                $query->where('society_id', $society->id)
                    ->whereDate('paid_on', '<=', $asOf)
                    ->whereHas('loan', fn ($loanQuery) =>
                        $loanQuery
                            ->where('society_id', $society->id)
                            ->where('cycle_id', $cycle->id)
                    );
            })
            ->get(['interest_amount']);

        $allocatedInterestCents = $interestAllocations->sum(
            fn ($allocation) =>
                Money::toCents($allocation->interest_amount)
        );

        if ($allocatedInterestCents !== $recordedInterestCents) {
            $blockers[] =
                'Collected loan interest does not reconcile ' .
                'with the member interest-credit records.';
        }

        /*
         * Combine loan and contribution penalty collections.
         * Waivers do not enter the distributable cash pool.
         */
        $penaltyPool = 0;

        $contributionPenalties = ContributionPenalty::query()
            ->where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->whereDate('created_at', '<=', $asOf)
            ->with([
                'payments' => fn ($query) =>
                    $query->whereDate('paid_on', '<=', $asOf),
                'waivers' => fn ($query) =>
                    $query->whereDate('created_at', '<=', $asOf),
            ])
            ->get();

        foreach ($contributionPenalties as $penalty) {
            $paid = $penalty->payments->sum(
                fn ($payment) => Money::toCents(
                    $payment->amount
                )
            );

            $waived = $penalty->waivers->sum(
                fn ($waiver) => Money::toCents(
                    $waiver->amount
                )
            );

            $penaltyPool += $paid;

            if (
                Money::toCents($penalty->assessed_amount)
                > $paid + $waived
            ) {
                $blockers[] =
                    "Contribution penalty {$penalty->id} " .
                    'has an unpaid balance.';
            }
        }

        $loanPenalties = LoanPenalty::query()
            ->where('society_id', $society->id)
            ->whereHas('loan', fn ($query) =>
                $query->where('society_id', $society->id)
                    ->where('cycle_id', $cycle->id)
            )
            ->whereDate('created_at', '<=', $asOf)
            ->with([
                'payments' => fn ($query) =>
                    $query->whereDate('paid_on', '<=', $asOf),
                'waivers' => fn ($query) =>
                    $query->whereDate('created_at', '<=', $asOf),
            ])
            ->get();

        foreach ($loanPenalties as $penalty) {
            $paid = $penalty->payments->sum(
                fn ($payment) => Money::toCents(
                    $payment->amount
                )
            );

            $waived = $penalty->waivers->sum(
                fn ($waiver) => Money::toCents(
                    $waiver->amount
                )
            );

            $penaltyPool += $paid;

            if (
                Money::toCents($penalty->assessed_amount)
                > $paid + $waived
            ) {
                $blockers[] =
                    "Loan penalty {$penalty->id} " .
                    'has an unpaid balance.';
            }
        }

        /*
         * Confirm contribution obligations have been met.
         * Only obligations due by the cutoff block readiness.
         */
        $obligations = ContributionObligation::query()
            ->where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->whereDate('due_date', '<=', $asOf)
            ->with([
                'payments' => fn ($query) =>
                    $query->whereDate('paid_on', '<=', $asOf),
            ])
            ->get();

        foreach ($obligations as $obligation) {
            $received = $obligation->payments->sum(
                fn ($payment) => Money::toCents(
                    $payment->amount
                )
            );

            if (
                $received < Money::toCents(
                    $obligation->required_amount
                )
            ) {
                $blockers[] =
                    "Contribution obligation {$obligation->id} " .
                    'has an unpaid minimum.';
            }
        }

        /*
         * An outstanding loan must be resolved before
         * approving a final payout. No automatic deduction.
         */
        $loans = Loan::query()
            ->where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->get();

        foreach ($loans as $loan) {
            if (in_array(
                $loan->status,
                ['approved', 'pending'],
                true
            )) {
                $blockers[] =
                    "Loan {$loan->loan_number} is awaiting " .
                    'disbursement or cancellation.';
                continue;
            }

            if (! in_array(
                $loan->status,
                ['disbursed', 'settled'],
                true
            )) {
                continue;
            }

            if (! $loan->matures_on) {
                $blockers[] =
                    "Loan {$loan->loan_number} has no maturity date.";
            }

            if (! $loan->installments()->exists()) {
                $blockers[] =
                    "Loan {$loan->loan_number} has no " .
                    'contractual repayment obligation.';
                continue;
            }

            try {
                $balance = $this->balances->summary($loan);

                if ($balance['total_outstanding'] > 0) {
                    $blockers[] =
                        "Loan {$loan->loan_number} has an " .
                        'outstanding contractual balance of M' .
                        Money::fromCents(
                            $balance['total_outstanding']
                        ) . '.';
                }
            } catch (DomainException $exception) {
                $blockers[] =
                    "Loan {$loan->loan_number}: " .
                    $exception->getMessage();
            }
        }

        /*
         * Allocate collected penalty proceeds using
         * the society's selected distribution policy.
         */
        $method = (string)
            $cycle->policy->penalty_distribution_method;

        $weights = [];

        foreach ($rows as $id => $row) {
            $weights[$id] = $row['savings_cents'];
        }

        try {
            $shares = $this->splitter->execute(
                $penaltyPool,
                $method,
                $weights
            );
        } catch (DomainException $exception) {
            $blockers[] = $exception->getMessage();

            $shares = array_fill_keys(
                array_keys($rows),
                0
            );
        }

        foreach ($rows as $id => &$row) {
            $row['penalty_share_cents'] =
                $shares[$id] ?? 0;

            $row['gross_payout_cents'] =
                $row['savings_cents']
                + $row['interest_refund_cents']
                + $row['penalty_share_cents'];
        }

        unset($row);

        $totals = [
            'savings_cents' => 0,
            'interest_refund_cents' => 0,
            'penalty_share_cents' => 0,
            'gross_payout_cents' => 0,
        ];

        foreach ($rows as $row) {
            foreach ($totals as $key => $_) {
                $totals[$key] += $row[$key];
            }
        }

        $distributed = $totals['penalty_share_cents'];

        $undistributed = $penaltyPool - $distributed;

        if (
            $method !== 'society_reserve'
            && $undistributed !== 0
        ) {
            $blockers[] =
                'Collected penalties have not been fully allocated.';
        }

        /*
         * Check recorded liquid assets separately from
         * member entitlements.
         */
        $liquidAssets = 0;

        foreach ([
            'CASH_ON_HAND',
            'BANK_ACCOUNT',
            'MOBILE_MONEY',
        ] as $code) {
            $account = LedgerAccount::query()
                ->where('society_id', $society->id)
                ->where('code', $code)
                ->first();

            if ($account) {
                $liquidAssets +=
                    $this->assets->balanceCents($account);
            }
        }

        if ($liquidAssets < $totals['gross_payout_cents']) {
            $blockers[] =
                'Recorded liquid assets are insufficient ' .
                'to cover the proposed gross payout.';
        }

        $rows = array_values($rows);

        $blockers = array_values(array_unique($blockers));

        $result = [
            'society_id' => $society->id,
            'cycle_id' => $cycle->id,
            'cycle_name' => $cycle->name,
            'as_of_date' => $asOf,
            'distribution_method' => $method,
            'rows' => $rows,
            'totals' => $totals,
            'penalty_pool_cents' => $penaltyPool,
            'penalty_distributed_cents' => $distributed,
            'penalty_reserved_cents' => $undistributed,
            'liquid_assets_cents' => $liquidAssets,
            'blockers' => $blockers,
            'is_final' => false,
        ];

        $result['source_hash'] = hash(
            'sha256',
            json_encode($result, JSON_THROW_ON_ERROR)
        );

        return $result;
    }
}
