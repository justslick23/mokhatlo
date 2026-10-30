# Run from the Laravel project root. Appends policy configuration routes once.
$ErrorActionPreference = 'Stop'
$web = Join-Path (Get-Location).Path 'routes\web.php'
$snippet = Join-Path (Get-Location).Path 'routes\CYCLE_POLICY_ROUTES.txt'
if (!(Test-Path $web) -or !(Test-Path $snippet)) {
    throw 'Run from Laravel project root after merging package folders.'
}
$existing = [System.IO.File]::ReadAllText($web)
if ($existing -match "name\('cycles\.policy\.edit'\)" -or $existing -match 'cycles\.policy\.edit') {
    Write-Host 'Policy routes already installed. No changes made.'
    exit 0
}
$backupDir = Join-Path (Get-Location).Path 'storage\app\private\policy-route-backups'
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
$backup = Join-Path $backupDir ('web.php.' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.bak')
Copy-Item $web $backup
$routes = [System.IO.File]::ReadAllText($snippet)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($web, $existing.TrimEnd() + "`r`n`r`n" + $routes + "`r`n", $utf8NoBom)
Write-Host "Policy routes installed. Previous web.php backed up at $backup"
