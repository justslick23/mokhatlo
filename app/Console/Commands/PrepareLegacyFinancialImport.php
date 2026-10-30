<?php

namespace App\Console\Commands;

use App\Models\LedgerTransaction;
use App\Services\Legacy\LegacyTransactionType;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PrepareLegacyFinancialImport extends Command
{
    protected $signature = 'mokhatlo:legacy:financial-plan
        {batchId=3 : Legacy snapshot batch ID}
        {--society=1 : Existing target society ID}';

    protected $description =
        'Prepare historical financial import plan without writing live transactions';

    private function cents($value): int
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('Missing monetary amount.');
        }

        return Money::toCents((string) $value);
    }

    private function loadRows(int $batchId): array
    {
        $tables = [
            'societies',
            'members',
            'cycles',
            'loans',
            'transactions',
        ];

        $result = array_fill_keys($tables, []);

        DB::table('legacy_import_staged_rows')
            ->where('batch_id', $batchId)
            ->whereIn('source_table', $tables)
            ->orderBy('id')
            ->chunkById(200, function ($entries) use (&$result) {
                foreach ($entries as $entry) {
                    $plain = Crypt::decryptString(
                        $entry->payload_encrypted
                    );

                    if (! hash_equals(
                        (string) $entry->source_row_sha256,
                        hash('sha256', $plain)
                    )) {
                        throw new RuntimeException(
                            'Corrupt historical record: ' .
                            $entry->source_table . '#' .
                            $entry->source_id_value
                        );
                    }

                    $row = json_decode(
                        $plain,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );

                    if (
                        (string) ($row['id'] ?? '')
                        !== (string) $entry->source_id_value
                    ) {
                        throw new RuntimeException(
                            'Legacy record ID mismatch.'
                        );
                    }

                    $result[$entry->source_table][
                        (string) $entry->source_id_value
                    ] = $row;
                }
            });

        return $result;
    }

    private function mapping(
        array $maps,
        string $table,
        string $oldId,
        string $expectedTarget
    ): int {
        $key = $table . ':' . $oldId;
        $map = $maps[$key] ?? null;

        if (
            ! $map
            || $map->target_table !== $expectedTarget
        ) {
            throw new RuntimeException(
                "Missing or incorrect mapping for {$key}."
            );
        }

        return (int) $map->target_id;
    }

    public function handle(): int
    {
        try {
            $batchId = (int) $this->argument('batchId');
            $societyId = (int) $this->option('society');

            $batch = DB::table('legacy_import_batches')
                ->find($batchId);

            if (
                ! $batch
                || ! in_array(
                    $batch->status,
                    ['completed', 'needs_review'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Legacy source snapshot is not ready.'
                );
            }

            $source = DB::table('legacy_import_sources')
                ->find($batch->source_id);

            if (! $source) {
                throw new RuntimeException(
                    'Legacy import source is missing.'
                );
            }

            $society = DB::table('societies')
                ->find($societyId);

            if (! $society) {
                throw new RuntimeException(
                    'Target society does not exist.'
                );
            }

            $rows = $this->loadRows($batchId);

            $expectedCounts = json_decode(
                $batch->counts ?? '{}',
                true
            )['tables'] ?? [];

            foreach ($rows as $table => $items) {
                if (
                    ! isset($expectedCounts[$table])
                    || count($items)
                        !== (int) $expectedCounts[$table]
                ) {
                    throw new RuntimeException(
                        "Incomplete staged records in {$table}."
                    );
                }
            }

            $maps = DB::table('legacy_record_maps')
                ->where('source_id', $batch->source_id)
                ->get()
                ->keyBy(
                    fn ($row) =>
                        $row->source_table . ':' .
                        $row->source_id_value
                )
                ->all();

            if (
                count($rows['societies']) !== 1
                || count($rows['cycles']) !== 1
            ) {
                throw new RuntimeException(
                    'Expected one historical society and cycle.'
                );
            }

            $oldSociety = reset($rows['societies']);
            $oldCycle = reset($rows['cycles']);

            $mappedSocietyId = $this->mapping(
                $maps,
                'societies',
                (string) $oldSociety['id'],
                'societies'
            );

            $cycleId = $this->mapping(
                $maps,
                'cycles',
                (string) $oldCycle['id'],
                'cycles'
            );

            if ($mappedSocietyId !== $societyId) {
                throw new RuntimeException(
                    'The historical source maps to another society.'
                );
            }

            $cycle = DB::table('cycles')
                ->where('id', $cycleId)
                ->where('society_id', $societyId)
                ->first();

            if (! $cycle) {
                throw new RuntimeException(
                    'Mapped historical cycle does not exist.'
                );
            }

            if (
                substr((string) $cycle->start_date, 0, 10)
                    !== $oldCycle['start_date']
                || substr((string) $cycle->end_date, 0, 10)
                    !== $oldCycle['end_date']
            ) {
                throw new RuntimeException(
                    'Historical cycle dates differ from the target.'
                );
            }

            $memberIds = [];

            foreach ($rows['members'] as $oldId => $member) {
                if (
                    (string) $member['society_id']
                    !== (string) $oldSociety['id']
                ) {
                    throw new RuntimeException(
                        "Legacy member #{$oldId} belongs to another society."
                    );
                }

                $newId = $this->mapping(
                    $maps,
                    'members',
                    (string) $oldId,
                    'society_memberships'
                );

                $exists = DB::table('society_memberships')
                    ->where('id', $newId)
                    ->where('society_id', $societyId)
                    ->exists();

                if (! $exists) {
                    throw new RuntimeException(
                        "Target membership #{$newId} was not found."
                    );
                }

                $memberIds[(string) $oldId] = $newId;
            }

            $plan = [
                'source_batch_id' => $batchId,
                'source_fingerprint' => $batch->file_sha256,
                'source_id' => (int) $batch->source_id,
                'society_id' => $societyId,
                'cycle_id' => $cycleId,
                'loans' => [],
                'contributions' => [],
                'penalties_pending_review' => [],
                'totals_cents' => [
                    'contributions' => 0,
                    'principal_disbursed' => 0,
                    'repayments' => 0,
                    'interest_paid' => 0,
                    'penalties_unverified' => 0,
                ],
            ];

            $loanEvents = [];
            $issues = [];

            foreach ($rows['transactions'] as $oldId => $tx) {
                $kind = LegacyTransactionType::classify(
                    $tx['type'] ?? null
                );

                $amount = $this->cents($tx['amount'] ?? null);
                $date = substr(
                    (string) ($tx['transaction_date'] ?? ''),
                    0,
                    10
                );

                if (
                    $amount <= 0
                    || ! preg_match(
                        '/^\d{4}-\d{2}-\d{2}$/',
                        $date
                    )
                    || ! checkdate(
                        (int) substr($date, 5, 2),
                        (int) substr($date, 8, 2),
                        (int) substr($date, 0, 4)
                    )
                ) {
                    throw new RuntimeException(
                        "Invalid amount/date in transaction #{$oldId}."
                    );
                }

                if (
                    (string) ($tx['society_id'] ?? '')
                    !== (string) $oldSociety['id']
                    || (string) ($tx['cycle_id'] ?? '')
                    !== (string) $oldCycle['id']
                ) {
                    throw new RuntimeException(
                        "Transaction #{$oldId} has a society/cycle mismatch."
                    );
                }

                $memberId = (string) ($tx['member_id'] ?? '');

                if (! isset($memberIds[$memberId])) {
                    throw new RuntimeException(
                        "Transaction #{$oldId} has no mapped member."
                    );
                }

                $event = [
                    'legacy_transaction_id' => (string) $oldId,
                    'legacy_loan_id' =>
                        isset($tx['loan_id'])
                            ? (string) $tx['loan_id']
                            : null,
                    'membership_id' => $memberIds[$memberId],
                    'amount_cents' => $amount,
                    'date' => $date,
                ];

                if ($kind === 'contribution_received') {
                    /*
                     * The payment date does not prove
                     * which monthly obligation was paid.
                     */
                    $event['obligation_allocation'] =
                        'unverified';

                    $plan['contributions'][] = $event;

                    $plan['totals_cents']['contributions']
                        += $amount;

                    continue;
                }

                if ($kind === 'penalty_unverified') {
                    $event['collection_status'] =
                        'unverified';

                    $plan['penalties_pending_review'][] =
                        $event;

                    $plan['totals_cents']['penalties_unverified']
                        += $amount;

                    continue;
                }

                if (in_array($kind, [
                    'loan_disbursement',
                    'loan_repayment_received',
                    'interest_component',
                ], true)) {
                    $oldLoanId = (string) ($tx['loan_id'] ?? '');

                    if (
                        ! isset($rows['loans'][$oldLoanId])
                        || (string) $rows['loans'][$oldLoanId]['member_id']
                            !== $memberId
                    ) {
                        throw new RuntimeException(
                            "Transaction #{$oldId} does not match its loan borrower."
                        );
                    }

                    $loanEvents[$oldLoanId][$kind][] =
                        $event;

                    continue;
                }

                $issues[] =
                    "Transaction #{$oldId} has unsupported type {$kind}.";
            }

            foreach ($rows['loans'] as $oldId => $loan) {
                $oldId = (string) $oldId;
                $oldMemberId = (string) $loan['member_id'];

                if (
                    (string) ($loan['society_id'] ?? '')
                        !== (string) $oldSociety['id']
                    || (string) ($loan['cycle_id'] ?? '')
                        !== (string) $oldCycle['id']
                    || ! isset($memberIds[$oldMemberId])
                ) {
                    throw new RuntimeException(
                        "Loan #{$oldId} has invalid ownership."
                    );
                }

                $principal = $this->cents(
                    $loan['principal']
                );

                $interest = $this->cents(
                    $loan['interest']
                );

                $total = $this->cents(
                    $loan['total_amount']
                );

                $outstanding = $this->cents(
                    $loan['outstanding_balance']
                );

                $penaltyRemaining = $this->cents(
                    $loan['penalty_amount'] ?? '0'
                );

                $disbursements = $loanEvents[$oldId]
                    ['loan_disbursement'] ?? [];

                $repayments = $loanEvents[$oldId]
                    ['loan_repayment_received'] ?? [];

                $interestEvents = $loanEvents[$oldId]
                    ['interest_component'] ?? [];

                $disbursed = array_sum(
                    array_column($disbursements, 'amount_cents')
                );

                $repaid = array_sum(
                    array_column($repayments, 'amount_cents')
                );

                $interestPaid = array_sum(
                    array_column($interestEvents, 'amount_cents')
                );

                if (
                    $principal <= 0
                    || $principal !== $disbursed
                    || count($disbursements) !== 1
                    || $total !== $principal + $interest
                ) {
                    $issues[] =
                        "Loan #{$oldId} has inconsistent contractual or disbursement totals.";
                }

                if ($interestPaid > $repaid) {
                    $issues[] =
                        "Loan #{$oldId} has interest greater than receipts.";
                }

                if (
                    $outstanding !==
                    $total + $penaltyRemaining - $repaid
                ) {
                    $issues[] =
                        "Loan #{$oldId} historical outstanding balance does not reconcile.";
                }

                $repaidByDate = [];
                $interestByDate = [];

                foreach ($repayments as $repayment) {
                    $repaidByDate[$repayment['date']][] =
                        $repayment;
                }

                foreach ($interestEvents as $component) {
                    $interestByDate[$component['date']][] =
                        $component;
                }

                $allocatedRepayments = [];

                foreach ($repaidByDate as $date => $events) {
                    $components = $interestByDate[$date] ?? [];

                    if (count($events) !== 1 && count($components) > 0) {
                        $issues[] =
                            "Loan #{$oldId}, {$date}: multiple repayments; interest allocation is ambiguous.";

                        continue;
                    }

                    $interestOnDate = array_sum(
                        array_column(
                            $components,
                            'amount_cents'
                        )
                    );

                    foreach ($events as $repayment) {
                        if (
                            $interestOnDate >
                            $repayment['amount_cents']
                        ) {
                            $issues[] =
                                "Loan #{$oldId}, {$date}: interest exceeds repayment.";
                            continue;
                        }

                        $allocatedRepayments[] = [
                            ...$repayment,
                            'interest_cents' => $interestOnDate,
                            'principal_cents' =>
                                $repayment['amount_cents']
                                - $interestOnDate,
                            'interest_source_ids' =>
                                array_column(
                                    $components,
                                    'legacy_transaction_id'
                                ),
                        ];
                    }
                }

                foreach ($interestByDate as $date => $components) {
                    if (! isset($repaidByDate[$date])) {
                        $issues[] =
                            "Loan #{$oldId}, {$date}: interest entry has no repayment.";
                    }
                }

                $plan['loans'][] = [
                    'legacy_loan_id' => $oldId,
                    'membership_id' => $memberIds[$oldMemberId],
                    'legacy_status' => $loan['status'] ?? null,
                    'principal_cents' => $principal,
                    'contract_interest_cents' => $interest,
                    'contract_total_cents' => $total,
                    'recorded_outstanding_cents' => $outstanding,
                    'unverified_penalty_balance_cents' =>
                        $penaltyRemaining,
                    'issue_date' => $loan['issue_date'] ?? null,
                    'due_date' => $loan['due_date'] ?? null,
                    'disbursements' => $disbursements,
                    'repayments' => $allocatedRepayments,
                ];

                $plan['totals_cents']['principal_disbursed']
                    += $disbursed;

                $plan['totals_cents']['repayments']
                    += $repaid;

                $plan['totals_cents']['interest_paid']
                    += $interestPaid;
            }

            $this->info('Historical Financial Import Plan');

            $this->table(
                ['Category', 'Count / Amount'],
                [
                    ['Historical loans', count($plan['loans'])],
                    ['Contribution receipts', count($plan['contributions'])],
                    [
                        'Savings total',
                        'M ' . number_format(
                            $plan['totals_cents']['contributions'] / 100,
                            2
                        ),
                    ],
                    [
                        'Disbursed principal',
                        'M ' . number_format(
                            $plan['totals_cents']['principal_disbursed'] / 100,
                            2
                        ),
                    ],
                    [
                        'Loan repayments',
                        'M ' . number_format(
                            $plan['totals_cents']['repayments'] / 100,
                            2
                        ),
                    ],
                    [
                        'Interest within repayments',
                        'M ' . number_format(
                            $plan['totals_cents']['interest_paid'] / 100,
                            2
                        ),
                    ],
                    [
                        'Unverified penalty entries',
                        count(
                            $plan['penalties_pending_review']
                        ),
                    ],
                ]
            );

            /*
             * Determine the ACTUAL ledger-line model/table.
             */
            $ledgerLineTable = null;

            if (method_exists(
                LedgerTransaction::class,
                'lines'
            )) {
                $ledgerLineTable = (
                    new LedgerTransaction()
                )->lines()->getRelated()->getTable();
            }

            $this->newLine();
            $this->info('Target Schema Readiness');

            $required = [
                'loan_installments',
                'loan_disbursements',
                'loan_repayments',
                'loan_repayment_allocations',
                'loan_interest_credits',
                'contribution_payments',
                'contribution_obligations',
                'ledger_accounts',
                'ledger_transactions',
            ];

            if ($ledgerLineTable) {
                $required[] = $ledgerLineTable;
            } else {
                $issues[] =
                    'LedgerTransaction::lines() relationship is missing.';
            }

            $schemaRows = [];

            foreach ($required as $table) {
                $exists = Schema::hasTable($table);

                $schemaRows[] = [
                    $table,
                    $exists ? 'Found' : 'Missing',
                ];

                if (! $exists) {
                    $issues[] =
                        "Required target table {$table} is missing.";
                }
            }

            $this->table(
                ['Table', 'Status'],
                $schemaRows
            );

            /*
             * A financial import must target a clean
             * historical cycle, not overwrite existing data.
             */
            if ($cycle->status !== 'draft') {
                $issues[] =
                    'Target historical cycle must be in draft status before initial import.';
            }

            foreach ([
                'loans',
                'contribution_payments',
                'ledger_transactions',
            ] as $table) {
                if (
                    DB::table($table)
                        ->where('society_id', $societyId)
                        ->where('cycle_id', $cycleId)
                        ->exists()
                ) {
                    $issues[] =
                        "{$table} already has records in the target cycle.";
                }
            }

            /*
             * Ensure every original loan is represented once.
             */
            foreach ($plan['loans'] as $loan) {
                if (
                    DB::table('loans')
                        ->where('society_id', $societyId)
                        ->where('legacy_source', $source->source_code)
                        ->where('legacy_id', $loan['legacy_loan_id'])
                        ->exists()
                ) {
                    $issues[] =
                        "Legacy loan #{$loan['legacy_loan_id']} already exists.";
                }
            }

            $issues = array_values(array_unique($issues));

            $plan['blockers'] = $issues;
            $plan['ready_for_financial_import'] =
                count($issues) === 0;

            $directory = storage_path(
                'app/private/legacy-audits'
            );

            File::ensureDirectoryExists($directory);

            $path = $directory .
                DIRECTORY_SEPARATOR .
                "batch-{$batchId}-financial-plan.enc";

            /*
             * Financial source details remain encrypted.
             */
            File::put(
                $path,
                Crypt::encryptString(
                    json_encode(
                        $plan,
                        JSON_THROW_ON_ERROR
                    )
                )
            );

            $this->newLine();

            if ($issues !== []) {
                $this->warn('Import preparation requires attention:');

                foreach ($issues as $issue) {
                    $this->warn('- ' . $issue);
                }
            } else {
                $this->info(
                    'All automated financial-plan checks passed.'
                );
            }

            $this->info(
                "Encrypted import plan saved: {$path}"
            );

            $this->warn(
                'NO live historical loans, repayments, contributions, interest credits or ledger journals were created.'
            );

            return $issues === []
                ? self::SUCCESS
                : self::FAILURE;

        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'Financial import preparation failed: ' .
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
