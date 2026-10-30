<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Historical Mokhatlo financial importer.
 *
 * --apply is an all-or-nothing SQL transaction. Original cash is posted to a
 * legacy CLEARING asset, never a bank/cash account, pending external proof.
 * Unverified penalty entries are review evidence ONLY, not cash or income.
 *
 * IMPORTANT: This only uses schema fields that it explicitly preflights.
 * Existing accounting code must be prevented from rewriting imported history.
 */
final class ImportLegacyFinancial extends Command
{
    protected $signature = 'mokhatlo:legacy:import
        {batchId=3 : Source snapshot batch ID}
        {--society=1 : Mapped target society}
        {--actor=1 : Existing user who performs this audited import}
        {--apply : Commit after preflight; omit to preview only}
        {--force : Allow --apply when APP_ENV=production}';

    protected $description = 'Import verified historical loans, contributions and repayments with balanced legacy-clearing journals';

    private array $columns = [];
    private array $sourceHashes = [];
    private array $accounts = [];
    private int $sourceId;
    private int $societyId;
    private int $cycleId;
    private int $actorId;
    private string $sourceCode;
    private array $stats = [
        'loans' => 0, 'disbursements' => 0, 'contributions' => 0,
        'repayments' => 0, 'interest_credits' => 0, 'review_items' => 0,
        'contributions_cents' => 0, 'disbursements_cents' => 0,
        'repayments_cents' => 0, 'interest_cents' => 0,
    ];

    public function handle(): int
    {
        try {
            $batchId = (int) $this->argument('batchId');
            $this->societyId = (int) $this->option('society');
            $this->actorId = (int) $this->option('actor');

            if (! Schema::hasTable('legacy_financial_import_runs')) {
                throw new RuntimeException('Run the Step 9C.3 control-table migration first.');
            }

            $batch = DB::table('legacy_import_batches')->find($batchId);
            if (! $batch || ! in_array($batch->status, ['needs_review', 'completed'], true)) {
                throw new RuntimeException('Source snapshot is missing or not ready.');
            }

            $source = DB::table('legacy_import_sources')->find($batch->source_id);
            if (! $source) {
                throw new RuntimeException('Legacy source record was not found.');
            }

            $this->sourceId = (int) $source->id;
            $this->sourceCode = $source->source_code;

            $path = storage_path("app/private/legacy-audits/batch-{$batchId}-financial-plan.enc");
            if (! is_file($path)) {
                throw new RuntimeException("Encrypted financial plan not found: {$path}");
            }

            $encrypted = file_get_contents($path);
            $plain = Crypt::decryptString($encrypted);
            $plan = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
            $planHash = hash('sha256', $plain);

            if (
                ! ($plan['ready_for_financial_import'] ?? false)
                || ($plan['blockers'] ?? []) !== []
                || (int) ($plan['source_batch_id'] ?? 0) !== $batchId
                || (int) ($plan['source_id'] ?? 0) !== $this->sourceId
                || (int) ($plan['society_id'] ?? 0) !== $this->societyId
                || ! hash_equals((string) $batch->file_sha256, (string) ($plan['source_fingerprint'] ?? ''))
            ) {
                throw new RuntimeException('The financial plan is incomplete, blocked, or belongs to a different source.');
            }

            $this->cycleId = (int) ($plan['cycle_id'] ?? 0);

            $existing = DB::table('legacy_financial_import_runs')
                ->where('society_id', $this->societyId)
                ->where('cycle_id', $this->cycleId)
                ->first();

            if ($existing) {
                if (
                    (int) $existing->batch_id === $batchId
                    && hash_equals((string) $existing->plan_sha256, $planHash)
                    && hash_equals((string) $existing->source_fingerprint, (string) $batch->file_sha256)
                ) {
                    $this->info('This historical plan was already imported. No changes were made.');
                    $this->line('Import run ID: ' . $existing->id);
                    return self::SUCCESS;
                }
                throw new RuntimeException('This target cycle already has a different financial import.');
            }

            $this->preflight($plan, $batchId);
            $this->printPlan($plan);

            if (! $this->option('apply')) {
                $this->warn('PREVIEW ONLY: no loan, repayment, contribution or journal was written.');
                $this->info('After backup and review, rerun with --apply.');
                return self::SUCCESS;
            }

            if (app()->environment('production') && ! $this->option('force')) {
                throw new RuntimeException('Production import requires --force and a verified database backup.');
            }

            DB::transaction(function () use ($plan, $batchId, $batch, $planHash) {
                DB::table('societies')->where('id', $this->societyId)->lockForUpdate()->first();
                DB::table('cycles')->where('id', $this->cycleId)->lockForUpdate()->first();
                // Locking is not a substitute for disabling other financial writers.
                // Recheck under the lock before ANY inserts.
                $this->assertEmptyDestination();
                $this->loadAccounts();

                $loanIds = [];
                foreach ($plan['loans'] as $loan) {
                    $oldId = (string) $loan['legacy_loan_id'];
                    $disbursement = $loan['disbursements'][0];
                    $newId = $this->insertLoan($loan, $disbursement, $oldId);
                    $loanIds[$oldId] = $newId;
                    $this->map('loans', $oldId, 'loans', $newId);

                    $disbId = $this->insertRow('loan_disbursements', [
                        'society_id' => $this->societyId, 'loan_id' => $newId,
                        'amount' => $this->money((int) $disbursement['amount_cents']),
                        'disbursed_on' => $disbursement['date'],
                        'payment_method' => 'legacy_import',
                        'payment_reference' => 'LEGACY-DISB-' . $oldId,
                        'notes' => 'Imported historical disbursement; source transaction #'
                            . $disbursement['legacy_transaction_id'] . '. Clearing account, not verified bank cash.',
                        'idempotency_key' => (string) Str::uuid(),
                        'recorded_by' => $this->actorId,
                    ]);
                    $this->map('transactions', (string) $disbursement['legacy_transaction_id'], 'loan_disbursements', $disbId);
                    $this->journal('loan_disbursement', $disbId, $disbursement['date'],
                        'Historical loan disbursement #' . $oldId, [
                            [$this->accounts['principal_receivable'], (int) $disbursement['amount_cents'], 0],
                            [$this->accounts['clearing'], 0, (int) $disbursement['amount_cents']],
                        ]);
                    $this->stats['disbursements']++;
                    $this->stats['disbursements_cents'] += (int) $disbursement['amount_cents'];

                    $installmentId = $this->insertInstallment($loan, $newId);

                    foreach ($loan['repayments'] as $repayment) {
                        $this->insertRepayment($loan, $newId, $installmentId, $repayment);
                    }
                    $this->stats['loans']++;
                }

                foreach ($plan['contributions'] as $receipt) {
                    $id = $this->insertRow('contribution_payments', [
                        'society_id' => $this->societyId,
                        'cycle_id' => $this->cycleId,
                        'society_membership_id' => (int) $receipt['membership_id'],
                        'contribution_obligation_id' => null, // Intentionally UNALLOCATED historical receipt.
                        'amount' => $this->money((int) $receipt['amount_cents']),
                        'paid_on' => $receipt['date'],
                        'payment_method' => 'legacy_import',
                        'payment_reference' => 'LEGACY-CONTRIB-' . $receipt['legacy_transaction_id'],
                        'notes' => 'Historical receipt; obligation period not established. Original transaction #'
                            . $receipt['legacy_transaction_id'],
                        'idempotency_key' => (string) Str::uuid(),
                        'recorded_by' => $this->actorId,
                    ]);
                    $this->map('transactions', (string) $receipt['legacy_transaction_id'], 'contribution_payments', $id);
                    $this->journal('contribution_payment', $id, $receipt['date'],
                        'Historical saving receipt #' . $receipt['legacy_transaction_id'], [
                            [$this->accounts['clearing'], (int) $receipt['amount_cents'], 0],
                            [$this->accounts['savings_payable'], 0, (int) $receipt['amount_cents']],
                        ]);
                    $this->stats['contributions']++;
                    $this->stats['contributions_cents'] += (int) $receipt['amount_cents'];
                }

                // The ledger is balanced but cash is UNVERIFIED: it stays in clearing.
                $this->assertExpectedTotals($plan);
                $runId = $this->insertRow('legacy_financial_import_runs', [
                    'batch_id' => $batchId,
                    'society_id' => $this->societyId,
                    'cycle_id' => $this->cycleId,
                    'imported_by' => $this->actorId,
                    'source_fingerprint' => $batch->file_sha256,
                    'plan_sha256' => $planHash,
                    'posted_totals' => json_encode($this->stats, JSON_THROW_ON_ERROR),
                    'imported_at' => now(),
                ]);

                foreach ($plan['penalties_pending_review'] as $row) {
                    $id = $this->insertRow('legacy_financial_review_items', [
                        'legacy_financial_import_run_id' => $runId,
                        'source_table' => 'transactions',
                        'source_id_value' => (string) $row['legacy_transaction_id'],
                        'reason' => 'penalty_collection_not_proven',
                        'amount_cents' => (int) $row['amount_cents'],
                        'loan_id' => isset($loanIds[(string) ($row['legacy_loan_id'] ?? '')])
                            ? $loanIds[(string) $row['legacy_loan_id']] : null,
                        'society_membership_id' => (int) $row['membership_id'],
                    ]);
                    $this->map('transactions', (string) $row['legacy_transaction_id'], 'legacy_financial_review_items', $id);
                    $this->stats['review_items']++;
                }

                foreach ($plan['loans'] as $loan) {
                    $penalty = (int) $loan['unverified_penalty_balance_cents'];
                    if ($penalty <= 0) {
                        continue;
                    }
                    $this->insertRow('legacy_financial_review_items', [
                        'legacy_financial_import_run_id' => $runId,
                        'source_table' => 'loans',
                        'source_id_value' => (string) $loan['legacy_loan_id'],
                        'reason' => 'outstanding_penalty_unverified',
                        'amount_cents' => $penalty,
                        'loan_id' => $loanIds[(string) $loan['legacy_loan_id']],
                        'society_membership_id' => (int) $loan['membership_id'],
                    ]);
                    $this->stats['review_items']++;
                }

                DB::table('legacy_financial_import_runs')->where('id', $runId)
                    ->update(['posted_totals' => json_encode($this->stats, JSON_THROW_ON_ERROR)]);
                $this->assertClearingBalanceForCycle($plan);
                // Every source loan and financial transaction must have an ID map.
                $expected = DB::table('legacy_import_staged_rows')
                    ->where('batch_id', $batchId)
                    ->whereIn('source_table', ['loans', 'transactions'])
                    ->count();
                $actual = DB::table('legacy_record_maps')
                    ->where('source_id', $this->sourceId)
                    ->whereIn('source_table', ['loans', 'transactions'])
                    ->count();
                if ($expected !== $actual) {
                    throw new RuntimeException("Financial ID mapping count mismatch: {$actual} / {$expected}.");
                }
            }, 3);

            $this->info('Financial import committed successfully.');
            $this->printStats();
            $this->warn('Legacy clearing is NOT verified bank cash. Penalty review items remain unresolved.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());
            $this->warn('No partial import is expected: the posting operation uses one DB transaction.');
            return self::FAILURE;
        }
    }

    private function preflight(array $plan, int $batchId): void
    {
        foreach (['loans','loan_installments','loan_disbursements','loan_repayments',
            'loan_repayment_allocations','loan_interest_credits','contribution_payments',
            'ledger_accounts','ledger_transactions','ledger_lines','legacy_record_maps',
            'legacy_financial_import_runs','legacy_financial_review_items'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Missing table: {$table}");
            }
            $this->columns[$table] = collect(Schema::getColumns($table))->keyBy('name')->all();
        }

        $required = [
            'loans' => ['request_key','society_id','cycle_id','society_membership_id','loan_number',
                'principal_amount','term_months','interest_rate_percent','interest_method',
                'interest_basis','policy_version','quoted_interest_amount','quoted_total_amount',
                'status','submitted_at','submitted_by','disbursed_at','matures_on','legacy_source','legacy_id'],
            'loan_installments' => ['loan_id','society_id','due_date','total_due'],
            'loan_disbursements' => ['society_id','loan_id','amount','disbursed_on','payment_method',
                'idempotency_key','recorded_by'],
            'loan_repayments' => ['society_id','loan_id','amount','paid_on','payment_method',
                'idempotency_key','recorded_by'],
            'loan_repayment_allocations' => ['loan_id','loan_repayment_id','loan_installment_id',
                'principal_amount','interest_amount'],
            'loan_interest_credits' => ['society_id','loan_id','loan_repayment_id',
                'beneficiary_membership_id','amount'],
            'contribution_payments' => ['society_id','cycle_id','society_membership_id',
                'contribution_obligation_id','amount','paid_on','payment_method','idempotency_key','recorded_by'],
            'ledger_accounts' => ['society_id','code','name','type','is_active'],
            'ledger_transactions' => ['society_id','cycle_id','source_type','source_id',
                'posting_date','description','created_by'],
            'ledger_lines' => ['ledger_transaction_id','ledger_account_id','debit_amount','credit_amount'],
        ];
        foreach ($required as $table => $fields) {
            foreach ($fields as $field) {
                if (! isset($this->columns[$table][$field])) {
                    throw new RuntimeException("Target schema mismatch: {$table}.{$field} is missing.");
                }
            }
        }

        if (! ($this->columns['contribution_payments']['contribution_obligation_id']['nullable'] ?? false)) {
            throw new RuntimeException('contribution_obligation_id is NOT NULL. Historical periods are unknown; do not invent obligations. Add a reviewed unallocated-receipt pathway before proceeding.');
        }

        $loanNumberField = isset($this->columns['loan_installments']['installment_no'])
            ? 'installment_no' : (isset($this->columns['loan_installments']['installment_number']) ? 'installment_number' : null);
        $principalField = isset($this->columns['loan_installments']['principal_due'])
            ? 'principal_due' : (isset($this->columns['loan_installments']['principal_amount']) ? 'principal_amount' : null);
        $interestField = isset($this->columns['loan_installments']['interest_due'])
            ? 'interest_due' : (isset($this->columns['loan_installments']['interest_amount']) ? 'interest_amount' : null);
        if (! $loanNumberField || ! $principalField || ! $interestField) {
            throw new RuntimeException('Unsupported loan_installments schema: expected installment_no/number, principal_due/amount and interest_due/amount.');
        }
        $this->installmentFields = compact('loanNumberField','principalField','interestField');

        $cycle = DB::table('cycles')->where('society_id', $this->societyId)->find($this->cycleId);
        if (! $cycle || $cycle->status !== 'draft') {
            throw new RuntimeException('The mapped historical cycle must exist and remain draft before import.');
        }
        $user = DB::table('society_memberships')
            ->where('society_id', $this->societyId)
            ->where('user_id', $this->actorId)->first();
        if (! $user || $user->status !== 'active') {
            throw new RuntimeException('The importing actor must be an active member of the mapped society.');
        }
        if (! DB::table('societies')->where('id',$this->societyId)->exists()) {
            throw new RuntimeException('Target society not found.');
        }
        $this->assertEmptyDestination();

        foreach ($plan['loans'] as $loan) {
            if (count($loan['disbursements']) !== 1) {
                throw new RuntimeException('Loan #' . $loan['legacy_loan_id'] . ' has ambiguous disbursements.');
            }
            $oldLoan = (string) $loan['legacy_loan_id'];
            $this->assertMapping('members', (int) $loan['membership_id']);
            if ((int) $loan['principal_cents'] <= 0) {
                throw new RuntimeException("Loan #{$oldLoan} has invalid principal.");
            }
            $interestTotal = 0;
            $principalTotal = 0;
            foreach ($loan['repayments'] as $repayment) {
                if ((int) $repayment['amount_cents'] !== (int) $repayment['interest_cents'] + (int) $repayment['principal_cents']) {
                    throw new RuntimeException("Loan #{$oldLoan} has an unbalanced repayment allocation.");
                }
                $interestTotal += (int) $repayment['interest_cents'];
                $principalTotal += (int) $repayment['principal_cents'];
            }
            if ($principalTotal > (int) $loan['principal_cents'] || $interestTotal > (int) $loan['contract_interest_cents']) {
                throw new RuntimeException("Loan #{$oldLoan} has an over-allocated repayment.");
            }
            $baseRemaining = (int) $loan['principal_cents'] + (int) $loan['contract_interest_cents'] - $principalTotal - $interestTotal;
            if ($baseRemaining + (int) $loan['unverified_penalty_balance_cents'] !== (int) $loan['recorded_outstanding_cents']) {
                throw new RuntimeException("Loan #{$oldLoan} outstanding balance does not reconcile.");
            }
        }
        foreach ($plan['contributions'] as $payment) {
            $this->assertMapping('members', (int) $payment['membership_id']);
        }

        $this->loadAccounts(true);
        $this->verifyInsertShapes();
        $this->loadSourceHashes($batchId);
    }

    private array $installmentFields = [];

    private function assertMapping(string $type, int $membershipId): void
    {
        if (! DB::table('legacy_record_maps')->where('source_id', $this->sourceId)
            ->where('source_table', $type)->where('target_table', 'society_memberships')
            ->where('target_id', $membershipId)->exists()) {
            throw new RuntimeException("Missing original member mapping to membership #{$membershipId}.");
        }
    }

    private function assertEmptyDestination(): void
    {
        if (DB::table('legacy_financial_import_runs')->where('society_id',$this->societyId)
            ->where('cycle_id',$this->cycleId)->exists()) {
            throw new RuntimeException('This cycle already has a financial import run.');
        }
        foreach (['loans','contribution_payments','ledger_transactions'] as $table) {
            if (DB::table($table)->where('society_id',$this->societyId)
                ->where('cycle_id',$this->cycleId)->exists()) {
                throw new RuntimeException("Destination is not empty: {$table} already has records for this cycle.");
            }
        }
    }

    private function loadAccounts(bool $preflightOnly = false): void
    {
        $types = ['savings_payable'=>'liability','principal_receivable'=>'asset','interest_payable'=>'liability'];
        foreach ($types as $key=>$type) {
            $code = config("legacy_financial_import.accounts.{$key}");
            $account = DB::table('ledger_accounts')->where('society_id',$this->societyId)
                ->where('code',$code)->first();
            if (! $account || $account->type !== $type || ! $account->is_active) {
                $allCodes = DB::table('ledger_accounts')->where('society_id',$this->societyId)->pluck('code')->implode(', ');
                throw new RuntimeException("Missing {$type} account code '{$code}' for {$key}. Available: {$allCodes}. Change ONLY the import config to match your actual account purpose.");
            }
            $this->accounts[$key] = (int) $account->id;
        }
        $code = config('legacy_financial_import.accounts.clearing');
        $clearing = DB::table('ledger_accounts')->where('society_id',$this->societyId)
            ->where('code',$code)->first();
        if ($clearing && ($clearing->type !== 'asset' || ! $clearing->is_active)) {
            throw new RuntimeException('Existing LEGACY_IMPORT_CLEARING has wrong account type/status.');
        }
        if (! $clearing && $preflightOnly) {
            $this->info('Legacy clearing asset account will be created on --apply.');
        }
        if (! $clearing && ! $preflightOnly) {
            $id = $this->insertRow('ledger_accounts', [
                'society_id'=>$this->societyId, 'code'=>$code,
                'name'=>'Legacy Import Clearing (unverified opening cash)',
                'type'=>'asset','is_active'=>true,
            ]);
            $this->accounts['clearing'] = $id;
        } elseif ($clearing) {
            $this->accounts['clearing'] = (int) $clearing->id;
            if (DB::table('ledger_lines')->where('ledger_account_id',$clearing->id)->exists()) {
                throw new RuntimeException('Legacy clearing account already has postings; use a fresh clearing account after reviewing its history.');
            }
        }
    }

    private function verifyInsertShapes(): void
    {
        $loanExample = [
            'request_key'=>1,'society_id'=>1,'cycle_id'=>1,'society_membership_id'=>1,'loan_number'=>1,
            'principal_amount'=>1,'purpose'=>1,'term_months'=>1,'interest_rate_percent'=>1,
            'interest_method'=>1,'interest_basis'=>1,'policy_version'=>1,
            'quoted_interest_amount'=>1,'quoted_total_amount'=>1,'status'=>1,
            'submitted_at'=>1,'submitted_by'=>1,'disbursed_at'=>1,'settled_at'=>1,
            'matures_on'=>1,'legacy_source'=>1,'legacy_id'=>1,'created_at'=>1,'updated_at'=>1,
        ];
        $number = $this->installmentFields['loanNumberField'];
        $principal = $this->installmentFields['principalField'];
        $interest = $this->installmentFields['interestField'];
        $shapes = [
            'loans'=>$loanExample,
            'loan_installments'=>['society_id'=>1,'loan_id'=>1,$number=>1,'due_date'=>1,$principal=>1,$interest=>1,'total_due'=>1,'status'=>1,'created_at'=>1,'updated_at'=>1],
            'loan_disbursements'=>['society_id'=>1,'loan_id'=>1,'amount'=>1,'disbursed_on'=>1,'payment_method'=>1,'payment_reference'=>1,'notes'=>1,'idempotency_key'=>1,'recorded_by'=>1,'created_at'=>1,'updated_at'=>1],
            'loan_repayments'=>['society_id'=>1,'loan_id'=>1,'amount'=>1,'paid_on'=>1,'payment_method'=>1,'payment_reference'=>1,'notes'=>1,'idempotency_key'=>1,'recorded_by'=>1,'created_at'=>1,'updated_at'=>1],
            'loan_repayment_allocations'=>['loan_id'=>1,'loan_repayment_id'=>1,'loan_installment_id'=>1,'principal_amount'=>1,'interest_amount'=>1,'created_at'=>1,'updated_at'=>1],
            'loan_interest_credits'=>['society_id'=>1,'loan_id'=>1,'loan_repayment_id'=>1,'beneficiary_membership_id'=>1,'amount'=>1,'created_at'=>1,'updated_at'=>1],
            'contribution_payments'=>['society_id'=>1,'cycle_id'=>1,'society_membership_id'=>1,'contribution_obligation_id'=>null,'amount'=>1,'paid_on'=>1,'payment_method'=>1,'payment_reference'=>1,'notes'=>1,'idempotency_key'=>1,'recorded_by'=>1,'created_at'=>1,'updated_at'=>1],
            'ledger_transactions'=>['society_id'=>1,'cycle_id'=>1,'source_type'=>1,'source_id'=>1,'posting_date'=>1,'description'=>1,'created_by'=>1,'created_at'=>1,'updated_at'=>1],
            'ledger_lines'=>['ledger_transaction_id'=>1,'ledger_account_id'=>1,'debit_amount'=>1,'credit_amount'=>1,'created_at'=>1,'updated_at'=>1],
        ];
        foreach ($shapes as $table=>$shape) {
            foreach ($this->columns[$table] as $name=>$column) {
                if ($name === 'id' || array_key_exists($name,$shape)) {
                    continue;
                }
                if (! ($column['nullable'] ?? false) && ($column['default'] ?? null) === null
                    && ! ($column['auto_increment'] ?? false)) {
                    throw new RuntimeException("Unknown mandatory column {$table}.{$name}. Review the live schema before importing.");
                }
            }
        }
    }

    private function loadSourceHashes(int $batchId): void
    {
        $items = DB::table('legacy_import_staged_rows')->where('batch_id',$batchId)
            ->whereIn('source_table',['loans','transactions'])->get();
        foreach ($items as $item) {
            $this->sourceHashes[$item->source_table . ':' . $item->source_id_value] = $item->source_row_sha256;
        }
    }

    private function insertLoan(array $loan, array $disbursement, string $oldId): int
    {
        $principal = (int) $loan['principal_cents'];
        $interest = (int) $loan['contract_interest_cents'];
        $issue = $loan['issue_date'] ?: $disbursement['date'];
        $due = $loan['due_date'];
        if (!$issue || !$due || strtotime($due) < strtotime($issue)) {
            throw new RuntimeException("Missing or invalid contract dates for loan #{$oldId}.");
        }
        $a = new \DateTimeImmutable($issue);
        $b = new \DateTimeImmutable($due);
        $d = $a->diff($b);
        $months = max(1, $d->y * 12 + $d->m + ($d->d > 0 ? 1 : 0));
        $rate = number_format($interest * 100 / $principal, 5, '.', '');
        $fullyPaid = (int) $loan['recorded_outstanding_cents'] === 0;
        $lastRepayment = $fullyPaid && $loan['repayments'] !== []
            ? max(array_column($loan['repayments'],'date')) : null;
        return $this->insertRow('loans', [
            'request_key'=>(string) Str::uuid(),
            'society_id'=>$this->societyId,'cycle_id'=>$this->cycleId,
            'society_membership_id'=>(int) $loan['membership_id'],
            'loan_number'=>'LEG-'.$this->cycleId.'-'.$oldId,
            'principal_amount'=>$this->money($principal),
            'purpose'=>'Historical loan #'.$oldId.' (imported contract; term_months derived from issue/due dates)',
            'term_months'=>$months,'interest_rate_percent'=>$rate,
            'interest_method'=>'flat','interest_basis'=>'per_term','policy_version'=>0,
            'quoted_interest_amount'=>$this->money($interest),
            'quoted_total_amount'=>$this->money((int) $loan['contract_total_cents']),
            'status'=>$fullyPaid ? 'settled' : 'disbursed',
            'submitted_at'=>$issue.' 00:00:00','submitted_by'=>$this->actorId,
            'disbursed_at'=>$disbursement['date'].' 00:00:00',
            'settled_at'=>$lastRepayment ? $lastRepayment.' 00:00:00' : null,
            'matures_on'=>$due,'legacy_source'=>$this->sourceCode,'legacy_id'=>$oldId,
        ]);
    }

    private function insertInstallment(array $loan, int $loanId): int
    {
        $row = [
            'society_id'=>$this->societyId,'loan_id'=>$loanId,
            $this->installmentFields['loanNumberField']=>1,
            $this->installmentFields['principalField']=>$this->money((int) $loan['principal_cents']),
            $this->installmentFields['interestField']=>$this->money((int) $loan['contract_interest_cents']),
            'total_due'=>$this->money((int) $loan['contract_total_cents']),
            'due_date'=>$loan['due_date'],
            'status'=>((int) $loan['recorded_outstanding_cents'] === 0 ? 'paid':'pending'),
        ];
        return $this->insertRow('loan_installments',$row);
    }

    private function insertRepayment(array $loan, int $loanId, int $installmentId, array $repayment): void
    {
        $principal = (int) $repayment['principal_cents'];
        $interest = (int) $repayment['interest_cents'];
        $amount = (int) $repayment['amount_cents'];
        $oldTx = (string) $repayment['legacy_transaction_id'];
        $id = $this->insertRow('loan_repayments', [
            'society_id'=>$this->societyId,'loan_id'=>$loanId,
            'amount'=>$this->money($amount),'paid_on'=>$repayment['date'],
            'payment_method'=>'legacy_import',
            'payment_reference'=>'LEGACY-REP-'.$oldTx,
            'notes'=>'Legacy repayment #'.$oldTx.'; interest is a component, not additional receipt.',
            'idempotency_key'=>(string) Str::uuid(),
            'recorded_by'=>$this->actorId,
        ]);
        $this->map('transactions',$oldTx,'loan_repayments',$id);
        $this->insertRow('loan_repayment_allocations', [
            'loan_id'=>$loanId,'loan_repayment_id'=>$id,
            'loan_installment_id'=>$installmentId,
            'principal_amount'=>$this->money($principal),
            'interest_amount'=>$this->money($interest),
        ]);
        if ($interest > 0) {
            $creditId = $this->insertRow('loan_interest_credits', [
                'society_id'=>$this->societyId,'loan_id'=>$loanId,
                'loan_repayment_id'=>$id,
                'beneficiary_membership_id'=>(int) $loan['membership_id'],
                'amount'=>$this->money($interest),
            ]);
            foreach ($repayment['interest_source_ids'] as $oldInterestTx) {
                $this->map('transactions',(string) $oldInterestTx,'loan_interest_credits',$creditId);
            }
            $this->stats['interest_credits']++;
            $this->stats['interest_cents'] += $interest;
        }
        $lines = [[$this->accounts['clearing'],$amount,0]];
        if ($principal > 0) {
            $lines[] = [$this->accounts['principal_receivable'],0,$principal];
        }
        if ($interest > 0) {
            $lines[] = [$this->accounts['interest_payable'],0,$interest];
        }
        $this->journal('loan_repayment',$id,$repayment['date'],
            'Historical loan repayment #'.$oldTx,$lines);
        $this->stats['repayments']++;
        $this->stats['repayments_cents'] += $amount;
    }

    private function journal(string $sourceType, int $sourceId, string $date, string $description, array $lines): void
    {
        $debits = array_sum(array_column($lines,1));
        $credits = array_sum(array_column($lines,2));
        if ($debits <= 0 || $debits !== $credits) {
            throw new RuntimeException('Unbalanced import journal: '.$description);
        }
        $id = $this->insertRow('ledger_transactions',[
            'society_id'=>$this->societyId,'cycle_id'=>$this->cycleId,
            'source_type'=>$sourceType,'source_id'=>$sourceId,
            'posting_date'=>$date,'description'=>$description,
            'created_by'=>$this->actorId,
        ]);
        foreach ($lines as [$account,$debit,$credit]) {
            $this->insertRow('ledger_lines',[
                'ledger_transaction_id'=>$id,
                'ledger_account_id'=>$account,
                'debit_amount'=>$this->money($debit),
                'credit_amount'=>$this->money($credit),
            ]);
        }
    }

    private function map(string $sourceTable, string $oldId, string $targetTable, int $targetId): void
    {
        $hash = $this->sourceHashes[$sourceTable.':'.$oldId] ?? null;
        if (!$hash) {
            throw new RuntimeException("Missing staged hash for {$sourceTable} #{$oldId}.");
        }
        if (DB::table('legacy_record_maps')->where('source_id',$this->sourceId)
            ->where('source_table',$sourceTable)->where('source_id_value',$oldId)->exists()) {
            throw new RuntimeException("Legacy {$sourceTable} #{$oldId} has already been mapped. Refusing duplication.");
        }
        $this->insertRow('legacy_record_maps',[
            'source_id'=>$this->sourceId,'source_table'=>$sourceTable,
            'source_id_value'=>$oldId,'target_table'=>$targetTable,
            'target_id'=>$targetId,'source_row_sha256'=>$hash,
        ]);
    }

    private function insertRow(string $table, array $row): int
    {
        if (!isset($this->columns[$table])) {
            $this->columns[$table] = collect(Schema::getColumns($table))->keyBy('name')->all();
        }
        $names = $this->columns[$table];
        $data = array_intersect_key($row, $names);
        if (isset($names['created_at'])) {
            $data['created_at'] = now();
        }
        if (isset($names['updated_at'])) {
            $data['updated_at'] = now();
        }
        return (int) DB::table($table)->insertGetId($data);
    }

    private function assertExpectedTotals(array $plan): void
    {
        $pairs = [
            'contributions'=>'contributions_cents',
            'principal_disbursed'=>'disbursements_cents',
            'repayments'=>'repayments_cents',
            'interest_paid'=>'interest_cents',
        ];
        foreach ($pairs as $source=>$target) {
            if ((int) $plan['totals_cents'][$source] !== $this->stats[$target]) {
                throw new RuntimeException("Imported {$source} total does not match approved source plan.");
            }
        }
        if ($this->stats['loans'] !== count($plan['loans'])
            || $this->stats['contributions'] !== count($plan['contributions'])) {
            throw new RuntimeException('Import row count mismatch.');
        }
    }

    private function assertClearingBalanceForCycle(array $plan): void
    {
        $expected = (int) $plan['totals_cents']['contributions']
            + (int) $plan['totals_cents']['repayments']
            - (int) $plan['totals_cents']['principal_disbursed'];
        $lines = DB::table('ledger_transactions as t')
            ->join('ledger_lines as l','l.ledger_transaction_id','=','t.id')
            ->where('t.society_id',$this->societyId)
            ->where('t.cycle_id',$this->cycleId)
            ->where('l.ledger_account_id',$this->accounts['clearing'])
            ->get(['l.debit_amount','l.credit_amount']);
        $net = 0;
        foreach ($lines as $l) {
            $net += $this->parseMoney($l->debit_amount) - $this->parseMoney($l->credit_amount);
        }
        if ($net !== $expected) {
            throw new RuntimeException('Legacy clearing account does not reconcile to source cash movement.');
        }
    }

    private function parseMoney($value): int
    {
        $s = (string) $value;
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/',$s)) {
            throw new RuntimeException('Invalid posted ledger decimal: '.$s);
        }
        $negative = str_starts_with($s,'-');
        [$whole,$frac] = array_pad(explode('.',ltrim($s,'-'),2),2,'');
        $cents = ((int) $whole)*100 + (int) str_pad($frac,2,'0');
        return $negative ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . intdiv(abs($cents),100)
            . '.' . str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT);
    }

    private function printPlan(array $plan): void
    {
        $t = $plan['totals_cents'];
        $this->table(['Category','Source total'],[
            ['Loans',count($plan['loans'])],
            ['Loan disbursements','M '.$this->money((int)$t['principal_disbursed'])],
            ['Contributions (unallocated)','M '.$this->money((int)$t['contributions'])],
            ['Loan repayments','M '.$this->money((int)$t['repayments'])],
            ['Interest INCLUDED in repayments','M '.$this->money((int)$t['interest_paid'])],
            ['Pending penalty collection evidence',count($plan['penalties_pending_review'])],
            ['Unverified clearing debit (not bank cash)','M '.$this->money(
                (int)$t['contributions']+(int)$t['repayments']-(int)$t['principal_disbursed'])],
        ]);
    }

    private function printStats(): void
    {
        $this->table(['Record','Imported'],array_map(
            fn($key,$value)=>[$key,(string)$value],
            array_keys($this->stats),array_values($this->stats)
        ));
    }
}
