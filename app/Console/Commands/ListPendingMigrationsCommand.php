<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ListPendingMigrationsCommand extends Command
{
    protected $signature = 'migrate:list-pending';

    protected $description = 'Print migration basenames that are not in the migrations table (same queue `php artisan migrate` will run next)';

    public function handle(): int
    {
        $dir = database_path('migrations');
        $files = File::glob($dir.DIRECTORY_SEPARATOR.'*.php');
        sort($files);

        $ran = DB::table('migrations')->pluck('migration')->all();
        $ran = array_flip($ran);

        $pending = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (! isset($ran[$name])) {
                $pending[] = $name;
            }
        }

        if ($pending === []) {
            $this->info('No pending migrations.');

            return self::SUCCESS;
        }

        $this->info('Pending migrations ('.count($pending).'):');
        foreach ($pending as $name) {
            $this->line($name);
        }

        return self::SUCCESS;
    }
}
