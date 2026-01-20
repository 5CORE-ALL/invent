<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonSbCampaignReport;
use Illuminate\Console\Command;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;

class AutoUpdateAmazonPinkDilHlAds extends Command
{
    protected $signature = 'amazon:auto-update-pink-dil-hl-ads';
    protected $description = 'Automatically update Amazon campaign pink dil bgt';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
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

            $campaigns = $this->getAmazonPinkDilHlAdsData();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                return 0;
            }

            // Separate campaigns to pause vs update budget
            $campaignsToPause = [];
            $campaignsToUpdate = [];

            foreach ($campaigns as $campaign) {
                // Check if (dil is pink (dilPercent > 50) AND ACOS > 20%) OR (ratings < 3.5)
                // Note: No price condition for HL campaigns
                $rating = isset($campaign->rating) && $campaign->rating !== null ? (float) $campaign->rating : null;
                $shouldPause = (($campaign->dilPercent ?? 0) > 50 && ($campaign->acos_L30 ?? 0) > 20) || ($rating !== null && $rating < 3.5);
                if ($shouldPause) {
                    $campaignsToPause[] = $campaign->campaign_id;
                } else {
                    $campaignsToUpdate[] = $campaign;
                }
            }

            // Pause campaigns that meet the criteria
            if (!empty($campaignsToPause)) {
                $pauseResult = $updatePinkDilKwAds->pauseSbCampaigns($campaignsToPause);
                $this->info("Pause Result: " . json_encode($pauseResult));
            }

            // Update budget for campaigns that don't meet pause criteria
            if (!empty($campaignsToUpdate)) {
                $campaignIds = collect($campaignsToUpdate)->pluck('campaign_id')->toArray();
                $newBgts = collect($campaignsToUpdate)->pluck('sbgt')->toArray();

                $result = $updatePinkDilKwAds->updateAutoAmazonSbCampaignBgt($campaignIds, $newBgts);
                $this->info("Update Result: " . json_encode($result));
            }
            
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getAmazonPinkDilHlAdsData(){
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

        $amazonSpCampaignReportsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
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

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                $expected1 = $sku;                
                $expected2 = $sku . ' HEAD';      

                return ($cleanName === $expected1 || $cleanName === $expected2)
                    && strtoupper($item->campaignStatus) === 'ENABLED';
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $cleanName = strtoupper(trim($item->campaignName));
                $expected1 = $sku;
                $expected2 = $sku . ' HEAD';

                return ($cleanName === $expected1 || $cleanName === $expected2)
                    && strtoupper($item->campaignStatus) === 'ENABLED';
            });


            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            
            // Get L30 data for ACOS calculation
            $matchedCampaignL30 = !empty($campaignId) ? $amazonSpCampaignReportsL30->first(function ($item) use ($campaignId) {
                return ($item->campaign_id ?? '') === $campaignId;
            }) : null;

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['rating'] = $junglescoutData[$pm->sku] ?? null;
            $row['sbgt'] = 1;

            if ($row['INV'] > 0 && !empty($row['campaignName'])) {
                $dilPercent = $row['INV'] > 0 ? (($row['A_L30'] / $row['INV']) * 100) : 0;
                if ($dilPercent > 50) {
                    $row['dilPercent'] = round($dilPercent, 2);
                    
                    // Calculate ACOS from L30 data
                    $sales = $matchedCampaignL30->sales30d ?? 0;
                    $spend = $matchedCampaignL30->spend ?? 0;
                    
                    if ($sales > 0) {
                        $row['acos_L30'] = round(($spend / $sales) * 100, 2);
                    } elseif ($spend > 0) {
                        $row['acos_L30'] = 100;
                    } else {
                        $row['acos_L30'] = 0;
                    }
                    
                    $result[] = (object) $row;
                }
            }
            }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getAmazonPinkDilHlAdsData: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

}