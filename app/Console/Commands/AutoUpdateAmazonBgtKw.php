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
use Illuminate\Support\Facades\DB;

class AutoUpdateAmazonBgtKw extends Command
{
    protected $signature = 'amazon:auto-update-amz-bgt-kw {--dry-run : Run without updating Amazon (test only)}';
    protected $description = 'Automatically update Amazon campaign bgt price';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $dryRun = $this->option('dry-run');
            $this->info($dryRun ? "Starting Amazon bgts auto-update (DRY RUN - no updates will be made)..." : "Starting Amazon bgts auto-update...");

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

            $campaigns = $this->amazonAcosKwControlData();

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
                // Show detailed campaign information for verification (before update)
                $this->info("\n========================================");
                $this->info($dryRun ? "CAMPAIGN BUDGET UPDATE SUMMARY (KW) [DRY RUN]" : "CAMPAIGN BUDGET UPDATE SUMMARY (KW)");
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
                
                if ($dryRun) {
                    $this->warn("DRY RUN - No updates were made to Amazon.");
                    $this->info("Run without --dry-run to apply budget updates.");
                } else {
                    $result = $updateKwBgts->updateAutoAmazonCampaignBgt($campaignIds, $newBgts);
                    if (isset($result['status']) && $result['status'] !== 200) {
                        $this->error("Budget update failed: " . ($result['message'] ?? 'Unknown error'));
                        return 1;
                    }
                    $this->info("Successfully updated " . count($campaignIds) . " campaign budgets.");
                }
                
            } catch (\Exception $e) {
                $this->error("Error updating campaign budgets: " . $e->getMessage());
                return 1;
            }

        } finally {
            // Ensure connection is closed
            try {
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                // Ignore disconnect errors
            }
        }

        return 0;
    }

    public function amazonAcosKwControlData()
    {
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            // Return empty array if no SKUs found
            if (empty($skus)) {
                DB::connection()->disconnect();
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
                ->where('campaignName', 'NOT LIKE', '% PT')
                ->where('campaignName', 'NOT LIKE', '% PT.')
                ->whereRaw("UPPER(campaignStatus) = 'ENABLED'")
                ->get();

            // Disconnect after query
            DB::connection()->disconnect();

            // For PARENT rows: INV = sum of child SKUs' INV (same as AmazonSpBudgetController)
            $childInvSumByParent = [];
            foreach ($productMasters as $pm) {
                $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku ?? '');
                $norm = preg_replace('/\s+/', ' ', $norm);
                $skuUpper = strtoupper(trim($norm));
                if (stripos($skuUpper, 'PARENT') !== false) {
                    continue;
                }
                $p = $pm->parent ?? '';
                if ($p === '') {
                    continue;
                }
                $shopifyChild = $shopifyData[$pm->sku] ?? null;
                $inv = ($shopifyChild && isset($shopifyChild->inv)) ? (int) $shopifyChild->inv : 0;
                if (!isset($childInvSumByParent[$p])) {
                    $childInvSumByParent[$p] = 0;
                }
                $childInvSumByParent[$p] += $inv;
            }

            $result = [];
            $totalSpend = 0;
            $totalSales = 0;
            $validCampaignsForTotal = []; // Store valid campaigns for total ACOS calculation

            // Single pass: collect all valid campaigns and calculate totals
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku ?? '');

                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$pm->sku] ?? null;

                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $campaignName = strtoupper(trim(rtrim($item->campaignName ?? '', '.')));
                    $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                    return $campaignName === $cleanSku;
                });

                if (!$matchedCampaignL30) {
                    continue;
                }

                // INV: for PARENT rows use sum of children's INV; for child rows use shopify inv
                $inv = (stripos($sku, 'PARENT') !== false)
                    ? (int) ($childInvSumByParent[$pm->parent ?? $pm->sku ?? ''] ?? 0)
                    : (($shopify && isset($shopify->inv)) ? (int) $shopify->inv : 0);

                // Skip if INV = 0
                if ($inv == 0) {
                    continue;
                }

                // Skip if campaign_id is empty
                if (empty($matchedCampaignL30->campaign_id)) {
                    continue;
                }

                $row = [];
                $row['price']  = $amazonSheet->price ?? 0;
                $row['campaign_id'] = $matchedCampaignL30->campaign_id ?? '';
                $row['campaignName'] = $matchedCampaignL30->campaignName ?? '';

                // Skip if campaignName is empty (matching frontend filter)
                if (empty($row['campaignName'])) {
                    continue;
                }

                $sales = $matchedCampaignL30->sales30d ?? 0;
                $spend = $matchedCampaignL30->spend ?? 0;
                $row['spend'] = $spend;
                $row['units_ordered_l30'] = $amazonSheet->units_ordered_l30 ?? 0;

                if ($spend > 0 && $sales > 0) {
                    $row['acos_L30'] = round(($spend / $sales) * 100, 2);
                } elseif ($spend > 0 && $sales == 0) {
                    $row['acos_L30'] = 100;
                } else {
                    $row['acos_L30'] = 0;
                }
                
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

                // Add to totals calculation (only campaigns that pass all filters)
                $totalSpend += $spend;
                $totalSales += $sales;
                $validCampaignsForTotal[] = $row;
            }

            // Calculate total ACOS from valid campaigns only (matching frontend logic)
            $totalACOS = $totalSales > 0 ? ($totalSpend / $totalSales) * 100 : 0;

            // Now calculate sbgt for each valid campaign using the calculated total ACOS
            foreach ($validCampaignsForTotal as $row) {
                $acos = (float) ($row['acos_L30'] ?? 0);
                $price = (float) ($row['price'] ?? 0);
                $isParent = (stripos($row['campaignName'] ?? '', 'PARENT') !== false);

                if ($isParent) {
                    // PARENT SKU: ACOS-based sbgt, max $5
                    if ($acos < 5) {
                        $row['sbgt'] = 6;
                    } elseif ($acos < 10) {
                        $row['sbgt'] = 5;
                    } elseif ($acos < 15) {
                        $row['sbgt'] = 4;
                    } elseif ($acos < 20) {
                        $row['sbgt'] = 3;
                    } elseif ($acos < 25) {
                        $row['sbgt'] = 2;
                    } else {
                        $row['sbgt'] = 1;
                    }
                    if ($row['sbgt'] > 5) {
                        $row['sbgt'] = 5;
                    }
                } else {
                    // Child SKU: Budget = 10% of price (rounded up), ACOS > 20% → $1, max $5
                    if ($acos > 20) {
                        $row['sbgt'] = 1;
                    } else {
                        $row['sbgt'] = ceil($price * 0.10);
                    }
                    if ($row['sbgt'] > 5) {
                        $row['sbgt'] = 5;
                    }
                }

                $result[] = (object) $row;
            }

            DB::connection()->disconnect();
            return $result;
        } catch (\Exception $e) {
            $this->error("Error in amazonAcosKwControlData: " . $e->getMessage());
            $this->info("Error trace: " . $e->getTraceAsString());
            try {
                DB::connection()->disconnect();
            } catch (\Exception $ex) {
                // Ignore disconnect errors
            }
            return [];
        }
    }

}