<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketingMaster\FacebookAddsManagerController;
use App\Models\MetaAdRawData;
use App\Services\MetaApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMetaAllAds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meta:sync-all-ads {--skip-raw : Skip saving raw ads data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Meta All Ads data from Meta API (Facebook & Instagram - L30 and L7 data) and save raw ads data daily';

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
                $this->info('✓ Campaign sync completed successfully!');
                $this->line("  - L30 records synced: {$data->l30_synced}");
                $this->line("  - L7 records synced: {$data->l7_synced}");
                
                // Save raw ads data if not skipped
                if (!$this->option('skip-raw')) {
                    $this->info('Saving raw ads data...');
                    $rawAdsCount = $this->saveRawAdsData();
                    $this->info("  - Raw ads saved: {$rawAdsCount}");
                }
                
                return 0;
            } else {
                $this->error('✗ Sync failed: ' . ($data->error ?? 'Unknown error'));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Error during sync: ' . $e->getMessage());
            Log::error('SyncMetaAllAds Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Save raw ads data to database
     * 
     * @return int Number of ads saved
     */
    private function saveRawAdsData(): int
    {
        try {
            $metaApi = new MetaApiService();
            $rawAds = $metaApi->fetchRawAdsData();
            
            $syncDate = now()->toDateString();
            $saved = 0;
            
            foreach ($rawAds as $ad) {
                $adId = $ad['id'] ?? null;
                if (!$adId) {
                    continue;
                }
                
                // Extract campaign info from ad data
                $campaignId = $ad['campaign_id'] ?? null;
                $campaignName = $ad['campaign_name'] ?? null;
                
                // If campaign_name is not in ad, try to get it from campaign_id
                if (!$campaignName && $campaignId) {
                    // We can optionally fetch campaign name, but for now just use what we have
                }
                
                // Parse dates if available
                $createdTime = null;
                $updatedTime = null;
                
                if (isset($ad['created_time'])) {
                    try {
                        $createdTime = \Carbon\Carbon::parse($ad['created_time']);
                    } catch (\Exception $e) {
                        // Invalid date, skip
                    }
                }
                
                if (isset($ad['updated_time'])) {
                    try {
                        $updatedTime = \Carbon\Carbon::parse($ad['updated_time']);
                    } catch (\Exception $e) {
                        // Invalid date, skip
                    }
                }
                
                // Extract creative data if it exists
                $creativeData = null;
                if (isset($ad['creative'])) {
                    $creativeData = is_array($ad['creative']) ? $ad['creative'] : json_decode($ad['creative'], true);
                }
                
                MetaAdRawData::updateOrCreate(
                    [
                        'ad_id' => $adId,
                        'sync_date' => $syncDate, // This allows daily snapshots
                    ],
                    [
                        'ad_name' => $ad['name'] ?? null,
                        'campaign_id' => $campaignId,
                        'campaign_name' => $campaignName,
                        'adset_id' => $ad['adset_id'] ?? null,
                        'status' => strtolower($ad['status'] ?? 'unknown'),
                        'effective_object_story_id' => $ad['effective_object_story_id'] ?? null,
                        'preview_shareable_link' => $ad['preview_shareable_link'] ?? null,
                        'source_ad_id' => $ad['source_ad_id'] ?? null,
                        'creative_data' => $creativeData,
                        'ad_created_time' => $createdTime,
                        'ad_updated_time' => $updatedTime,
                        'raw_data' => $ad, // Store complete raw response
                    ]
                );
                
                $saved++;
            }
            
            Log::info('Raw Ads Data Saved', [
                'sync_date' => $syncDate,
                'ads_saved' => $saved,
            ]);
            
            return $saved;
        } catch (\Exception $e) {
            Log::error('Save Raw Ads Data Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->warn('Warning: Failed to save raw ads data: ' . $e->getMessage());
            return 0;
        }
    }
}
