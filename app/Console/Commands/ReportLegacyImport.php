<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportLegacyImport extends Command
{
    protected $signature =
        'mokhatlo:legacy:report
         {batchId : Legacy import batch ID}
         {--limit=30 : Maximum exception records to display}';

    protected $description =
        'Inspect a staged historical Mokhatlo import';

    public function handle(): int
    {
        $batchId = (int) $this->argument('batchId');

        $batch = DB::table('legacy_import_batches as batches')
            ->join(
                'legacy_import_sources as sources',
                'sources.id',
                '=',
                'batches.source_id'
            )
            ->where('batches.id', $batchId)
            ->select(
                'batches.*',
                'sources.source_code'
            )
            ->first();

        if (! $batch) {
            $this->error(
                "Import batch {$batchId} does not exist."
            );

            return self::FAILURE;
        }

        $summary = json_decode(
            $batch->counts ?? '{}',
            true
        ) ?: [];

        $this->info(
            "Mokhatlo Legacy Import — Batch #{$batchId}"
        );

        $this->table(
            ['Property', 'Value'],
            [
                ['Source', $batch->source_code],
                ['Mode', $batch->mode],
                ['Status', $batch->status],
                ['Started', $batch->started_at ?? '—'],
                ['Finished', $batch->finished_at ?? '—'],
                ['Fingerprint', $batch->file_sha256],
            ]
        );

        $this->newLine();
        $this->info('Source Records');

        $tableCounts = [];

        foreach ($summary['tables'] ?? [] as $table => $count) {
            $tableCounts[] = [$table, $count];
        }

        $this->table(
            ['Legacy table', 'Rows staged'],
            $tableCounts
        );

        $this->newLine();
        $this->info('Transaction Classification');

        $types = [];

        foreach (
            $summary['transaction_types'] ?? []
            as $kind => $count
        ) {
            $types[] = [
                $kind,
                $count,
                number_format(
                    ($summary['transaction_totals_cents'][$kind] ?? 0)
                    / 100,
                    2
                ),
            ];
        }

        $this->table(
            ['Transaction category', 'Records', 'Source amount (M)'],
            $types
        );

        $this->warn(
            'Source amounts are not yet verified ledger or cash balances.'
        );

        $this->newLine();
        $this->info('Exceptions by Type');

        $groups = DB::table('legacy_import_exceptions')
            ->where('batch_id', $batchId)
            ->selectRaw(
                'severity, error_code, COUNT(*) as total'
            )
            ->groupBy('severity', 'error_code')
            ->orderBy('severity')
            ->orderBy('error_code')
            ->get();

        $this->table(
            ['Severity', 'Code', 'Count'],
            $groups->map(fn ($row) => [
                $row->severity,
                $row->error_code,
                $row->total,
            ])->all()
        );

        $limit = min(
            200,
            max(1, (int) $this->option('limit'))
        );

        $exceptions = DB::table('legacy_import_exceptions')
            ->where('batch_id', $batchId)
            ->orderByRaw(
                "CASE WHEN severity = 'error' THEN 0 ELSE 1 END"
            )
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($exceptions->isNotEmpty()) {
            $this->newLine();
            $this->warn(
                'Exception Details — first ' . $limit
            );

            $this->table(
                ['Table', 'Source ID', 'Severity', 'Issue'],
                $exceptions->map(fn ($row) => [
                    $row->source_table,
                    $row->source_id_value ?? '—',
                    $row->severity,
                    $row->error_code . ': ' . $row->details,
                ])->all()
            );
        }

        /*
         * Compare source fingerprints from earlier snapshots.
         * Two matching snapshots help verify a stable source.
         */
        $matching = DB::table('legacy_import_batches')
            ->where('source_id', $batch->source_id)
            ->where('file_sha256', $batch->file_sha256)
            ->where('id', '!=', $batchId)
            ->where('status', '!=', 'failed')
            ->count();

        $this->newLine();

        $this->line(
            "Other matching snapshots: {$matching}"
        );

        if ($matching === 0) {
            $this->warn(
                'No matching earlier source snapshot was found.'
            );
        } else {
            $this->info(
                'Matching source fingerprint found.'
            );
        }

        $this->newLine();

        $this->info(
            'No historical financial records have been posted ' .
            'to Mokhatlo 2.0 by this audit.'
        );

        return self::SUCCESS;
    }
}
