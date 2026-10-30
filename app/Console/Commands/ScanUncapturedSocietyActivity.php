<?php

namespace App\Console\Commands;

use App\Services\MokhatloMail\CaptureFinancialActivity;
use Illuminate\Console\Command;

final class ScanUncapturedSocietyActivity extends Command
{
    protected $signature = 'mokhatlo:mail:scan';
    protected $description = 'Safety net for newly inserted financial models (including direct-query inserts).';

    public function handle(CaptureFinancialActivity $capture): int
    {
        if (! config('mokhatlo_mail.enabled') || ! config('mokhatlo_mail.started_at')) {
            return self::SUCCESS;
        }
        foreach ([
            'ContributionPayment', 'Loan', 'LoanDisbursement', 'LoanRepayment',
            'ContributionPenaltyPayment', 'LoanPenaltyPayment',
            'ContributionPenaltyWaiver', 'LoanPenaltyWaiver',
        ] as $type) {
            $model = 'App\\Models\\' . $type;
            if (! class_exists($model)) continue;
            $model::query()->where('created_at', '>=', config('mokhatlo_mail.started_at'))
                ->orderBy('id')->chunkById(200, static function ($rows) use ($capture) {
                    foreach ($rows as $row) $capture->capture($row);
                });
        }
        // NOTE: direct SQL updates to existing loan statuses bypass Eloquent updated events.
        // Call CaptureFinancialActivity::capture($loan, 'status') in such custom writers.
        return self::SUCCESS;
    }
}
