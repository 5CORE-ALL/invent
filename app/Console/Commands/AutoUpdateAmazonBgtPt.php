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
use Illuminate\Support\Facades\DB;

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

            $updateKwBgts = new AmazonACOSController;

            $campaigns = $this->amazonAcosPtControlData();

            // Close connection after data fetching
            DB::connection()->disconnect();

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
                
                // Show detailed campaign information for verification
                $this->info("\n========================================");
                $this->info("CAMPAIGN BUDGET UPDATE SUMMARY (PT)");
                $this->info("========================================\n");
                
                foreach ($validCampaigns as $campaign) {
                    $this->info("Campaign: " . ($campaign->campaignName ?? 'N/A'));
                    $this->info("  Price: $" . number_format($campaign->price ?? 0, 2));
                    $this->info("  ACOS: " . number_format($campaign->acos_L30 ?? 0, 2) . "%");
                    $this->info("  New Budget: $" . ($campaign->sbgt ?? 0));
                    $this->info("  Campaign ID: " . ($campaign->campaign_id ?? 'N/A'));
                    $this->info("---");
                }
                
                $this->info("\nTotal Campaigns: " . count($campaignIds));
                $this->info("========================================\n");
                
                if (isset($result['status']) && $result['status'] !== 200) {
                    $this->error("Budget update failed: " . ($result['message'] ?? 'Unknown error'));
                    return 1;
                }
                
                $this->info("Successfully prepared " . count($campaignIds) . " campaign budgets for update.");
                
            } catch (\Exception $e) {
                $this->error("Error updating campaign budgets: " . $e->getMessage());
                return 1;
            }

        } finally {
            // Ensure connection is closed
            DB::connection()->disconnect();
        }

        return 0;
    }

    public function amazonAcosPtControlData()
    {
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            // Return empty array if no SKUs found
            if (empty($skus)) {
                return [];
            }

            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');
            
            $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku ?? '');
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
                $sku = strtoupper($pm->sku ?? '');

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

                // Skip if campaign_id is empty
                if (empty($matchedCampaignL30->campaign_id)) {
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
                $sku = strtoupper($pm->sku ?? '');

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

                // Skip if campaign_id is empty
                if (empty($matchedCampaignL30->campaign_id)) {
                    continue;
                }

                $row = [];
                $row['INV']         = $shopify->inv ?? 0;
                $row['price']  = $amazonSheet->price ?? 0;
                $row['campaign_id'] = $matchedCampaignL30->campaign_id ?? '';
                $row['campaignName'] = $matchedCampaignL30->campaignName ?? '';

                $sales = $matchedCampaignL30->sales30d ?? 0;
                $spend = $matchedCampaignL30->spend ?? 0;
                $row['units_ordered_l30'] = $amazonSheet->units_ordered_l30 ?? 0;

                if ($spend > 0 && $sales > 0) {
                    $row['acos_L30'] = round(($spend / $sales) * 100, 2);
                } elseif ($spend > 0 && $sales == 0) {
                    $row['acos_L30'] = 100;
                } else {
                    $row['acos_L30'] = 0;
                }

                $acos = (float) ($row['acos_L30'] ?? 0);

                $tpft = 0;
                $nra = '';
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) $raw = json_decode($raw, true);
                    if (is_array($raw)) {
                        $tpft = isset($raw['TPFT']) ? (int) floor($raw['TPFT']) : 0;
                        $nra = $raw['NRA'] ?? '';
                    }
                }
                $row['TPFT'] = $tpft;

                // Skip if NRA === 'NRA' (matching frontend filter)
                if ($nra === 'NRA') {
                    continue;
                }

                $acos = (float) ($row['acos_L30'] ?? 0);

                $price = (float) ($row['price'] ?? 0);

                // New sbgt rule: Budget = 10% of price (rounded up)
                // BUT if ACOS > 20%, then budget = $1
                if ($acos > 20) {
                    $row['sbgt'] = 1;
                } else {
                    $row['sbgt'] = ceil($price * 0.10);
                }

                $result[] = (object) $row;
            }

        return $result;
        } catch (\Exception $e) {
            Log::error("Error in amazonAcosPtControlData: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
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