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
    protected $signature = 'amazon:auto-update-amz-bgt-pt {--dry-run : Run without updating Amazon (test only)}';
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

            $campaigns = $this->amazonAcosPtControlData();

            // Close connection after data fetching
            DB::connection()->disconnect();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                return 0;
            }

            // Load bid caps from database
            $bidCapsData = \App\Models\AmazonBidCap::all()->keyBy('sku');

            // Track campaigns skipped due to bid cap
            $skippedDueToCap = [];

            // Filter out campaigns with empty/null campaign_id or invalid sbgt
            // Also filter out if SBGT > Bid Cap (cap protection)
            $validCampaigns = collect($campaigns)->filter(function ($campaign) use ($bidCapsData, &$skippedDueToCap) {
                if (empty($campaign->campaign_id) || !isset($campaign->sbgt) || $campaign->sbgt <= 0) {
                    return false;
                }
                
                // Check if bid cap exists for this SKU
                $sku = strtoupper($campaign->campaignName ?? '');
                if ($bidCapsData->has($sku)) {
                    $bidCap = $bidCapsData[$sku]->bid_cap;
                    // If SBGT exceeds bid cap, skip this campaign
                    if ($bidCap > 0 && $campaign->sbgt > $bidCap) {
                        $skippedDueToCap[] = [
                            'campaign' => $campaign->campaignName,
                            'sbgt' => $campaign->sbgt,
                            'cap' => $bidCap
                        ];
                        return false; // Skip - SBGT exceeds cap
                    }
                }
                
                return true;
            })->values();

            // Show campaigns skipped due to bid cap
            if (count($skippedDueToCap) > 0) {
                $this->warn("\n⚠️  PT Campaigns SKIPPED due to Bid Cap protection:");
                foreach ($skippedDueToCap as $skipped) {
                    $this->warn("  - {$skipped['campaign']}: SBGT \${$skipped['sbgt']} > Cap \${$skipped['cap']}");
                }
                $this->warn("");
            }

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
                $this->info($dryRun ? "CAMPAIGN BUDGET UPDATE SUMMARY (PT) [DRY RUN]" : "CAMPAIGN BUDGET UPDATE SUMMARY (PT)");
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
                ->whereRaw("UPPER(campaignStatus) = 'ENABLED'")
                ->get();

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

            // For PARENT rows: avg price = average of child SKUs' prices
            $childPricesByParent = [];
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
                $amazonSheetChild = $amazonDatasheetsBySku[$skuUpper] ?? null;
                $childPrice = ($amazonSheetChild && isset($amazonSheetChild->price) && (float)$amazonSheetChild->price > 0)
                    ? (float)$amazonSheetChild->price
                    : null;
                if ($childPrice === null) {
                    $values = $pm->Values;
                    if (is_string($values)) {
                        $values = json_decode($values, true) ?: [];
                    } elseif (is_object($values)) {
                        $values = (array) $values;
                    } elseif (!is_array($values)) {
                        $values = [];
                    }
                    $childPrice = isset($values['msrp']) && (float)$values['msrp'] > 0
                        ? (float)$values['msrp']
                        : (isset($values['map']) && (float)$values['map'] > 0 ? (float)$values['map'] : null);
                }
                if ($childPrice !== null && $childPrice > 0) {
                    $normParent = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $p ?? ''))));
                    $normParent = rtrim($normParent, '.');
                    if (!isset($childPricesByParent[$normParent])) {
                        $childPricesByParent[$normParent] = [];
                    }
                    $childPricesByParent[$normParent][] = $childPrice;
                }
            }
            $avgPriceByParent = [];
            foreach ($childPricesByParent as $p => $prices) {
                $avgPriceByParent[$p] = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
            }

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
                $row['INV']         = $inv;
                $price = ($amazonSheet && isset($amazonSheet->price)) ? (float)$amazonSheet->price : 0;
                // For PARENT rows: use avg price from children when direct price is 0
                if (($price === 0 || $price === null) && stripos($sku, 'PARENT') !== false) {
                    $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $sku))));
                    $normParentKey = rtrim($normSku, '.');
                    $price = $avgPriceByParent[$normSku] ?? $avgPriceByParent[$normParentKey] ?? $avgPriceByParent[$pm->parent ?? ''] ?? $avgPriceByParent[rtrim($pm->parent ?? '', '.')] ?? 0;
                }
                $row['price'] = $price;
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

                // ACOS-based SBGT rules
                if ($acos > 35) {
                    $row['sbgt'] = 1;
                } elseif ($acos >= 30) {
                    $row['sbgt'] = 3;
                } elseif ($acos >= 25) {
                    $row['sbgt'] = 5;
                } elseif ($acos >= 20) {
                    $row['sbgt'] = 10;
                } elseif ($acos >= 15) {
                    $row['sbgt'] = 15;
                } elseif ($acos >= 10) {
                    $row['sbgt'] = 20;
                } elseif ($acos >= 5) {
                    $row['sbgt'] = 25;
                } else {
                    $row['sbgt'] = 30; // Less than 5
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