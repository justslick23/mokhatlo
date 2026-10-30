<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


use Illuminate\Support\Facades\Schedule;

Schedule::command(
    'mokhatlo:assess-contribution-penalties'
)
    ->dailyAt('01:15')
    ->timezone('Africa/Maseru')
    ->withoutOverlapping();

    
    Schedule::command('mokhatlo:assess-loan-penalties')
    ->dailyAt('01:30')
    ->timezone('Africa/Maseru')
    ->withoutOverlapping();

// Append ONCE to routes/console.php. Keep your existing scheduled tasks.
\Illuminate\Support\Facades\Schedule::command('mokhatlo:mail:monthly')
    ->dailyAt('08:00')->timezone('Africa/Maseru')->withoutOverlapping(30);
\Illuminate\Support\Facades\Schedule::command('mokhatlo:mail:scan')
    ->everyFiveMinutes()->withoutOverlapping(10);
\Illuminate\Support\Facades\Schedule::command('mokhatlo:mail:process')
    ->everyMinute()->withoutOverlapping(10);
