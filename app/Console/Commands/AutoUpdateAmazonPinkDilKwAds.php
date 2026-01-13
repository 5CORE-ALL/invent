<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonDatasheet;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;

class AutoUpdateAmazonPinkDilKwAds extends Command
{
    protected $signature = 'amazon:auto-update-pink-dil-kw-ads';
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

            $campaigns = $this->getAmazonPinkDilKwAdsData();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                return 0;
            }

            // Separate campaigns to pause vs update budget
            $campaignsToPause = [];
            $campaignsToUpdate = [];

            foreach ($campaigns as $campaign) {
                // Check if dil is pink (dilPercent > 50) AND ACOS > 30%
                if (($campaign->dilPercent ?? 0) > 50 && ($campaign->acos_L30 ?? 0) > 30) {
                    $campaignsToPause[] = $campaign->campaign_id;
                } else {
                    $campaignsToUpdate[] = $campaign;
                }
            }

            // Pause campaigns that meet the criteria
            if (!empty($campaignsToPause)) {
                $pauseResult = $updatePinkDilKwAds->pauseCampaigns($campaignsToPause);
                $this->info("Pause Result: " . json_encode($pauseResult));
            }

            // Update budget for campaigns that don't meet pause criteria
            if (!empty($campaignsToUpdate)) {
                $campaignIds = collect($campaignsToUpdate)->pluck('campaign_id')->toArray();
                $newBgts = collect($campaignsToUpdate)->pluck('sbgt')->toArray();

                $result = $updatePinkDilKwAds->updateAutoAmazonCampaignBgt($campaignIds, $newBgts);
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

    public function getAmazonPinkDilKwAdsData(){
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

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->whereIn('campaignName', $skus)
            ->where(function ($q) {
                $q->where('campaignName', 'NOT LIKE', '%PT%')
                ->where('campaignName', 'NOT LIKE', '%PT.%');
            })
            ->get();

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->whereIn('campaignName', $skus)
            ->where(function ($q) {
                $q->where('campaignName', 'NOT LIKE', '%PT%')
                ->where('campaignName', 'NOT LIKE', '%PT.%');
            })
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->whereIn('campaignName', $skus)
            ->where(function ($q) {
                $q->where('campaignName', 'NOT LIKE', '%PT%')
                ->where('campaignName', 'NOT LIKE', '%PT.%');
            })
            ->get();


        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->firstWhere('campaignName', $sku);
            $matchedCampaignL1 = $amazonSpCampaignReportsL1->firstWhere('campaignName', $sku);
            $campaignId = ($matchedCampaignL7 ? $matchedCampaignL7->campaign_id : null) ?? ($matchedCampaignL1 ? $matchedCampaignL1->campaign_id : '');
            
            // Get L30 data for ACOS calculation
            $matchedCampaignL30 = !empty($campaignId) ? $amazonSpCampaignReportsL30->firstWhere('campaign_id', $campaignId) : null;

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
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
            $this->error("Error in getAmazonPinkDilKwAdsData: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

}