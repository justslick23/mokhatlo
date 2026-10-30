<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Services\Legacy\LegacyReconciliationStatus;
use App\Services\Legacy\LegacyLoanPenaltyVisibility;
use App\Services\Reports\SocietyFinancialReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class VerifyLegacyFinalization extends Command
{
    protected $signature = 'mokhatlo:legacy:verify-final {--society=1}';
    protected $description = 'Verify historical report integration and unresolved evidence gates (read-only)';

    public function handle(LegacyReconciliationStatus $status, SocietyFinancialReport $reports, LegacyLoanPenaltyVisibility $historicPenalties): int
    {
        $society = Society::find((int) $this->option('society'));
        if (! $society) {
            $this->error('Society does not exist.');
            return self::FAILURE;
        }
        $result = $status->forSociety($society);
        if (! $result['imported']) {
            $this->error('No completed historical financial import exists.');
            return self::FAILURE;
        }
        $report = $reports->build($society, $result['cycle_id']);
        $coverage = $result['compliance']['totals'];
        $rows = [
            ['Report recognises historical cycle', $report['historical_cycle'] ? 'PASS' : 'FAIL'],
            ['Report savings equal original receipt savings', $report['contributions_received'] === $coverage['paid_cents'] ? 'PASS' : 'FAIL'],
            ['Report required equals reviewed obligations', $report['contributions_required'] === $coverage['required_cents'] ? 'PASS' : 'FAIL'],
            ['Report outstanding equals reviewed obligations', $report['contributions_outstanding'] === $coverage['outstanding_cents'] ? 'PASS' : 'FAIL'],
            ['Verified historical obligation coverage', $result['obligations_linked'] ? 'PASS' : 'FAIL'],
        ];
        $this->table(['Verification', 'Result'], $rows);
        $this->table(['Review gate', 'Current'], [
            ['Penalties requiring evidence', $result['pending_penalties']],
            ['Collected penalties not posted', $result['collected_pending_accounting']],
            ['Historical outstanding penalties not integrated', $result['outstanding_penalties_pending_subledger']],
            ['Clearing reclassified with evidence', $result['clearing_posted'] ? 'Yes' : 'No'],
            ['Unpaid contractual loan principal + interest (M)', number_format($result['contractual_outstanding_cents'] / 100, 2)],
        ]);
        $penalties = $historicPenalties->forCycle($society, (int) $result['cycle_id']);
        if ($penalties['rows'] !== []) {
            $this->newLine();
            $this->info('Historical loan penalties — pending evidence and native outstanding (no double-count)');
            $this->table(
                ['Loan', 'Principal + Interest', 'Pending Native', 'Native Outstanding', 'Combined Balance'],
                array_map(static fn (array $row) => [
                    $row['loan_number'],
                    'M '.number_format($row['contractual_cents'] / 100, 2),
                    'M '.number_format($row['documented_historical_penalty_cents'] / 100, 2),
                    'M '.number_format($row['native_historical_penalty_cents'] / 100, 2),
                    'M '.number_format($row['combined_cents'] / 100, 2),
                ], $penalties['rows'])
            );
            $this->line('All open contractual loans: M '.number_format($penalties['contractual_cents'] / 100, 2));
            $this->line('Pending native historical penalty balance: M '.number_format($penalties['documented_historical_penalties_cents'] / 100, 2));
            $this->line('Native outstanding historical penalty balance: M '.number_format($penalties['native_historical_penalties_cents'] / 100, 2));
            $this->line('Combined including historical penalties (once): M '.number_format($penalties['combined_cents'] / 100, 2));
            $this->comment($penalties['note']);
        }
        if ($result['blockers'] !== []) {
            foreach ($result['blockers'] as $blocker) $this->warn('- '.$blocker);
        } else {
            $this->info('Historical review gates cleared. Independent standard payout approval rules still apply.');
        }
        return collect($rows)->every(fn ($r) => $r[1] === 'PASS') ? self::SUCCESS : self::FAILURE;
    }
}
