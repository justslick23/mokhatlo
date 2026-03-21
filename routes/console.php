<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('reports:month-end', [
    '--month' => now()->subMonth()->month,
    '--year'  => now()->subMonth()->year,
])
    ->monthlyOn(1, '07:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(fn() => \Log::info('Month-end reports generated successfully.'))
    ->onFailure(fn() => \Log::error('Month-end report generation failed.'));

// Contribution & loan deadline checks
Schedule::command('contributions:process-deadlines')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(fn() => \Log::info('Contribution deadlines processed successfully.'))
    ->onFailure(fn() => \Log::error('Contribution deadline processing failed.'));

// Loan deadline reminders and penalties — daily
Schedule::command('loans:process-deadlines')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(fn() => \Log::info('Loan deadlines processed successfully.'))
    ->onFailure(fn() => \Log::error('Loan deadline processing failed.'));