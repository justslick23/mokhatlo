<?php

namespace App\Console\Commands;

use App\Services\Legacy\AuditLegacyFinancialImport as Auditor;
use Illuminate\Console\Command;
use Throwable;

final class AuditLegacyFinancialImport extends Command
{
    protected $signature = 'mokhatlo:legacy:audit {--society=1 : Society ID} {--run= : Specific financial import run ID}';
    protected $description = 'Read-only independent audit of imported loans, receipts, interest and ledger';

    public function handle(Auditor $auditor): int
    {
        try {
            $result = $auditor->execute(
                (int) $this->option('society'),
                $this->option('run') ? (int) $this->option('run') : null
            );
            $this->info('Post-Import Audit — Cycle #' . $result['cycle']->id);
            $this->table(['Check', 'Expected', 'Actual', 'Result'], array_map(
                fn ($r) => [$r['label'], $r['expected'], $r['actual'], $r['passed'] ? 'PASS' : 'FAIL'],
                $result['checks']
            ));
            $this->newLine();
            $this->info('Review Gates (not imported financial discrepancies)');
            $this->table(['Gate', 'Current'], array_map(
                fn ($r) => [$r['label'], $r['count']],
                $result['gates']
            ));
            $this->newLine();
            $this->info('Member Historical Positions (not approved payouts)');
            $this->table(['Member', 'Savings', 'Paid interest credit', 'Original unpaid loan total'], array_map(
                fn ($r) => [$r['number'] . ' / ' . $r['name'],
                    $auditor->money($r['savings_cents']),
                    $auditor->money($r['interest_cents']),
                    $auditor->money($r['outstanding_cents'])],
                $result['members']
            ));
            if (! $result['passed']) {
                $this->error('Audit mismatches exist. Do not activate or close this cycle.');
                return self::FAILURE;
            }
            $this->info('All imported amount, journal and member-credit checks passed.');
            $this->warn('The cycle is NOT ready to close: clearing, contributions and penalties need review.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
