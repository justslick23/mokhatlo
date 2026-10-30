$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$providers = Join-Path $root 'bootstrap\providers.php'
$console = Join-Path $root 'routes\console.php'
$snippet = Join-Path $root 'routes\CONSOLE_SCHEDULER_SNIPPET.php'
if (!(Test-Path $providers) -or !(Test-Path $console) -or !(Test-Path $snippet)) {
    throw 'Missing bootstrap/providers.php, routes/console.php or scheduler snippet. Run from extracted project root.'
}
$backup = Join-Path $root ('storage\app\mail-route-backups\' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force -Path $backup | Out-Null
Copy-Item $providers (Join-Path $backup 'providers.php')
Copy-Item $console (Join-Path $backup 'console.php')
$providerText = [System.IO.File]::ReadAllText($providers)
if ($providerText -notmatch 'MokhatloMailServiceProvider') {
    if ($providerText -notmatch 'return\s*\[') { throw 'Unexpected provider registry format. Add provider manually.' }
    $providerText = ([regex]::new('return\s*\[')).Replace($providerText, "return [`r`n    \App\Providers\MokhatloMailServiceProvider::class,", 1)
    [System.IO.File]::WriteAllText($providers, $providerText, (New-Object System.Text.UTF8Encoding($false)))
    Write-Host 'Provider registered.'
} else { Write-Host 'Provider already registered.' }
$consoleText = [System.IO.File]::ReadAllText($console)
if ($consoleText -notmatch 'mokhatlo:mail:monthly') {
    $schedule = [System.IO.File]::ReadAllText($snippet) -replace '(?m)^<\?php\s*', ''
    [System.IO.File]::AppendAllText($console, "`r`n" + $schedule, (New-Object System.Text.UTF8Encoding($false)))
    Write-Host 'Scheduler registered.'
} else { Write-Host 'Scheduler already registered.' }
Write-Host "Backup: $backup"
Write-Host 'Next: composer require barryvdh/laravel-dompdf:^3.1 ; php artisan migrate ; php artisan schedule:list'
