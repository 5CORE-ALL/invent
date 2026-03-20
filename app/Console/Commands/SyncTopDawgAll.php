<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncTopDawgAll extends Command
{
    protected $signature = 'topdawg:sync-all {--force : Force full fetch}';

    protected $description = 'Fetch TopDawg orders and products (no Shopify integration)';

    public function handle(): int
    {
        $startTime = microtime(true);
        $this->info('Starting TopDawg sync...');

        $exitCode = $this->call('topdawg:fetch', [
            '--force' => $this->option('force'),
        ]);

        $duration = round(microtime(true) - $startTime, 2);
        if ($exitCode === 0) {
            $this->info("TopDawg sync finished in {$duration} seconds.");
        } else {
            $this->warn("TopDawg sync completed with issues in {$duration} seconds.");
        }
        return $exitCode;
    }
}
