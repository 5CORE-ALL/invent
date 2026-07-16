<?php

namespace App\Console\Commands;

use App\Support\StoragePathGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class EnsureStorageDirectories extends Command
{
    protected $signature = 'storage:ensure
                            {--fix : Create missing directories (default: only check)}';

    protected $description = 'Ensure storage/framework and bootstrap/cache directories exist and are writable (run after deploy to fix cache/session errors)';

    public function handle(): int
    {
        $base = storage_path();
        $dirs = [
            $base . '/framework/cache/data',
            $base . '/framework/sessions',
            $base . '/framework/views',
            $base . '/logs',
            base_path('bootstrap/cache'),
        ];

        $fix = $this->option('fix');
        $allOk = true;

        if ($fix) {
            StoragePathGuard::ensure();
            StoragePathGuard::repairCacheTree();
        }

        foreach ($dirs as $dir) {
            $exists = is_dir($dir);
            $writable = $exists && is_writable($dir);

            if (!$exists) {
                if ($fix) {
                    if (File::makeDirectory($dir, 0775, true)) {
                        $this->info("[CREATED] {$dir}");
                    } else {
                        $this->error("[FAILED] Could not create: {$dir}");
                        $allOk = false;
                    }
                } else {
                    $this->warn("[MISSING] {$dir}");
                    $allOk = false;
                }
                continue;
            }

            if (!$writable) {
                $this->warn("[NOT WRITABLE] {$dir}");
                $allOk = false;
                continue;
            }

            $this->line("[OK] {$dir}");
        }

        if (!$allOk && !$fix) {
            $this->newLine();
            $this->info('Run with --fix to create/repair directories:');
            $this->line('  php artisan storage:ensure --fix');
            $this->newLine();
            $this->comment('Do NOT use 777 — host scanners often rewrite it to 665 (dirs lose +x and break).');
            $this->comment('Prefer leaving file cache entirely:');
            $this->line('  CACHE_DRIVER=database');
            $this->newLine();
            $this->comment('If you must keep file cache (replace www-data with your web/cron user):');
            $this->line('  chown -R www-data:www-data storage bootstrap/cache');
            $this->line('  find storage bootstrap/cache -type d -exec chmod 2775 {} \;');
            $this->line('  find storage bootstrap/cache -type f -exec chmod 664 {} \;');
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
