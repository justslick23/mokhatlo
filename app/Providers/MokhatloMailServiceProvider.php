<?php

namespace App\Providers;

use App\Services\MokhatloMail\CaptureFinancialActivity;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class MokhatloMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Registered once at application boot. No changes to financial services.
        // Eloquent events are transaction-aware via the same database connection:
        // if a transaction rolls back, the event + outbox rows roll back too.
        $captureTypes = [
            'ContributionPayment', 'Loan', 'LoanDisbursement', 'LoanRepayment',
            'ContributionPenaltyPayment', 'LoanPenaltyPayment',
            'ContributionPenaltyWaiver', 'LoanPenaltyWaiver',
        ];

        foreach ($captureTypes as $type) {
            $class = 'App\\Models\\' . $type;
            if (! class_exists($class)) {
                continue;
            }
            Event::listen('eloquent.created: ' . $class,
                static function ($model): void {
                    try {
                        app(CaptureFinancialActivity::class)->capture($model);
                    } catch (\Throwable $e) {
                        // Accounting is more important than email. The scanner can recover
                        // unrecorded inserts; admins are alerted to repair misconfiguration.
                        \Illuminate\Support\Facades\Log::error('Mokhatlo mail capture failed', [
                            'model' => get_class($model), 'id' => $model->getKey(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            );
        }

        // Loan request approval/rejection is an update, not an additional cash movement.
        Event::listen('eloquent.updated: App\\Models\\Loan',
            static function ($loan): void {
                if ($loan->wasChanged('status')) {
                    try {
                        app(CaptureFinancialActivity::class)->capture($loan, 'status');
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('Mokhatlo loan status notification capture failed', [
                            'loan_id' => $loan->getKey(), 'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        );
    }
}
