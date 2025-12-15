<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AutoUpdateAmazonBgtPt extends Command
{
    protected $signature = 'amazon:auto-update-amz-bgt-pt';
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

        $campaigns = $this->amazonAcosPtControlData();

        if (empty($campaigns)) {
            $this->warn("No campaigns matched filter conditions.");
            return 0;
        }

        // Filter out campaigns with empty/null campaign_id or invalid sbgt
        $validCampaigns = collect($campaigns)->filter(function ($campaign) {
            return !empty($campaign->campaign_id) && isset($campaign->sbgt) && $campaign->sbgt > 0;
        })->values();

        if ($validCampaigns->isEmpty()) {
            $this->warn("No valid campaigns found (all have empty campaign_id or invalid budget).");
            return 0;
        }

        $campaignIds = $validCampaigns->pluck('campaign_id')->toArray();
        $newBgts = $validCampaigns->pluck('sbgt')->toArray();

        // Ensure both arrays have the same length
        if (count($campaignIds) !== count($newBgts)) {
            $this->error("Error: Campaign IDs and budgets arrays have different lengths!");
            return 1;
        }

        try {
            $result = $updateKwBgts->updateAutoAmazonCampaignBgt($campaignIds, $newBgts);
            
            // Show only campaign name and new budget for valid campaigns
            $simplifiedResult = $validCampaigns->map(function ($campaign) {
                return [
                    'campaignName' => $campaign->campaignName ?? '',
                    'newBudget' => $campaign->sbgt ?? 0
                ];
            })->toArray();
            
            $this->info("Update Result: " . json_encode($simplifiedResult));
            
            if (isset($result['status']) && $result['status'] !== 200) {
                $this->error("Budget update failed: " . ($result['message'] ?? 'Unknown error'));
                return 1;
            }
            
            $this->info("Successfully updated " . count($campaignIds) . " campaign budgets.");
            
        } catch (\Exception $e) {
            $this->error("Error updating campaign budgets: " . $e->getMessage());
            return 1;
        }

    }

    public function amazonAcosPtControlData()
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
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];
        $totalSpend = 0;
        $totalSales = 0;

        // First pass: collect all data and calculate totals
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL30 = $this->matchCampaign($sku, $amazonSpCampaignReportsL30);

            if (!$matchedCampaignL30) {
                continue;
            }

            // Skip if INV = 0
            if (($shopify->inv ?? 0) == 0) {
                continue;
            }

            $sales = $matchedCampaignL30->sales30d ?? 0;
            $spend = $matchedCampaignL30->spend ?? 0;

            $totalSpend += $spend;
            $totalSales += $sales;
        }

        // Calculate total ACOS
        $totalACOS = $totalSales > 0 ? ($totalSpend / $totalSales) * 100 : 0;

        // Second pass: calculate sbgt with new rule
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL30 = $this->matchCampaign($sku, $amazonSpCampaignReportsL30);

            if (!$matchedCampaignL30) {
                continue;
            }

            // Skip if INV = 0
            if (($shopify->inv ?? 0) == 0) {
                continue;
            }

            $row = [];
            $row['INV']         = $shopify->inv ?? 0;
            $row['price']  = $amazonSheet->price ?? 0;
            $row['campaign_id'] = $matchedCampaignL30->campaign_id ?? '';
            $row['campaignName'] = $matchedCampaignL30->campaignName ?? '';

            $sales = $matchedCampaignL30->sales30d ?? 0;
            $spend = $matchedCampaignL30->spend ?? 0;

            if ($sales > 0) {
                $row['acos_L30'] = round(($spend / $sales) * 100, 2);
            } elseif ($spend > 0) {
                $row['acos_L30'] = 100;
            } else {
                $row['acos_L30'] = 0;
            }

            $acos = (float) ($row['acos_L30'] ?? 0);

            $tpft = 0;
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) $tpft = isset($raw['TPFT']) ? (int) floor($raw['TPFT']) : 0;
            }
            $row['TPFT'] = $tpft;

            $acos = (float) ($row['acos_L30'] ?? 0);

            $price = (float) ($row['price'] ?? 0);

            // New rule: if acos_L30 > total_acos, then sbgt = 1, otherwise old formula
            if ($totalACOS > 0 && $acos > $totalACOS) {
                $sbgt = 1;
            } else {
                // Old ACOS-based sbgt rule
                if ($acos < 10) {
                    $sbgt = 5;
                } elseif ($acos < 20) {
                    $sbgt = 4;
                } elseif ($acos < 30) {
                    $sbgt = 3;
                } elseif ($acos < 40) {
                    $sbgt = 2;
                } else {
                    $sbgt = 1;
                }
            }
            $row['sbgt'] = $sbgt;

            $result[] = (object) $row;
        }

        return $result;
    }

    function matchCampaign($sku, $campaignReports) {
        $skuClean = preg_replace('/\s+/', ' ', strtoupper(trim($sku)));

        $expected1 = $skuClean . ' PT';
        $expected2 = $skuClean . ' PT.';

        return $campaignReports->first(function ($item) use ($expected1, $expected2) {
            $campaignName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));

            return in_array($campaignName, [$expected1, $expected2], true)
                && strtoupper($item->campaignStatus) === 'ENABLED';
        });
    }
}