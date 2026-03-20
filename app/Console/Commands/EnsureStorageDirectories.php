<?php

namespace App\Console\Commands;

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

        foreach ($dirs as $dir) {
            $exists = is_dir($dir);
            $writable = $exists && is_writable($dir);

            if (!$exists) {
                if ($fix) {
                    if (File::makeDirectory($dir, 0755, true)) {
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
            $this->info('Run with --fix to create missing directories:');
            $this->line('  php artisan storage:ensure --fix');
            $this->newLine();
            $this->comment('On the server you may also need to fix ownership (replace www-data with your web server user):');
            $this->line('  chown -R www-data:www-data storage bootstrap/cache');
            $this->line('  chmod -R 775 storage bootstrap/cache');
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
