<?php

namespace App\Console\Commands;

use App\Services\Contributions\ContributionObligationCoverage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PreviewLegacyContributionCompliance extends Command
{
    protected $signature = 'mokhatlo:legacy:compliance-preview
        {--society=1 : Society ID}
        {--cycle=2 : Historical cycle ID}
        {--as-of= : Source receipt cutoff date YYYY-MM-DD}';

    protected $description = 'Read-only ordinary + historical obligation coverage preview';

    public function handle(ContributionObligationCoverage $coverage): int
    {
        try {
            $societyId = (int) $this->option('society');
            $cycleId = (int) $this->option('cycle');
            if (! DB::table('legacy_financial_import_runs')
                ->where('society_id', $societyId)
                ->where('cycle_id', $cycleId)
                ->exists()) {
                $this->error('Selected cycle does not have a historical financial import run.');
                return self::FAILURE;
            }

            $result = $coverage->forCycle(
                $societyId,
                $cycleId,
                $this->option('as-of') ?: null
            );
            $money = fn (int $cents) => 'M ' . number_format($cents / 100, 2);

            $this->info("Historical Compliance Preview — Cycle #{$cycleId}");
            $this->line('As of original payment date: ' . $result['as_of_date']);

            $this->table(
                ['Obligation', 'Member ID', 'Period', 'Due', 'Required', 'Ordinary', 'Historical', 'Outstanding'],
                array_map(fn ($r) => [
                    $r['obligation_id'], $r['society_membership_id'],
                    $r['period_start'], $r['due_date'], $money($r['required_cents']),
                    $money($r['direct_paid_cents']), $money($r['historical_paid_cents']),
                    $money($r['outstanding_cents']),
                ], $result['rows'])
            );

            $t = $result['totals'];
            $this->table(['Metric', 'Value'], [
                ['Obligations found', $t['obligations']],
                ['Fully paid obligations', $t['fully_paid_obligations']],
                ['Required total', $money($t['required_cents'])],
                ['Ordinary direct receipts', $money($t['direct_paid_cents'])],
                ['Reviewed historical links', $money($t['historical_paid_cents'])],
                ['Combined receipt coverage', $money($t['paid_cents'])],
                ['Outstanding on these obligations', $money($t['outstanding_cents'])],
                ['Overpayments (not lost)', $money($t['excess_cents'])],
            ]);

            $this->warn($result['notice']);
            $this->info('Read only: no contributions, obligations, penalties, or journal lines changed.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
