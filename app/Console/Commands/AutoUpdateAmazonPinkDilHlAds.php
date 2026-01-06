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

            // Check database connection
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
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

            $campaignIds = collect($campaigns)->pluck('campaign_id')->toArray();
            $newBgts = collect($campaigns)->pluck('sbgt')->toArray();

            $result = $updatePinkDilKwAds->updateAutoAmazonSbCampaignBgt($campaignIds, $newBgts);
            $this->info("Update Result: " . json_encode($result));
            
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::disconnect();
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

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['A_L30']  = $amazonSheet->units_ordered_l30 ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['sbgt'] = 1;

            if ($row['INV'] > 0 && !empty($row['campaignName'])) {
                $dilPercent = $row['INV'] > 0 ? (($row['A_L30'] / $row['INV']) * 100) : 0;
                if ($dilPercent > 50) {
                    $row['dilPercent'] = round($dilPercent, 2);
                    $result[] = (object) $row;
                }
            }
            }

            DB::disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getAmazonPinkDilHlAdsData: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::disconnect();
        }
    }

}