<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleAdsSbidService;
use App\Models\ProductMaster;
use App\Models\GoogleAdsCampaign;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;

class PauseGoogleShoppingAds extends Command
{
    protected $signature = 'google-shopping:pause-by-inventory {--dry-run : Run without actually pausing campaigns}';
    protected $description = 'Automatically pause Google Shopping campaigns when inventory (inv) = 0';

    protected $sbidService;

    public function __construct(GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
        $this->sbidService = $sbidService;
    }

    public function handle()
    {
        try {
            // Check database connection
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $dryRun = $this->option('dry-run');
            
            if ($dryRun) {
                $this->warn('⚠️  DRY RUN MODE - No campaigns will be paused');
            }
            
            $this->info('Starting inventory check for Google Shopping campaigns...');

            $customerId = env('GOOGLE_ADS_LOGIN_CUSTOMER_ID');
            if (empty($customerId)) {
                $this->error("✗ GOOGLE_ADS_LOGIN_CUSTOMER_ID is not configured");
                return 1;
            }
            $this->info("Customer ID: {$customerId}");

            // Fetch product masters (exclude soft deleted)
            $productMasters = ProductMaster::whereNull('deleted_at')
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->orderBy('parent', 'asc')
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                $this->warn("No product masters found!");
                DB::connection()->disconnect();
                return 0;
            }

            // Get all SKUs to fetch Shopify inventory data
            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->toArray();

            if (empty($skus)) {
                $this->warn("No valid SKUs found!");
                DB::connection()->disconnect();
                return 0;
            }

            $shopifyData = [];
            if (!empty($skus)) {
                $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            }
            
            DB::connection()->disconnect();

            $this->info("Found " . $productMasters->count() . " product masters");

            // Fetch SHOPPING campaigns that are currently ENABLED
            $googleCampaigns = GoogleAdsCampaign::select(
                    'campaign_id',
                    'campaign_name',
                    'campaign_status'
                )
                ->where('advertising_channel_type', 'SHOPPING')
                ->where('campaign_status', 'ENABLED')
                ->get()
                ->unique('campaign_id')
                ->values();

            $this->info("Found " . $googleCampaigns->count() . " enabled Google Shopping campaigns");

            $stats = [
                'total_checked' => 0,
                'zero_inventory' => 0,
                'paused' => 0,
                'already_paused' => 0,
                'no_matching_campaign' => 0,
                'errors' => 0,
            ];

            $pausedCampaigns = [];

            foreach ($productMasters as $pm) {
                $sku = strtoupper(trim($pm->sku));
                
                // Skip parent SKUs
                if (stripos($sku, 'PARENT') !== false) {
                    continue;
                }

                $stats['total_checked']++;

                // Check inventory
                $shopify = $shopifyData[$pm->sku] ?? null;
                $inv = $shopify ? ($shopify->inv ?? 0) : 0;

                // Only process if inventory is 0
                if ($inv > 0) {
                    continue;
                }

                $stats['zero_inventory']++;

                // Find matching campaign for this SKU
                $matchedCampaign = $googleCampaigns->first(function ($c) use ($sku) {
                    $campaign = strtoupper(trim($c->campaign_name));
                    $campaignCleaned = rtrim(trim($campaign), '.');
                    $skuTrimmed = strtoupper(trim($sku));
                    
                    // Check if SKU is in comma-separated list
                    $parts = array_map('trim', explode(',', $campaignCleaned));
                    $parts = array_map(function($part) {
                        return rtrim(trim($part), '.');
                    }, $parts);
                    $exactMatch = in_array($skuTrimmed, $parts);
                    
                    // If not in list, check if campaign name exactly equals SKU
                    if (!$exactMatch) {
                        $exactMatch = $campaignCleaned === $skuTrimmed;
                    }
                    
                    return $exactMatch && $c->campaign_status === 'ENABLED';
                });

                if (!$matchedCampaign) {
                    $stats['no_matching_campaign']++;
                    continue;
                }

                $campaignId = $matchedCampaign->campaign_id;
                $campaignName = $matchedCampaign->campaign_name;
                $campaignResourceName = "customers/{$customerId}/campaigns/{$campaignId}";

                // Check if already paused (shouldn't happen since we filtered for ENABLED, but double-check)
                if ($matchedCampaign->campaign_status !== 'ENABLED') {
                    $stats['already_paused']++;
                    continue;
                }

                if ($dryRun) {
                    $this->info("[DRY RUN] Would pause campaign: {$campaignName} (ID: {$campaignId}) - SKU: {$pm->sku} (INV: {$inv})");
                    $pausedCampaigns[] = [
                        'campaign_id' => $campaignId,
                        'campaign_name' => $campaignName,
                        'sku' => $pm->sku,
                        'inv' => $inv,
                    ];
                    $stats['paused']++;
                } else {
                    try {
                        $this->sbidService->pauseCampaign($customerId, $campaignResourceName);
                        
                        // Update database to reflect paused status
                        GoogleAdsCampaign::where('campaign_id', $campaignId)
                            ->update(['campaign_status' => 'PAUSED']);
                        
                        $this->info("✅ Paused campaign: {$campaignName} (ID: {$campaignId}) - SKU: {$pm->sku} (INV: {$inv})");
                        
                        $pausedCampaigns[] = [
                            'campaign_id' => $campaignId,
                            'campaign_name' => $campaignName,
                            'sku' => $pm->sku,
                            'inv' => $inv,
                            'paused_at' => now()->toDateTimeString(),
                        ];
                        $stats['paused']++;
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        $this->error("❌ Failed to pause campaign {$campaignId} ({$campaignName}): " . $e->getMessage());
                        Log::error("Failed to pause Google Shopping campaign", [
                            'campaign_id' => $campaignId,
                            'campaign_name' => $campaignName,
                            'sku' => $pm->sku,
                            'inv' => $inv,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            }

            // Print summary
            $this->info("\n" . str_repeat('=', 60));
            $this->info("Summary:");
            $this->info("  - Total SKUs checked: {$stats['total_checked']}");
            $this->info("  - Zero inventory found: {$stats['zero_inventory']}");
            $this->info("  - Campaigns " . ($dryRun ? "would be paused" : "paused") . ": {$stats['paused']}");
            $this->info("  - Already paused: {$stats['already_paused']}");
            $this->info("  - No matching campaign: {$stats['no_matching_campaign']}");
            $this->info("  - Errors: {$stats['errors']}");
            $this->info(str_repeat('=', 60));

            if ($dryRun) {
                $this->warn("\n⚠️  This was a DRY RUN. No campaigns were actually paused.");
                $this->info("Run without --dry-run to perform actual pauses.");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            Log::error("Error in PauseGoogleShoppingAds command", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }
}
