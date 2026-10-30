// Append ONCE to routes/console.php. Keep your existing scheduled tasks.
\Illuminate\Support\Facades\Schedule::command('mokhatlo:mail:monthly')
    ->dailyAt('08:00')->timezone('Africa/Maseru')->withoutOverlapping(30);
\Illuminate\Support\Facades\Schedule::command('mokhatlo:mail:scan')
    ->everyFiveMinutes()->withoutOverlapping(10);
\Illuminate\Support\Facades\Schedule::command('mokhatlo:mail:process')
    ->everyMinute()->withoutOverlapping(10);
