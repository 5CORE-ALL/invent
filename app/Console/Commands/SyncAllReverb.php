<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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

        Cache::put('reverb_sync_running', true, 7200);

        try {
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

            // Step 3: Dispatch pending import jobs only after sync completes
            $this->info('📤 Step 3: Dispatching pending order import jobs...');
            $this->call('reverb:process-pending', ['--limit' => 1000]);

            if ($orderExitCode === 0 && $inventoryExitCode === 0) {
                $this->info("✅ Complete sync finished successfully in {$duration} seconds!");
                return self::SUCCESS;
            }

            $this->warn("⚠️ Sync completed with warnings in {$duration} seconds");
            return self::FAILURE;
        } finally {
            Cache::forget('reverb_sync_running');
            $this->info('Sync lock released. Reverb import jobs can now process.');
        }
    }
}
