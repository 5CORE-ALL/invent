<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AmazonSpApiService;
use Illuminate\Support\Facades\Log;

class SyncAmazonInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amazon:sync-inventory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Amazon inventory data from SP-API to database (product_stock_mappings and amazon_datasheets tables)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Amazon inventory sync...');
        $this->info('This will fetch data from Amazon SP-API and update both:');
        $this->info('  - product_stock_mappings table');
        $this->info('  - amazon_datasheets table');
        $this->newLine();

        try {
            $service = new AmazonSpApiService();
            
            $this->info('Requesting inventory report from Amazon...');
            $service->getinventory();
            
            $this->newLine();
            $this->info('✅ Amazon inventory sync completed successfully!');
            $this->info('📊 Both tables have been updated with fresh inventory data.');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error syncing Amazon inventory: ' . $e->getMessage());
            Log::error('Amazon inventory sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}
