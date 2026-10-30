# Mokhatlo 2.0 - safe/idempotent historical loan penalty visibility installer.
# Supports patch files already extracted into the Laravel root, or unpacked separately.
$ErrorActionPreference = 'Stop'
$root   = [System.IO.Path]::GetFullPath((Resolve-Path (Join-Path $PSScriptRoot '..')).Path)
$target = [System.IO.Path]::GetFullPath((Get-Location).Path)

if (-not (Test-Path -LiteralPath (Join-Path $target 'artisan') -PathType Leaf)) {
    throw 'Run this script from the Laravel project root (e.g. C:\laragon\www\mkhtlo).'
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = Join-Path $target ('.mokhatlo-backups\legacy-penalty-visibility-' + $stamp)

function Same-FilePath([string] $one, [string] $two) {
    $a = [System.IO.Path]::GetFullPath($one).TrimEnd([char[]]@('\','/'))
    $b = [System.IO.Path]::GetFullPath($two).TrimEnd([char[]]@('\','/'))
    return [string]::Equals($a, $b, [System.StringComparison]::OrdinalIgnoreCase)
}

function Backup-Existing([string] $relative, [string] $existing) {
    $saved = Join-Path $backup $relative
    New-Item -ItemType Directory -Path (Split-Path $saved -Parent) -Force | Out-Null
    Copy-Item -LiteralPath $existing -Destination $saved -Force
    Write-Host ('Backed up ' + $relative)
}

function Copy-WithBackup([string] $relative) {
    $source = Join-Path $root $relative
    $dest = Join-Path $target $relative
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        throw ('Missing patch file: ' + $source)
    }

    if (Same-FilePath $source $dest) {
        Write-Host ('Already in project; skipping self-copy: ' + $relative)
        return
    }

    if (Test-Path -LiteralPath $dest -PathType Leaf) {
        $sourceHash = (Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash
        $destHash = (Get-FileHash -LiteralPath $dest -Algorithm SHA256).Hash
        if ($sourceHash -eq $destHash) {
            Write-Host ('Already installed; unchanged: ' + $relative)
            return
        }
        Backup-Existing $relative $dest
    }

    New-Item -ItemType Directory -Path (Split-Path $dest -Parent) -Force | Out-Null
    Copy-Item -LiteralPath $source -Destination $dest -Force
    Write-Host ('Installed ' + $relative)
}

Copy-WithBackup 'app\Services\Legacy\LegacyLoanPenaltyVisibility.php'
Copy-WithBackup 'resources\views\loans\partials\legacy-penalty.blade.php'

$commandPath = 'app\Console\Commands\VerifyLegacyFinalization.php'
$commandTarget = Join-Path $target $commandPath
if ((Test-Path -LiteralPath $commandTarget -PathType Leaf) -and
    ((Get-Content -LiteralPath $commandTarget -Raw) -match 'Unpaid contractual loan principal \+ interest')) {
    Copy-WithBackup $commandPath
} else {
    Write-Warning 'Existing verify-final command differs from the supported version. It was NOT overwritten. The loan panel can still work.'
}

$loanView = Join-Path $target 'resources\views\loans\show.blade.php'
if (Test-Path -LiteralPath $loanView -PathType Leaf) {
    $content = Get-Content -LiteralPath $loanView -Raw -Encoding UTF8
    if ($content -match 'loans\.partials\.legacy-penalty') {
        Write-Host 'Loan detail include already installed; skipping.'
    } else {
        $pattern = '@section\s*\(\s*[\x27\x22]content[\x27\x22]\s*\)'
        $regex = [regex]::new($pattern)
        if ($regex.IsMatch($content)) {
            Backup-Existing 'resources\views\loans\show.blade.php' $loanView
            $replacement = '$0' + "`r`n" + "    @include('loans.partials.legacy-penalty')"
            $updated = $regex.Replace($content, $replacement, 1)
            [System.IO.File]::WriteAllText(
                $loanView,
                $updated,
                [System.Text.UTF8Encoding]::new($false)
            )
            Write-Host 'Added reviewed historical penalty panel to loans/show.blade.php.'
        } else {
            Write-Warning 'Loan detail uses a different Blade layout. Include loans.partials.legacy-penalty manually in the loan view where $loan is available.'
        }
    }
} else {
    Write-Warning 'No resources/views/loans/show.blade.php found. Include the loan penalty partial in the actual loan detail view manually.'
}

Write-Host ''
Write-Host 'No migrations, new charges, payments or financial journal writes were performed.'
Write-Host ('Any backups created during this run: ' + $backup)
Write-Host 'Next: php artisan optimize:clear; php artisan mokhatlo:legacy:verify-final --society=1'
