<?php

namespace App\Console\Commands;

use App\Models\LoanInstallment;
use App\Services\Loans\AssessLoanInstallmentPenalty;
use Illuminate\Console\Command;

class AssessLoanPenalties extends Command
{
    protected $signature = 'mokhatlo:assess-loan-penalties';

    protected $description =
        'Assess one-time penalties on overdue loan instalments';

    public function handle(
        AssessLoanInstallmentPenalty $assessor
    ): int {
        $checked = 0;
        $created = 0;
        $failed = 0;

        LoanInstallment::query()
            ->whereDate('due_date', '<', now('Africa/Maseru')->toDateString())
            ->whereHas('loan', function ($query) {
                $query->whereIn(
                    'status',
                    ['disbursed', 'settled']
                );
            })
            ->whereDoesntHave('penalty')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (
                $assessor,
                &$checked,
                &$created,
                &$failed
            ) {
                foreach ($rows as $row) {
                    $checked++;

                    try {
                        if ($assessor->execute($row->id) !== null) {
                            $created++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;

                        report($exception);

                        $this->error(
                            "Installment {$row->id}: " .
                            $exception->getMessage()
                        );
                    }
                }
            });

        $this->info(
            "Checked: {$checked}, " .
            "Assessed: {$created}, " .
            "Failed: {$failed}"
        );

        return $failed === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
