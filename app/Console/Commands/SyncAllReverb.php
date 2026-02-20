<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncAllReverb extends Command
{
    protected $signature = 'reverb:sync-all
                           {--force : Force full sync instead of incremental}
                           {--all-listings : Sync all listings including inactive}';

    protected $description = 'Fetch Reverb orders and sync inventory from Shopify to Reverb in one command';

    public function handle(): int
    {
        $startTime = microtime(true);
        $this->info('🚀 Starting complete Reverb sync...');

        // Step 1: Fetch Orders
        $this->info('📦 Step 1: Fetching Reverb orders...');
        $orderExitCode = $this->call('reverb:fetch', [
            '--force' => $this->option('force'),
        ]);

        if ($orderExitCode !== 0) {
            $this->warn('⚠️ Order fetch had issues, but continuing with inventory sync...');
        }

        // Step 2: Sync Inventory
        $this->info('🔄 Step 2: Syncing inventory from Shopify to Reverb...');
        $inventoryExitCode = $this->call('reverb:sync-inventory-from-shopify', [
            '--all' => $this->option('all-listings'),
        ]);

        $duration = round(microtime(true) - $startTime, 2);

        if ($orderExitCode === 0 && $inventoryExitCode === 0) {
            $this->info("✅ Complete sync finished successfully in {$duration} seconds!");
            return self::SUCCESS;
        }

        $this->warn("⚠️ Sync completed with warnings in {$duration} seconds");
        return self::FAILURE;
    }
}
