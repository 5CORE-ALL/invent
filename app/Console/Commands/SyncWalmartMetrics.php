<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncWalmartMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:walmart-metrics-data {--chunk= : Override DB write chunk size (default from cron-monitor config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Confirm Walmart metrics already live in local walmart_metrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! Schema::hasTable('walmart_metrics')) {
            $this->warn('walmart_metrics table not found.');

            return 0;
        }

        $count = DB::table('walmart_metrics')->count();
        $this->info("Walmart metrics already live in walmart_metrics ({$count} row(s)).");

        return 0;
    }
}
