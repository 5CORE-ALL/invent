<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonDatasheet;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;

class AutoUpdateAmazonPinkDilPtAds extends Command
{
    protected $signature = 'amazon:auto-update-pink-dil-pt-ads {--dry-run : Run without making changes}';
    protected $description = 'Automatically update Amazon campaign pink dil bgt';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $dryRun = $this->option('dry-run');
            if ($dryRun) {
                $this->warn("=== DRY RUN MODE - No changes will be made ===");
            }
            $this->info("Starting Amazon bgts auto-update...");

            // Check database connection (without creating persistent connection)
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                // Immediately disconnect after check to prevent connection buildup
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $updatePinkDilKwAds = new AmazonACOSController;

            $campaigns = $this->getAmazonPinkDilPtAdsData();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                return 0;
            }

            // Separate campaigns to pause vs update budget
            $campaignsToPause = [];
            $campaignsToUpdate = [];

            foreach ($campaigns as $campaign) {
                // Check if (dil is pink (dilPercent > 50) AND ACOS > 20%) OR (ratings < 3.5) OR (price < 10 AND units_ordered_l30 > 0)
                $rating = isset($campaign->rating) && $campaign->rating !== null ? (float) $campaign->rating : null;
                $price = isset($campaign->price) ? (float) $campaign->price : null;
                $unitsL30 = $campaign->OV_L30 ?? 0; // Use OV L30 instead of A_L30
                $dilPercent = $campaign->dilPercent ?? 0;
                $acos = $campaign->acos_L30 ?? 0;
                
                // Check each condition separately for detailed logging
                $condition1 = ($dilPercent > 50 && $acos > 20); // Pink dil AND ACOS > 20%
                $condition2 = ($rating !== null && $rating < 3.5); // Rating < 3.5
                $condition3 = ($price < 10 && $unitsL30 > 0); // Price < 10 AND units > 0
                
                $shouldPause = $condition1 || $condition2 || $condition3;
                
                if ($shouldPause) {
                    // Determine which condition(s) triggered the pause
                    $pauseReasons = [];
                    if ($condition1) {
                        $pauseReasons[] = "Dil Pink ($dilPercent% > 50%) AND ACOS High ($acos% > 20%)";
                    }
                    if ($condition2) {
                        $pauseReasons[] = "Low Rating ($rating < 3.5)";
                    }
                    if ($condition3) {
                        $pauseReasons[] = "Low Price (\$$price < \$10) AND Units Ordered ($unitsL30 > 0)";
                    }
                    
                    $reason = implode(" OR ", $pauseReasons);
                    
                    // Log pause details with reason
                    $this->info(sprintf(
                        "PAUSE: Campaign: %s | Dil: %.2f%% | ACOS: %.2f%% | Rating: %s | Price: $%.2f | Units L30: %d | Reason: %s | Status: PAUSED",
                        $campaign->campaignName ?? 'N/A',
                        $dilPercent,
                        $acos,
                        $rating !== null ? number_format($rating, 2) : 'N/A',
                        $price ?? 0,
                        $unitsL30,
                        $reason
                    ));
                    $campaignsToPause[] = $campaign->campaign_id;
                } else {
                    $campaignsToUpdate[] = $campaign;
                }
            }

            // Pause campaigns that meet the criteria
            if (!empty($campaignsToPause)) {
                $pauseCount = count($campaignsToPause);
                if ($dryRun) {
                    $this->warn(sprintf("DRY RUN: Would pause %d campaigns", $pauseCount));
                    $this->info(sprintf("=== TOTAL PAUSED CAMPAIGNS: %d ===", $pauseCount));
                } else {
                    $pauseResult = $updatePinkDilKwAds->pauseCampaigns($campaignsToPause);
                    $this->info("Pause Result: " . json_encode($pauseResult));
                    $this->info(sprintf("=== TOTAL PAUSED CAMPAIGNS: %d ===", $pauseCount));
                }
            } else {
                $this->info("=== TOTAL PAUSED CAMPAIGNS: 0 ===");
            }

            // Update budget for campaigns that don't meet pause criteria
            if (!empty($campaignsToUpdate)) {
                // Log budget update details
                foreach ($campaignsToUpdate as $campaign) {
                    $this->info(sprintf(
                        "BGT UPDATE: Campaign: %s | Old BGT: $%.2f | New BGT: $%.2f",
                        $campaign->campaignName ?? 'N/A',
                        $campaign->campaignBudgetAmount ?? 0,
                        $campaign->sbgt ?? 0
                    ));
                }
                
                if ($dryRun) {
                    $this->warn(sprintf("DRY RUN: Would update budget for %d campaigns", count($campaignsToUpdate)));
                } else {
                    $campaignIds = collect($campaignsToUpdate)->pluck('campaign_id')->toArray();
                    $newBgts = collect($campaignsToUpdate)->pluck('sbgt')->toArray();

                    $updateResult = $updatePinkDilKwAds->updateAutoAmazonCampaignBgt($campaignIds, $newBgts);
                    $this->info("Update Result: " . json_encode($updateResult));
                }
            }
            
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getAmazonPinkDilPtAdsData(){
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                $this->warn("No product masters found in database!");
                return [];
            }

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            if (empty($skus)) {
                $this->warn("No valid SKUs found!");
                return [];
            }

            $amazonDatasheetsBySku = [];
            $shopifyData = [];

            if (!empty($skus)) {
                $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
                $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
            }

            // Fetch latest ratings from junglescout_product_data (same logic as getAmazonUtilizedAdsData)
            $junglescoutData = collect();
            
            // Get latest by SKU
            $skuRatings = DB::table('junglescout_product_data as j1')
                ->select('j1.sku', 'j1.data')
                ->whereNotNull('j1.sku')
                ->whereIn('j1.sku', $skus)
                ->whereRaw('j1.updated_at = (SELECT MAX(j2.updated_at) FROM junglescout_product_data j2 WHERE j2.sku = j1.sku)')
                ->get();
            
            foreach ($skuRatings as $item) {
                $data = json_decode($item->data, true);
                $rating = $data['rating'] ?? null;
                if ($item->sku && !$junglescoutData->has($item->sku)) {
                    $junglescoutData->put($item->sku, $rating);
                }
            }
            
            // Get latest by parent as fallback
            $parentRatings = DB::table('junglescout_product_data as j1')
                ->select('j1.parent', 'j1.data')
                ->whereNotNull('j1.parent')
                ->whereIn('j1.parent', $skus)
                ->whereRaw('j1.updated_at = (SELECT MAX(j2.updated_at) FROM junglescout_product_data j2 WHERE j2.parent = j1.parent)')
                ->get();
            
            foreach ($parentRatings as $item) {
                $data = json_decode($item->data, true);
                $rating = $data['rating'] ?? null;
                if ($item->parent && !$junglescoutData->has($item->parent)) {
                    $junglescoutData->put($item->parent, $rating);
                }
            }

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();


        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            // Match campaigns using same logic as utilized pages (getAmazonUtilizedAdsData for PT)
            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                // Normalize campaign name: replace non-breaking spaces and multiple spaces (same as utilized pages)
                $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                $cleanName = strtoupper(trim($campaignName));
                
                // Check both with space and without space before PT (same as utilized pages)
                return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.' || 
                        $cleanName === $sku . 'PT' || $cleanName === $sku . 'PT.');
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                // Normalize campaign name: replace non-breaking spaces and multiple spaces (same as utilized pages)
                $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                $cleanName = strtoupper(trim($campaignName));
                
                // Check both with space and without space before PT (same as utilized pages)
                return ($cleanName === $sku . ' PT' || $cleanName === $sku . ' PT.' || 
                        $cleanName === $sku . 'PT' || $cleanName === $sku . 'PT.');
            });

            $campaignId = ($matchedCampaignL7 ? $matchedCampaignL7->campaign_id : null) ?? ($matchedCampaignL1 ? $matchedCampaignL1->campaign_id : '');
            
            // Skip if no campaign found
            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            // Get L30 data for ACOS calculation
            $matchedCampaignL30 = null;
            if (!empty($campaignId)) {
                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($campaignId) {
                    return ($item->campaign_id ?? '') === $campaignId;
                });
            }

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['OV_L30'] = $shopify->quantity ?? 0; // OV L30 (overall L30 from Shopify)
            $row['price']  = $amazonSheet->price ?? null;
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
            $row['rating'] = $junglescoutData[$pm->sku] ?? null;
            $row['sbgt'] = 1;

            // Process ALL campaigns, not just pink dil ones
            // We need to check for pause conditions: (dilPercent > 50 AND ACOS > 35%) OR (rating < 3.5) OR (price < 10 AND units_ordered_l30 > 0)
            if (!empty($row['campaignName'])) {
                // Use OV L30 (overall L30) instead of A_L30 for dilPercent calculation
                $dilPercent = $row['INV'] > 0 ? (($row['OV_L30'] / $row['INV']) * 100) : 0;
                
                // Calculate ACOS from L30 data
                $sales = $matchedCampaignL30 ? ($matchedCampaignL30->sales30d ?? 0) : 0;
                $spend = $matchedCampaignL30 ? ($matchedCampaignL30->spend ?? 0) : 0;
                
                if ($sales > 0) {
                    $row['acos_L30'] = round(($spend / $sales) * 100, 2);
                } elseif ($spend > 0) {
                    $row['acos_L30'] = 100;
                } else {
                    $row['acos_L30'] = 0;
                }
                
                // Set dilPercent for all campaigns
                $row['dilPercent'] = round($dilPercent, 2);
                
                $result[] = (object) $row;
            }
            }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getAmazonPinkDilPtAdsData: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

}