<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketingMaster\FacebookAddsManagerController;
use Illuminate\Console\Command;

class SyncMetaAllAds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meta:sync-all-ads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Meta All Ads data from Meta API (Facebook & Instagram - L30 and L7 data)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Meta All Ads sync from Meta API...');
        
        try {
            $controller = new FacebookAddsManagerController();
            $response = $controller->syncMetaAdsFromApi();
            
            $data = $response->getData();
            
            if (isset($data->success) && $data->success) {
                $this->info('✓ Sync completed successfully!');
                $this->line("  - L30 records synced: {$data->l30_synced}");
                $this->line("  - L7 records synced: {$data->l7_synced}");
                return 0;
            } else {
                $this->error('✗ Sync failed: ' . ($data->error ?? 'Unknown error'));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Error during sync: ' . $e->getMessage());
            return 1;
        }
    }
}
