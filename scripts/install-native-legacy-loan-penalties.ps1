$ErrorActionPreference = 'Stop'
$target = [System.IO.Path]::GetFullPath((Get-Location).Path)
$sourceRoot = [System.IO.Path]::GetFullPath((Resolve-Path (Join-Path $PSScriptRoot '..')).Path)
if (-not (Test-Path -LiteralPath (Join-Path $target 'artisan') -PathType Leaf)) {
    throw 'Run this installer from the existing Laravel project root.'
}
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = Join-Path $target ('.mokhatlo-backups\native-legacy-penalties-' + $stamp)
$paths = @(
    'database\migrations\2026_10_30_000100_create_legacy_imported_loan_penalties_table.php',
    'app\Console\Commands\LinkReviewedLegacyLoanPenalties.php',
    'app\Console\Commands\VerifyLegacyFinalization.php',
    'app\Services\Legacy\LegacyNativePenaltyImporter.php',
    'app\Services\Legacy\LegacyLoanPenaltyVisibility.php',
    'app\Services\Legacy\LegacyReconciliationStatus.php',
    'app\Services\Legacy\LegacyResolutionActions.php',
    'resources\views\loans\partials\legacy-penalty.blade.php'
)
function SamePath([string]$a, [string]$b) {
    return [string]::Equals([IO.Path]::GetFullPath($a), [IO.Path]::GetFullPath($b), [StringComparison]::OrdinalIgnoreCase)
}
foreach ($relative in $paths) {
    $source = Join-Path $sourceRoot $relative
    $dest = Join-Path $target $relative
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { throw "Patch file missing: $relative" }
    if (SamePath $source $dest) { Write-Host "Already extracted into project, skipping self-copy: $relative"; continue }
    if (Test-Path -LiteralPath $dest -PathType Leaf) {
        $newHash = (Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash
        $oldHash = (Get-FileHash -LiteralPath $dest -Algorithm SHA256).Hash
        if ($newHash -eq $oldHash) { Write-Host "Already installed: $relative"; continue }
        $saved = Join-Path $backup $relative
        New-Item -ItemType Directory -Path (Split-Path $saved -Parent) -Force | Out-Null
        Copy-Item -LiteralPath $dest -Destination $saved -Force
        Write-Host "Backed up: $relative"
    }
    New-Item -ItemType Directory -Path (Split-Path $dest -Parent) -Force | Out-Null
    Copy-Item -LiteralPath $source -Destination $dest -Force
    Write-Host "Installed: $relative"
}
Write-Host "Backups, if any: $backup"
Write-Host 'Next: php artisan optimize:clear; php artisan migrate; php artisan mokhatlo:legacy:penalties-link --society=1'
Write-Host 'PREVIEW FIRST. Do not use --apply until the two items and amounts are verified.'
