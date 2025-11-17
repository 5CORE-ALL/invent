<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;

class AutoUpdateAmazonBgtKw extends Command
{
    protected $signature = 'amazon:auto-update-amz-bgt-kw';
    protected $description = 'Automatically update Amazon campaign bgt price';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info("Starting Amazon bgts auto-update...");

        $updateKwBgts = new AmazonACOSController;

        $campaigns = $this->amazonAcosKwControlData();

        if (empty($campaigns)) {
            $this->warn("No campaigns matched filter conditions.");
            return 0;
        }

        $campaignIds = collect($campaigns)->pluck('campaign_id')->toArray();
        $newBgts = collect($campaigns)->pluck('sbgt')->toArray();

        $result = $updateKwBgts->updateAutoAmazonCampaignBgt($campaignIds, $newBgts);
        $this->info("Update Result: " . json_encode($result));

    }

    public function amazonAcosKwControlData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        
        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            if (!$matchedCampaignL30) {
                continue;
            }

            // clicks must be >= 25
            // if (($matchedCampaignL30->clicks ?? 0) < 25) {
            //     continue;
            // }

            // Skip if INV = 0
            if (($shopify->inv ?? 0) == 0) {
                continue;
            }

            $row = [];
            $row['price']  = $amazonSheet->price ?? 0;
            $row['campaign_id'] = $matchedCampaignL30->campaign_id ?? '';

            $sales = $matchedCampaignL30->sales30d ?? 0;
            $spend = $matchedCampaignL30->spend ?? 0;

            if ($sales > 0) {
                $row['acos_L30'] = round(($spend / $sales) * 100, 2);
            } elseif ($spend > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }
            
            $tpft = 0;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) $tpft = isset($raw['TPFT']) ? (int) floor($raw['TPFT']) : 0;
            }
            $row['TPFT'] = $tpft;

            $acos = (float) ($row['acos_L30'] ?? 0);

            $price = (float) ($row['price'] ?? 0);

            $sbgt = round($price * 0.10, 0);

            if($sbgt > 10){
                $sbgt = 10;
            } else if($sbgt < 1){
                $sbgt = 1;
            }

            $row['sbgt'] = $sbgt;

            $result[] = (object) $row;
        }

        return $result;
    }

    // private function getDilColor($value)
    // {
    //     $percent = floatval($value) * 100;

    //     if ($percent < 16.66) {
    //         return 'red';
    //     } elseif ($percent >= 16.66 && $percent < 25) {
    //         return 'yellow';
    //     } elseif ($percent >= 25 && $percent < 50) {
    //         return 'green';
    //     } else {
    //         return 'pink';
    //     }
    // }

}