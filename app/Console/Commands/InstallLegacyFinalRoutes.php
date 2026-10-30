<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class InstallLegacyFinalRoutes extends Command
{
    protected $signature = 'mokhatlo:legacy:install-final-routes';
    protected $description = 'Back up routes/web.php and append unique Step 9F routes with payout protection';

    public function handle(): int
    {
        try {
            $web = base_path('routes/web.php');
            $snippetPath = base_path('routes/STEP_9F_FINAL_ROUTES.txt');
            if (! is_file($web) || ! is_file($snippetPath)) {
                throw new RuntimeException('web.php or Step 9F route snippet is missing.');
            }
            $contents = File::get($web);
            $marker = '// MOKHATLO_STEP9F_FINAL_ROUTES_START';
            if (str_contains($contents, $marker)) {
                $this->info('Step 9F routes are already installed. No changes made.');
                return self::SUCCESS;
            }
            if (str_contains($contents, "name('legacy.finalize.index')")) {
                throw new RuntimeException('Existing finalization routes found; review manually to avoid duplicates.');
            }
            $backupDir = storage_path('app/private/legacy-route-backups');
            File::ensureDirectoryExists($backupDir);
            $backup = $backupDir.'/web-'.now()->format('Ymd-His').'.php';
            File::copy($web, $backup);
            $append = "\n\n{$marker}\n".File::get($snippetPath)
                ."\n// MOKHATLO_STEP9F_FINAL_ROUTES_END\n";
            File::append($web, $append);
            $this->info('Added Step 9F finalization routes and fail-closed payout guards.');
            $this->line('Backed up old web.php: '.$backup);
            $this->warn('Run php artisan route:list --path=legacy and php artisan route:list --path=payouts -v to verify routing.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Routes not modified: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
