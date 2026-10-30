<?php

namespace App\Console\Commands;

use App\Services\MokhatloMail\MonthlyReportPositions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PreviewMokhatloHistoricalBank extends Command
{
    protected $signature = 'mokhatlo:reports:bank-history {--society=1} {--year=2026}';
    protected $description = 'Read-only monthly bank balances, including supported historic reconstruction';

    public function handle(MonthlyReportPositions $positions): int
    {
        $societyId = (int) $this->option('society');
        $year = (int) $this->option('year');
        if ($societyId < 1 || $year < 2000 || $year > 2100
            || ! DB::table('societies')->where('id', $societyId)->exists()) {
            $this->error('Invalid society or reporting year.');
            return self::FAILURE;
        }
        $rows = [];
        foreach (range(1,12) as $month) {
            $end = \Carbon\CarbonImmutable::create($year,$month,1)->endOfMonth()->toDateString();
            $p = $positions->ledger($societyId, $end);
            $rows[] = [
                substr($end,0,7),
                'M '.number_format(($p['bank_for_report_cents'] ?? $p['BANK_ACCOUNT']) / 100, 2),
                ($p['bank_for_report_status'] ?? 'recorded') === 'reconstructed'
                    ? 'Calculated from historical transactions'
                    : 'Dated bank ledger',
            ];
        }
        $this->table(['Month', 'Bank balance at month-end', 'Source'], $rows);
        $this->warn('Calculated historical amounts depend on complete records of deposits, loan payments and withdrawals. This command does not alter any financial records.');
        return self::SUCCESS;
    }
}
