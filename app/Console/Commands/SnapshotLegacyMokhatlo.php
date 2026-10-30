<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyTransactionType;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SnapshotLegacyMokhatlo extends Command
{
    protected $signature = 'mokhatlo:legacy:snapshot';

    protected $description =
        'Read and stage a legacy Mokhatlo database without importing financial transactions';

    private const MINIMUM_COLUMNS = [
        'users' => ['id', 'name', 'email'],
        'societies' => ['id', 'name'],
        'members' => ['id', 'user_id', 'society_id'],
        'cycles' => [
            'id', 'society_id', 'start_date', 'end_date',
        ],
        'loans' => [
            'id', 'society_id', 'cycle_id',
            'member_id', 'principal', 'interest',
            'total_amount', 'outstanding_balance',
        ],
        'transactions' => [
            'id', 'society_id', 'cycle_id',
            'member_id', 'type', 'amount',
            'transaction_date',
        ],
    ];

    private const FOREIGN_KEYS = [
        'members' => [
            'user_id' => 'users',
            'society_id' => 'societies',
        ],
        'cycles' => [
            'society_id' => 'societies',
        ],
        'loans' => [
            'society_id' => 'societies',
            'cycle_id' => 'cycles',
            'member_id' => 'members',
            'issued_by' => 'users',
        ],
        'transactions' => [
            'society_id' => 'societies',
            'cycle_id' => 'cycles',
            'member_id' => 'members',
            'loan_id' => 'loans',
            'recorded_by' => 'users',
        ],
    ];

    public function handle(): int
    {
        $batchId = null;

        try {
            $settings = config('legacy_mokhatlo');

            $connectionConfig = $settings['connection'] ?? [];

            if (
                empty($connectionConfig['database'])
                || empty($connectionConfig['username'])
            ) {
                throw new RuntimeException(
                    'Configure LEGACY_DB_DATABASE and LEGACY_DB_USERNAME first.'
                );
            }

            /*
             * Register the connection at runtime.
             * The application's main DB connection is unchanged.
             */
            config([
                'database.connections.legacy_mokhatlo' =>
                    $connectionConfig,
            ]);

            DB::purge('legacy_mokhatlo');

            $legacy = DB::connection('legacy_mokhatlo');
            $target = DB::connection();

            if (
                $legacy->getDatabaseName()
                === $target->getDatabaseName()
            ) {
                throw new RuntimeException(
                    'Legacy source and target databases must be different.'
                );
            }

            /*
             * Confirm the tracking tables exist.
             */
            foreach ([
                'legacy_import_sources',
                'legacy_import_batches',
                'legacy_import_exceptions',
                'legacy_import_staged_rows',
            ] as $table) {
                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    throw new RuntimeException(
                        "Missing target tracking table: {$table}"
                    );
                }
            }

            $sourceCode = $settings['source_code']
                ?? 'legacy_mokhatlo';

            $source = DB::table('legacy_import_sources')
                ->where('source_code', $sourceCode)
                ->first();

            if (! $source) {
                $sourceId = DB::table('legacy_import_sources')
                    ->insertGetId([
                        'source_code' => $sourceCode,
                        'description' =>
                            'Read-only historical Mokhatlo source',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            } else {
                $sourceId = $source->id;
            }

            $batchId = DB::table('legacy_import_batches')
                ->insertGetId([
                    'source_id' => $sourceId,
                    'file_sha256' => str_repeat('0', 64),
                    'mode' => 'dry_run',
                    'status' => 'running',
                    'counts' => null,
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $issues = (object) [
                'error' => 0,
                'warning' => 0,
            ];

            $counts = [];
            $types = [];
            $typeTotals = [];

            $fingerprint = hash_init('sha256');

            $this->info(
                "Historical snapshot batch #{$batchId}"
            );

            /*
             * Query Builder includes soft-deleted rows.
             * No writes are issued to the legacy connection.
             */
            foreach ($settings['columns'] as $table => $wanted) {
                if (! $legacy->getSchemaBuilder()->hasTable($table)) {
                    $required = in_array(
                        $table,
                        $settings['required_tables'],
                        true
                    );

                    if ($required) {
                        $this->issue(
                            $batchId,
                            $issues,
                            $table,
                            null,
                            'error',
                            'MISSING_TABLE',
                            "Required source table {$table} does not exist."
                        );
                    }

                    $counts[$table] = 0;
                    continue;
                }

                $actualColumns = $legacy
                    ->getSchemaBuilder()
                    ->getColumnListing($table);

                $available = array_values(
                    array_intersect($wanted, $actualColumns)
                );

                $minimum = self::MINIMUM_COLUMNS[$table] ?? ['id'];

                $missing = array_diff($minimum, $available);

                if ($missing !== []) {
                    $this->issue(
                        $batchId,
                        $issues,
                        $table,
                        null,
                        'error',
                        'SCHEMA_MISMATCH',
                        'Required columns missing: '
                            . implode(', ', $missing)
                    );

                    $counts[$table] = 0;
                    continue;
                }

                if (
                    $table === 'loans'
                    && ! in_array('interest_paid', $available, true)
                ) {
                    $this->issue(
                        $batchId,
                        $issues,
                        $table,
                        null,
                        'warning',
                        'INTEREST_PAID_COLUMN_MISSING',
                        'Historical interest paid must be reconciled using transaction records.'
                    );
                }

                $counts[$table] = 0;

                $legacy->table($table)
                    ->select($available)
                    ->orderBy('id')
                    ->chunkById(200, function ($records) use (
                        $legacy,
                        $table,
                        $batchId,
                        $issues,
                        &$counts,
                        &$types,
                        &$typeTotals,
                        $fingerprint
                    ) {
                        foreach ($records as $record) {
                            $row = (array) $record;
                            $sourceId = (string) $row['id'];

                            $eventKind = null;

                            if ($table === 'transactions') {
                                $eventKind = LegacyTransactionType::classify(
                                    $row['type'] ?? null
                                );

                                $types[$eventKind] =
                                    ($types[$eventKind] ?? 0) + 1;

                                try {
                                    $amountCents = Money::toCents(
                                        (string) $row['amount']
                                    );

                                    $typeTotals[$eventKind] =
                                        ($typeTotals[$eventKind] ?? 0)
                                        + $amountCents;

                                    if ($amountCents <= 0) {
                                        $this->issue(
                                            $batchId, $issues,
                                            $table, $sourceId,
                                            'warning',
                                            'NON_POSITIVE_TRANSACTION',
                                            'Review this transaction amount before import.'
                                        );
                                    }
                                } catch (Throwable $exception) {
                                    $this->issue(
                                        $batchId, $issues,
                                        $table, $sourceId,
                                        'error',
                                        'INVALID_MONEY',
                                        'Transaction amount cannot be interpreted as decimal money.'
                                    );
                                }

                                if (LegacyTransactionType::needsReview($eventKind)) {
                                    $this->issue(
                                        $batchId,
                                        $issues,
                                        $table,
                                        $sourceId,
                                        $eventKind === 'unknown'
                                            ? 'error'
                                            : 'warning',
                                        strtoupper($eventKind),
                                        'Verify the historical meaning and supporting financial evidence.'
                                    );
                                }
                            }

                            if ($table === 'loans') {
                                $this->checkLoan(
                                    $batchId,
                                    $issues,
                                    $row,
                                    $sourceId
                                );
                            }

                            $this->checkForeignKeys(
                                $legacy,
                                $batchId,
                                $issues,
                                $table,
                                $row,
                                $sourceId
                            );

                            $json = json_encode(
                                $row,
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                                | JSON_THROW_ON_ERROR
                            );

                            $rowHash = hash('sha256', $json);

                            hash_update(
                                $fingerprint,
                                "{$table}:{$sourceId}:{$rowHash}\n"
                            );

                            DB::table('legacy_import_staged_rows')
                                ->insert([
                                    'batch_id' => $batchId,
                                    'source_table' => $table,
                                    'source_id_value' => $sourceId,
                                    'event_kind' => $eventKind,
                                    'source_row_sha256' => $rowHash,
                                    'payload_encrypted' =>
                                        Crypt::encryptString($json),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                            $counts[$table]++;
                        }
                    });

                $this->line(
                    "{$table}: {$counts[$table]} record(s)"
                );
            }

            $snapshotHash = hash_final($fingerprint);

            /*
             * The existing column is named file_sha256
             * but stores a deterministic row-stream
             * snapshot hash for this direct-DB import.
             */
            $summary = [
                'tables' => $counts,
                'transaction_types' => $types,
                'transaction_totals_cents' => $typeTotals,
                'errors' => $issues->error,
                'warnings' => $issues->warning,
            ];

            DB::table('legacy_import_batches')
                ->where('id', $batchId)
                ->update([
                    'file_sha256' => $snapshotHash,
                    'status' =>
                        ($issues->error + $issues->warning > 0)
                            ? 'needs_review'
                            : 'completed',
                    'counts' => json_encode(
                        $summary,
                        JSON_THROW_ON_ERROR
                    ),
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->newLine();

            $this->info("Snapshot #{$batchId} saved.");

            $this->table(
                ['Check', 'Count'],
                [
                    ['Errors', $issues->error],
                    ['Warnings', $issues->warning],
                    ['Source records', array_sum($counts)],
                ]
            );

            $this->line(
                "Fingerprint: {$snapshotHash}"
            );

            $this->line(
                "Next: php artisan mokhatlo:legacy:report {$batchId}"
            );

            return $issues->error === 0
                ? self::SUCCESS
                : self::FAILURE;

        } catch (Throwable $exception) {
            if ($batchId !== null) {
                DB::table('legacy_import_batches')
                    ->where('id', $batchId)
                    ->update([
                        'status' => 'failed',
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            report($exception);

            $this->error(
                'Legacy snapshot failed: ' . $exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    private function checkForeignKeys(
        $legacy,
        int $batchId,
        object $issues,
        string $table,
        array $row,
        string $sourceId
    ): void {
        foreach (self::FOREIGN_KEYS[$table] ?? [] as $column => $parent) {
            $value = $row[$column] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (
                ! $legacy->getSchemaBuilder()->hasTable($parent)
                || ! $legacy->table($parent)
                    ->where('id', $value)
                    ->exists()
            ) {
                $this->issue(
                    $batchId,
                    $issues,
                    $table,
                    $sourceId,
                    'error',
                    'BROKEN_REFERENCE',
                    "{$column}={$value} cannot be found in {$parent}."
                );
            }
        }
    }

    private function checkLoan(
        int $batchId,
        object $issues,
        array $row,
        string $sourceId
    ): void {
        try {
            $principal = Money::toCents(
                (string) $row['principal']
            );

            $interest = Money::toCents(
                (string) $row['interest']
            );

            $total = Money::toCents(
                (string) $row['total_amount']
            );

            if ($total !== $principal + $interest) {
                $this->issue(
                    $batchId,
                    $issues,
                    'loans',
                    $sourceId,
                    'warning',
                    'CONTRACT_TOTAL_MISMATCH',
                    'Principal plus contractual interest differs from the stored loan total.'
                );
            }

            if (
                isset($row['penalty_amount'])
                && Money::toCents(
                    (string) $row['penalty_amount']
                ) !== 0
            ) {
                $this->issue(
                    $batchId,
                    $issues,
                    'loans',
                    $sourceId,
                    'warning',
                    'PENALTY_BALANCE_UNVERIFIED',
                    'A stored penalty balance does not prove that a penalty was collected.'
                );
            }
        } catch (Throwable $exception) {
            $this->issue(
                $batchId,
                $issues,
                'loans',
                $sourceId,
                'error',
                'INVALID_LOAN_MONEY',
                'One or more loan monetary fields are invalid.'
            );
        }
    }

    private function issue(
        int $batchId,
        object $issues,
        string $table,
        ?string $sourceId,
        string $severity,
        string $code,
        string $details
    ): void {
        $issues->{$severity}++;

        DB::table('legacy_import_exceptions')->insert([
            'batch_id' => $batchId,
            'source_table' => $table,
            'source_id_value' => $sourceId,
            'severity' => $severity,
            'error_code' => $code,
            'details' => $details,
            'resolution_status' => 'unresolved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
