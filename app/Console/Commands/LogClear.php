<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LogClear extends Command
{
    protected $signature = 'log:clear';
    protected $description = 'Clear the Laravel log file';

    public function handle()
    {
        $log = storage_path('logs/laravel.log');
        if (file_exists($log)) {
            file_put_contents($log, '');
            $this->info('Log file cleared!');
        } else {
            $this->info('Log file does not exist.');
        }
    }
}