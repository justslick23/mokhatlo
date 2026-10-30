<?php

namespace App\Services\Legacy;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * READ ONLY: never writes or reallocates historical receipts.
 * All money comparisons use integer cents and the original encrypted plan.
 */
final class AuditLegacyFinancialImport
{
    private function cents(string|int|float|null $value): int
    {
        $s = (string) $value;
        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $s)) {
            throw new RuntimeException("Unexpected money decimal: {$s}");
        }
        $negative = str_starts_with($s, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($s, '-'), 2), 2, '');
        $result = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        return $negative ? -$result : $result;
    }

    public function money(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . 'M ' . number_format(abs($cents) / 100, 2);
    }

    public function execute(int $societyId, ?int $runId = null): array
    {
        $query = DB::table('legacy_financial_import_runs')
            ->where('society_id', $societyId);
        if ($runId !== null) {
            $query->where('id', $runId);
        }
        $run = $query->orderByDesc('id')->first();
        if (! $run) {
            throw new RuntimeException('No historical financial import exists for this society.');
        }

        $cycleId = (int) $run->cycle_id;
        $batch = DB::table('legacy_import_batches')->find($run->batch_id);
        if (! $batch) {
            throw new RuntimeException('Original source batch is missing.');
        }
        $planPath = storage_path("app/private/legacy-audits/batch-{$run->batch_id}-financial-plan.enc");
        if (! is_file($planPath)) {
            throw new RuntimeException('Encrypted original financial plan is unavailable.');
        }
        $plain = Crypt::decryptString((string) file_get_contents($planPath));
        if (! hash_equals((string) $run->plan_sha256, hash('sha256', $plain))) {
            throw new RuntimeException('Original financial plan fingerprint does not match the imported run.');
        }
        $plan = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        if (
            (int) ($plan['cycle_id'] ?? 0) !== $cycleId ||
            (int) ($plan['society_id'] ?? 0) !== $societyId ||
            (int) ($plan['source_batch_id'] ?? 0) !== (int) $run->batch_id ||
            ! hash_equals((string) $run->source_fingerprint, (string) ($plan['source_fingerprint'] ?? '')) ||
            ! hash_equals((string) $batch->file_sha256, (string) $run->source_fingerprint)
        ) {
            throw new RuntimeException('Imported run and source financial plan disagree.');
        }

        $sourceId = (int) $batch->source_id;
        $cycle = DB::table('cycles')->where('society_id', $societyId)->find($cycleId);
        if (! $cycle) {
            throw new RuntimeException('Mapped cycle is missing from this society.');
        }
        $posted = json_decode((string) $run->posted_totals, true, 512, JSON_THROW_ON_ERROR);
        $expected = $plan['totals_cents'];

        // Audit the IMMUTABLE original import only. Later, genuine loan repayments
        // and closing journals must not make the historical baseline audit fail.
        $originalLoanIds = DB::table('legacy_record_maps')
            ->where('source_id', $sourceId)->where('source_table', 'loans')
            ->where('target_table', 'loans')->pluck('target_id')->all();
        $originalContributionIds = DB::table('legacy_record_maps')
            ->where('source_id', $sourceId)->where('source_table', 'transactions')
            ->where('target_table', 'contribution_payments')->pluck('target_id')->all();
        $originalDisbursementIds = DB::table('legacy_record_maps')
            ->where('source_id', $sourceId)->where('source_table', 'transactions')
            ->where('target_table', 'loan_disbursements')->pluck('target_id')->all();
        $originalRepaymentIds = DB::table('legacy_record_maps')
            ->where('source_id', $sourceId)->where('source_table', 'transactions')
            ->where('target_table', 'loan_repayments')->pluck('target_id')->all();
        $loans = DB::table('loans')->where('society_id', $societyId)
            ->where('cycle_id', $cycleId)->whereIn('id', $originalLoanIds)->get();
        $loanIds = $loans->pluck('id')->all();
        $loansById = $loans->keyBy('id');
        $contributions = DB::table('contribution_payments')
            ->where('society_id', $societyId)->where('cycle_id', $cycleId)
            ->whereIn('id', $originalContributionIds)->get();
        $disbursements = DB::table('loan_disbursements')->where('society_id', $societyId)
            ->whereIn('id', $originalDisbursementIds)->get();
        $repayments = DB::table('loan_repayments')->where('society_id', $societyId)
            ->whereIn('id', $originalRepaymentIds)->get();
        $repaymentIds = $repayments->pluck('id')->all();
        $allocations = DB::table('loan_repayment_allocations')
            ->whereIn('loan_repayment_id', $repaymentIds)->get();
        $interestCredits = DB::table('loan_interest_credits')->where('society_id', $societyId)
            ->whereIn('loan_repayment_id', $repaymentIds)->get();
        // Step 9C's actual imported receipts already provide the review queue.
        // Some installations did NOT create the optional legacy_contribution_reviews
        // table. Derive the audit from real receipts and their immutable source maps,
        // rather than inventing a review table or importing contributions twice.
        $unallocated = DB::table('legacy_record_maps as m')
            ->join('contribution_payments as p', 'p.id', '=', 'm.target_id')
            ->where('m.source_id', $sourceId)
            ->where('m.source_table', 'transactions')
            ->where('m.target_table', 'contribution_payments')
            ->where('p.society_id', $societyId)
            ->where('p.cycle_id', $cycleId)
            ->select([
                'p.id', 'p.society_membership_id', 'p.amount', 'p.paid_on',
                'p.contribution_obligation_id',
                'm.source_id_value as source_transaction_id',
            ])
            ->get()
            ->map(function ($payment) {
                $payment->amount_cents = $this->cents($payment->amount);
                $payment->status = $payment->contribution_obligation_id === null
                    ? 'pending_allocation' : 'allocated';
                return $payment;
            });
        $reviews = DB::table('legacy_financial_review_items')
            ->where('legacy_financial_import_run_id', $run->id)->orderBy('id')->get();

        $sum = fn ($rows, $column): int => $rows->sum(fn ($r): int => $this->cents($r->{$column}));
        $sumCents = fn ($rows, $column): int => $rows->sum(fn ($r): int => (int) $r->{$column});

        $checks = [];
        $check = function (string $label, int $wanted, int $actual, bool $isMoney = false) use (&$checks): void {
            $checks[] = [
                'label' => $label, 'passed' => $wanted === $actual,
                'expected' => $isMoney ? $this->money($wanted) : (string) $wanted,
                'actual' => $isMoney ? $this->money($actual) : (string) $actual,
            ];
        };
        $check('Historical loan count', count($plan['loans']), $loans->count());
        $check('Disbursement count', count($plan['loans']), $disbursements->count());
        $check('Contribution count', count($plan['contributions']), $contributions->count());
        $expectedRepayments = array_sum(array_map(fn ($loan) => count($loan['repayments']), $plan['loans']));
        $expectedCredits = array_sum(array_map(fn ($loan) => count(array_filter($loan['repayments'],
            fn ($r) => (int) $r['interest_cents'] > 0)), $plan['loans']));
        $check('Repayment count', $expectedRepayments, $repayments->count());
        $check('Repayment allocation count', $expectedRepayments, $allocations->count());
        $check('Interest credit count', $expectedCredits, $interestCredits->count());
        $check('Mapped historical contribution receipt count', count($plan['contributions']), $unallocated->count());

        // Validate original transaction identity, amount, member, and payment date.
        // A matching aggregate sum alone can conceal individual receipt errors.
        $mappedReceipts = $unallocated->keyBy(fn ($r) => (string) $r->source_transaction_id);
        $receiptSourceIssues = 0;
        foreach ($plan['contributions'] as $original) {
            $payment = $mappedReceipts->get((string) $original['legacy_transaction_id']);
            if (
                ! $payment
                || $payment->amount_cents !== (int) $original['amount_cents']
                || (int) $payment->society_membership_id !== (int) $original['membership_id']
                || substr((string) $payment->paid_on, 0, 10) !== (string) $original['date']
            ) {
                $receiptSourceIssues++;
            }
        }
        $check('Individual historical receipt source discrepancies', 0, $receiptSourceIssues);
        $expectedReviews = count($plan['penalties_pending_review']) + count(array_filter($plan['loans'],
            fn ($loan) => (int) $loan['unverified_penalty_balance_cents'] > 0));
        $check('Penalty review count', $expectedReviews, $reviews->count());
        $check('Contribution amount', (int) $expected['contributions'], $sum($contributions, 'amount'), true);
        $check('Disbursement amount', (int) $expected['principal_disbursed'], $sum($disbursements, 'amount'), true);
        $check('Repayment amount', (int) $expected['repayments'], $sum($repayments, 'amount'), true);
        $check('Allocated repayment principal', (int) $expected['repayments'] - (int) $expected['interest_paid'],
            $sum($allocations, 'principal_amount'), true);
        $check('Allocated repayment interest', (int) $expected['interest_paid'],
            $sum($allocations, 'interest_amount'), true);
        $check('Borrower interest credits', (int) $expected['interest_paid'], $sum($interestCredits, 'amount'), true);
        $check('Mapped historical receipt amount', (int) $expected['contributions'], $sumCents($unallocated, 'amount_cents'), true);
        $check('Original receipt direct-FK remains NULL (split-month safe)', count($plan['contributions']),
            $contributions->filter(fn ($c) => $c->contribution_obligation_id === null)->count());

        // Match each historical loan against the permanent old->new ID map and contract figures.
        $loanMap = DB::table('legacy_record_maps')->where('source_id', $sourceId)
            ->where('source_table', 'loans')->get()->keyBy('source_id_value');
        $contractIssues = [];
        $outstandingByMember = [];
        $expectedOutstanding = 0;
        $expectedPenaltyBalance = 0;
        foreach ($plan['loans'] as $old) {
            $legacyId = (string) $old['legacy_loan_id'];
            $map = $loanMap->get($legacyId);
            $record = $map ? $loansById->get((int) $map->target_id) : null;
            if (
                ! $map || $map->target_table !== 'loans' || ! $record ||
                $this->cents($record->principal_amount) !== (int) $old['principal_cents'] ||
                $this->cents($record->quoted_interest_amount) !== (int) $old['contract_interest_cents'] ||
                $this->cents($record->quoted_total_amount) !== (int) $old['contract_total_cents'] ||
                (int) $record->society_membership_id !== (int) $old['membership_id']
            ) {
                $contractIssues[] = "Legacy loan #{$legacyId} does not match its mapped contract.";
            }
            $due = (int) $old['recorded_outstanding_cents'];
            $expectedOutstanding += $due;
            $expectedPenaltyBalance += (int) $old['unverified_penalty_balance_cents'];
            $member = (int) $old['membership_id'];
            $outstandingByMember[$member] = ($outstandingByMember[$member] ?? 0) + $due;
        }
        $check('Original loan ID mappings', count($plan['loans']), $loanMap->count());
        $check('Mapped contract discrepancies', 0, count($contractIssues));

        // Every source journal must balance, have >= 2 lines and be unique by source/type.
        $journals = DB::table('ledger_transactions')->where('society_id', $societyId)
            ->where(function ($q) use ($originalContributionIds, $originalDisbursementIds, $originalRepaymentIds) {
                $q->where(function ($j) use ($originalContributionIds) {
                    $j->where('source_type', 'contribution_payment')->whereIn('source_id', $originalContributionIds);
                })->orWhere(function ($j) use ($originalDisbursementIds) {
                    $j->where('source_type', 'loan_disbursement')->whereIn('source_id', $originalDisbursementIds);
                })->orWhere(function ($j) use ($originalRepaymentIds) {
                    $j->where('source_type', 'loan_repayment')->whereIn('source_id', $originalRepaymentIds);
                });
            })->get();
        $journalIds = $journals->pluck('id')->all();
        $lines = DB::table('ledger_lines')->whereIn('ledger_transaction_id', $journalIds)->get();
        $byJournal = $lines->groupBy('ledger_transaction_id');
        $imbalances = [];
        $sourceKeys = [];
        foreach ($journals as $journal) {
            $group = $byJournal->get($journal->id, collect());
            $debit = $sum($group, 'debit_amount');
            $credit = $sum($group, 'credit_amount');
            if ($group->count() < 2 || $debit <= 0 || $debit !== $credit) {
                $imbalances[] = $journal->id;
            }
            $key = $journal->source_type . ':' . $journal->source_id;
            if (isset($sourceKeys[$key])) {
                $imbalances[] = $journal->id;
            }
            $sourceKeys[$key] = true;
        }
        $check('Financial ledger journal count', count($plan['contributions']) + count($plan['loans']) + $expectedRepayments,
            $journals->count());
        $check('Unbalanced / duplicate-source journal count', 0, count(array_unique($imbalances)));
        $check('Orphan journal lines', 0, $lines->filter(fn ($l) => ! $journals->contains('id', $l->ledger_transaction_id))->count());

        $accountRows = DB::table('ledger_accounts')->where('society_id', $societyId)->get();
        $accounts = $accountRows->keyBy('id');
        $accountNets = [];
        $bankMovement = 0;
        $unknownAccounts = [];
        foreach ($lines as $line) {
            $account = $accounts->get((int) $line->ledger_account_id);
            if (! $account) {
                $unknownAccounts[] = $line->id;
                continue;
            }
            $code = $account->code;
            $net = $this->cents($line->debit_amount) - $this->cents($line->credit_amount);
            $accountNets[$code] = ($accountNets[$code] ?? 0) + $net;
            if (in_array($code, ['BANK_ACCOUNT', 'CASH_ON_HAND', 'MOBILE_MONEY'], true)) {
                $bankMovement += abs($net);
            }
        }
        $clearing = (int) $expected['contributions'] + (int) $expected['repayments'] - (int) $expected['principal_disbursed'];
        $check('Legacy clearing debit', $clearing, $accountNets['LEGACY_IMPORT_CLEARING'] ?? 0, true);
        $check('Member savings liability (net credit)', (int) $expected['contributions'],
            -($accountNets['MEMBER_SAVINGS'] ?? 0), true);
        $check('Member interest liability (net credit)', (int) $expected['interest_paid'],
            -($accountNets['MEMBER_INTEREST_PAYABLE'] ?? 0), true);
        $check('Loan principal receivable (net debit)',
            (int) $expected['principal_disbursed'] - ((int) $expected['repayments'] - (int) $expected['interest_paid']),
            $accountNets['LOAN_PRINCIPAL_RECEIVABLE'] ?? 0, true);
        $check('Real-bank / cash movements from import', 0, $bankMovement, true);
        $check('Unknown ledger account lines', 0, count($unknownAccounts));
        $check('All member interest belongs to borrowing member', 0, $interestCredits->filter(
            fn ($c) => ! $loansById->has((int) $c->loan_id) ||
                (int) $loansById->get((int) $c->loan_id)->society_membership_id !== (int) $c->beneficiary_membership_id
        )->count());

        $memberMap = DB::table('legacy_record_maps')->where('source_id', $sourceId)
            ->where('source_table', 'members')->where('target_table', 'society_memberships')->get();
        $membershipIds = $memberMap->pluck('target_id')->map(fn ($id) => (int) $id)->all();
        $members = DB::table('society_memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.society_id', $societyId)->whereIn('m.id', $membershipIds)
            ->select('m.id', 'm.member_number', 'u.name')->orderBy('m.id')->get()
            ->map(function ($member) use ($contributions, $interestCredits, $outstandingByMember) {
                $id = (int) $member->id;
                return [
                    'id' => $id, 'number' => $member->member_number, 'name' => $member->name,
                    'savings_cents' => $contributions->where('society_membership_id', $id)
                        ->sum(fn ($c) => $this->cents($c->amount)),
                    'interest_cents' => $interestCredits->where('beneficiary_membership_id', $id)
                        ->sum(fn ($c) => $this->cents($c->amount)),
                    'outstanding_cents' => $outstandingByMember[$id] ?? 0,
                ];
            })->all();
        $check('Mapped legacy membership count', $memberMap->count(), count($members));

        $reviewRows = $reviews->map(fn ($r) => [
            'id' => $r->id, 'source_table' => $r->source_table,
            'source_id' => $r->source_id_value, 'reason' => $r->reason,
            'amount_cents' => (int) $r->amount_cents,
            'status' => $r->resolution_status,
        ])->all();
        $unallocatedRows = $unallocated->map(fn ($r) => [
            'id' => $r->id, 'member_id' => (int) $r->society_membership_id,
            'source_id' => $r->source_transaction_id, 'amount_cents' => (int) $r->amount_cents,
            'paid_on' => $r->paid_on, 'status' => $r->status,
        ])->all();

        // These are close/readiness gates, NOT checks that the original transaction import failed.
        $pendingPenalties = $reviews->where('resolution_status', 'pending')->count();
        // Step 9E.2: a receipt is now fully obligation-linked when every
        // E1 reviewed portion has a valid active obligation link.
        // The original payment's single FK stays NULL for split-month receipts.
        $pendingAllocations = $unallocated->count();
        $obligationLinkIssues = 0;
        $linkedByReceipt = [];
        if (Schema::hasTable('legacy_receipt_obligation_links')) {
            $attributionsById = DB::table('legacy_receipt_period_attributions')
                ->where('society_id', $societyId)->where('cycle_id', $cycleId)
                ->get()->keyBy('id');
            $receiptById = $unallocated->keyBy('id');
            $obligationById = DB::table('contribution_obligations')
                ->where('society_id', $societyId)->where('cycle_id', $cycleId)
                ->get()->keyBy('id');
            $activeLinks = DB::table('legacy_receipt_obligation_links')
                ->where('society_id', $societyId)->where('cycle_id', $cycleId)
                ->whereNull('reversed_at')->get();
            $seenAttributionIds = [];
            foreach ($activeLinks as $link) {
                $attribution = $attributionsById->get((int) $link->legacy_receipt_period_attribution_id);
                $receipt = $receiptById->get((int) $link->contribution_payment_id);
                $obligation = $obligationById->get((int) $link->contribution_obligation_id);
                $aid = (int) $link->legacy_receipt_period_attribution_id;
                if (isset($seenAttributionIds[$aid])) {
                    $obligationLinkIssues++;
                }
                $seenAttributionIds[$aid] = true;
                if (! $attribution || ! $receipt || ! $obligation
                    || $attribution->reversed_at !== null
                    || (int) $attribution->contribution_payment_id !== (int) $link->contribution_payment_id
                    || (int) $attribution->amount_cents !== (int) $link->amount_cents
                    || (int) $receipt->society_membership_id !== (int) $obligation->society_membership_id
                    || substr((string) $attribution->period_start, 0, 10)
                        !== substr((string) $obligation->period_start, 0, 10)) {
                    $obligationLinkIssues++;
                }
                $id = (int) $link->contribution_payment_id;
                $linkedByReceipt[$id] = ($linkedByReceipt[$id] ?? 0) + (int) $link->amount_cents;
            }
            $pendingAllocations = 0;
            foreach ($unallocated as $receipt) {
                $linked = $linkedByReceipt[(int) $receipt->id] ?? 0;
                if ($linked > (int) $receipt->amount_cents) {
                    $obligationLinkIssues++;
                }
                if ($linked !== (int) $receipt->amount_cents) {
                    $pendingAllocations++;
                }
            }
        }
        $check('Historical obligation link inconsistencies', 0, $obligationLinkIssues);
        $unallocatedRows = array_map(function (array $row) use ($linkedByReceipt) {
            $row['status'] = ($linkedByReceipt[(int) $row['id']] ?? 0) === $row['amount_cents']
                ? 'obligation_linked' : 'pending_allocation';
            return $row;
        }, $unallocatedRows);

        // Step 9E.1 tracks evidence-backed period attribution separately from
        // actual contribution obligation settlement; no GL or payment is changed.
        $pendingPeriodEvidence = $unallocated->count();
        $overAttributed = 0;
        if (Schema::hasTable('legacy_receipt_period_attributions')) {
            $activePeriodRows = DB::table('legacy_receipt_period_attributions')
                ->where('society_id', $societyId)->where('cycle_id', $cycleId)
                ->whereNull('reversed_at')->get()->groupBy('contribution_payment_id');
            $pendingPeriodEvidence = 0;
            foreach ($unallocated as $receipt) {
                $amountAttributed = $activePeriodRows->get((int) $receipt->id, collect())
                    ->sum(fn ($row) => (int) $row->amount_cents);
                if ($amountAttributed !== (int) $receipt->amount_cents) {
                    $pendingPeriodEvidence++;
                }
                if ($amountAttributed > (int) $receipt->amount_cents) {
                    $overAttributed++;
                }
            }
        }
        $check('Historical period attribution overallocated receipts', 0, $overAttributed);
        // Independently verify that ordinary reports count the same REAL receipts
        // and reviewed obligations as the historical compliance bridge.
        $bridge = app(\App\Services\Contributions\ContributionObligationCoverage::class)
            ->forCycle($societyId, $cycleId);
        $report = app(\App\Services\Reports\SocietyFinancialReport::class)
            ->build(\App\Models\Society::findOrFail($societyId), $cycleId);
        $bridgeVerified = $report['historical_cycle']
            && (int) $report['contributions_received'] === (int) $expected['contributions']
            && (int) $report['contributions_required'] === (int) $bridge['totals']['required_cents']
            && (int) $report['contributions_outstanding'] === (int) $bridge['totals']['outstanding_cents'];
        $settlement = \Illuminate\Support\Facades\Schema::hasTable('legacy_clearing_reconciliations')
            ? DB::table('legacy_clearing_reconciliations')
                ->where('import_run_id', $run->id)->first()
            : null;
        $clearingVerified = $settlement && $settlement->status === 'posted'
            && DB::table('ledger_transactions')->where('id', $settlement->ledger_transaction_id)
                ->where('source_type', 'legacy_clearing_reconciliation')
                ->where('source_id', $settlement->id)->exists();
        $gates = [
            ['label' => 'Receipts awaiting month-by-month evidence', 'count' => $pendingPeriodEvidence],
            ['label' => 'Unallocated historical contributions', 'count' => $pendingAllocations],
            ['label' => 'Ordinary compliance report bridge verified', 'count' => $bridgeVerified ? 'Yes — matched verified coverage' : 'No — mismatch'],
            ['label' => 'Unresolved historical penalty questions', 'count' => $pendingPenalties],
            ['label' => 'Outstanding contractual loan balances (M)', 'count' => $this->money($expectedOutstanding)],
            ['label' => 'Legacy clearing independently verified', 'count' => $clearingVerified ? 'Yes — reclassified' : 'No — evidence/posting outstanding'],
        ];

        return [
            'run' => $run, 'cycle' => $cycle, 'checks' => $checks,
            'passed' => collect($checks)->every(fn ($r) => $r['passed']),
            'members' => $members, 'review_items' => $reviewRows,
            'unallocated_items' => $unallocatedRows, 'gates' => $gates,
            'pending_penalties' => $pendingPenalties, 'pending_allocations' => $pendingAllocations,
            'open_loan_cents' => $expectedOutstanding,
            'unverified_penalty_balance_cents' => $expectedPenaltyBalance,
            'expected_clearing_cents' => $clearing,
            'contract_issues' => $contractIssues,
            'account_nets' => $accountNets,
            'imported_totals' => $posted,
        ];
    }
}
