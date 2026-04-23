<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use GuzzleHttp\Client;
use App\Services\Amazon\AmazonBidUtilizationService;
use App\Support\AmazonAdsSbidRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoUpdateAmzUnderKwBids extends Command
{
    protected $signature = 'amazon:auto-update-under-kw-bids {--dry-run : Show what would be updated without calling API}';
    protected $description = 'Automatically update Amazon campaign keyword bids';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $dryRun = $this->option('dry-run');
            $this->info("Starting Amazon Under-Utilized KW bids auto-update..." . ($dryRun ? " [DRY RUN - no API calls]" : ""));

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

            $updateKwBids = new AmazonSpBudgetController;

            $campaigns = $this->getAutomateAmzUtilizedBgtKw();

            if (empty($campaigns)) {
            $this->warn("No campaigns matched filter conditions.");
            return 0;
        }

            // Filter out campaigns with invalid data
            $validCampaigns = collect($campaigns)->filter(function ($campaign) {
                return !empty($campaign->campaign_id) && 
                       isset($campaign->sbid) && 
                       is_numeric($campaign->sbid) && 
                       $campaign->sbid > 0;
            })->values();

            if ($validCampaigns->isEmpty()) {
                $this->warn("No valid campaigns found (missing campaign_id or invalid bid).");
                return 0;
            }

            $this->info("Found " . $validCampaigns->count() . " valid campaigns to update.");
            $this->line("");

            // Log campaigns before update
            $this->info("========================================");
            $this->info("CAMPAIGNS TO UPDATE (UNDER-UTILIZED):");
            $this->info("========================================");
            foreach ($validCampaigns as $campaign) {
                $campaignName = $campaign->campaignName ?? 'N/A';
                $newBid = $campaign->sbid ?? 0;
                $campaignId = $campaign->campaign_id ?? '';
                $ub7 = floatval($campaign->ub7 ?? 0);
                $ub1 = floatval($campaign->ub1 ?? 0);
                $inv = (int)($campaign->INV ?? 0);
                
                $this->info("Campaign Name: {$campaignName}");
                $this->info("  - Campaign ID: {$campaignId}");
                $this->info("  - Bid: {$newBid}");
                $this->info("  - 7UB: " . round($ub7, 2) . "% | 1UB: " . round($ub1, 2) . "%");
                $this->info("  - INV: {$inv}");
                $this->info("---");
            }
            $this->info("========================================");
            $this->line("");

            if ($dryRun) {
                $this->newLine();
                $this->warn("DRY RUN: No API call made. Remove --dry-run to apply updates.");
                $this->info("✓ Dry run completed. Total campaigns that would be updated: " . $validCampaigns->count());
                return 0;
            }

            $campaignIds = $validCampaigns->pluck('campaign_id')->toArray();
            $newBids = $validCampaigns->pluck('sbid')->toArray();

            // Validate arrays are aligned
            if (count($campaignIds) !== count($newBids)) {
                $this->error("✗ Array mismatch: campaign IDs and bids count don't match!");
                return 1;
            }

            try {
                $result = $updateKwBids->updateAutoCampaignKeywordsBid($campaignIds, $newBids);

                //Handle Response object (when no keywords found)
                if (is_object($result) && method_exists($result, 'getData')) {
                    $result = $result->getData(true);
                }

                // Check for errors
                if (is_array($result) && isset($result['status'])) {
                    if ($result['status'] == 200) {
                        $this->info("✓ Bid update completed successfully!");
                        $this->line("");
                        $this->info("Updated campaigns:");
                        $persistedRows = 0;
                        foreach ($validCampaigns as $campaign) {
                            $campaignName = $campaign->campaignName ?? 'N/A';
                            $newBid = $campaign->sbid ?? 0;
                            $this->line("  Campaign: {$campaignName} | New Bid: {$newBid}");
                            $persistedRows += AmazonBidUtilizationService::persistSpSbidM((string) ($campaign->campaign_id ?? ''), (float) $newBid);
                        }
                        Log::info('amazon:auto-update-under-kw-bids persisted sbid_m to L30', [
                            'campaigns' => $validCampaigns->count(),
                            'l30_rows_updated' => $persistedRows,
                        ]);
                    } else {
                        $this->error("✗ Bid update failed!");
                        $this->error("Status: " . $result['status']);
                        if (isset($result['message'])) {
                            $this->error("Message: " . $result['message']);
                        }
                        if (isset($result['error'])) {
                            $this->error("Error: " . $result['error']);
                        }
                        return 1;
                    }
                } else {
                    // Handle unexpected response format
                    $this->warn("Unexpected response format from update method.");
                    if (is_array($result) || is_object($result)) {
                        $this->line("Response: " . json_encode($result));
                    } else {
                        $this->line("Response type: " . gettype($result));
                    }
                }

                $this->info('Campaigns to be updated: ' . count($campaigns));

            } catch (\Exception $e) {
                $this->error("✗ Exception occurred during bid update:");
                $this->error($e->getMessage());
                $this->error("Stack trace: " . $e->getTraceAsString());
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error in handle: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getAutomateAmzUtilizedBgtKw()
    {
        try {
            $sbidRule = AmazonAdsSbidRule::resolvedRule();
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

            $shopifyData = [];
            $amazonDatasheets = [];

            if (!empty($skus)) {
                $shopifyData = ShopifySku::mapByProductSkus($skus);
                $amazonDatasheets = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
            }

        // For PARENT rows: INV = sum of child SKUs' INV (same as Over/BgtKw commands)
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

        // For PARENT rows: avg price = average of child SKUs' prices (from amazon datashheet, or ProductMaster Values as fallback)
        $childPricesByParent = [];
        foreach ($productMasters as $pmChild) {
            $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pmChild->sku ?? '');
            $norm = preg_replace('/\s+/', ' ', $norm);
            $skuUpper = strtoupper(trim($norm));
            if (stripos($skuUpper, 'PARENT') !== false) {
                continue;
            }
            $p = $pmChild->parent ?? '';
            if ($p === '') {
                continue;
            }
            $amazonSheetChild = $amazonDatasheets[$skuUpper] ?? null;
            $childPrice = ($amazonSheetChild && isset($amazonSheetChild->price) && (float)$amazonSheetChild->price > 0)
                ? (float)$amazonSheetChild->price
                : null;
            if ($childPrice === null) {
                $values = $pmChild->Values;
                if (is_string($values)) {
                    $values = json_decode($values, true) ?: [];
                } elseif (is_object($values)) {
                    $values = (array) $values;
                } else {
                    $values = is_array($values) ? $values : [];
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
        $avgPriceByParentCanonical = [];
        foreach ($childPricesByParent as $pk => $prices) {
            $avg = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
            $avgPriceByParent[$pk] = $avg;
            $canonical = preg_replace('/\s+/', '', $pk);
            if ($canonical !== '' && $avg > 0) {
                $avgPriceByParentCanonical[$canonical] = $avg;
            }
        }

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        // L2 = 2nd last date from table (table's latest date - 1 day), not server today
        $latestDateInTable = DB::table('amazon_sp_campaign_reports')
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->max('report_date_range');
        $dayBeforeYesterdayForL2 = $latestDateInTable
            ? date('Y-m-d', strtotime($latestDateInTable . ' -1 day'))
            : date('Y-m-d', strtotime('-2 days'));
        $amazonSpCampaignReportsL2 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', $dayBeforeYesterdayForL2)
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                // Match campaign with or without " KW" suffix
                return ($campaignName === $cleanSku || $campaignName === $cleanSku . ' KW') 
                    && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return ($campaignName === $cleanSku || $campaignName === $cleanSku . ' KW')
                    && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
            });

            $matchedCampaignL2 = $amazonSpCampaignReportsL2->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return ($campaignName === $cleanSku || $campaignName === $cleanSku . ' KW')
                    && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
            });

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return ($campaignName === $cleanSku || $campaignName === $cleanSku . ' KW') 
                    && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1 && !$matchedCampaignL30) {
                continue;
            }

            $row = [];
            // INV: for PARENT SKU use sum of children's INV; for child use shopify inv
            $isParentSku = stripos($pm->sku ?? '', 'PARENT') !== false;
            $row['INV'] = $isParentSku
                ? (int) ($childInvSumByParent[$pm->parent ?? $pm->sku ?? ''] ?? 0)
                : (int) ($shopify->inv ?? 0);
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? ($matchedCampaignL30->campaign_id ?? ''));
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? ($matchedCampaignL30->campaignName ?? ''));
            $row['campaignStatus'] = strtoupper(trim($matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? ($matchedCampaignL30->campaignStatus ?? 'PAUSED'))));
            // Use a stable utilization budget so command-side UB matches KW frontend behavior.
            // Prefer the higher non-zero budget among recent windows (L2/L1/L7/L30) instead of only L7.
            $budgetCandidates = [
                floatval($matchedCampaignL2->campaignBudgetAmount ?? 0),
                floatval($matchedCampaignL1->campaignBudgetAmount ?? 0),
                floatval($matchedCampaignL7->campaignBudgetAmount ?? 0),
                floatval($matchedCampaignL30->campaignBudgetAmount ?? 0),
            ];
            $budgetCandidates = array_values(array_filter($budgetCandidates, function ($v) {
                return $v > 0;
            }));
            $utilizationBudget = !empty($budgetCandidates) ? max($budgetCandidates) : 0;

            $row['campaignBudgetAmount'] = $utilizationBudget;
            $row['utilization_budget'] = $utilizationBudget;
            $row['l7_spend'] = $matchedCampaignL7 ? (float)($matchedCampaignL7->spend ?? 0) : 0;
            $row['l7_cpc'] = $matchedCampaignL7 ? (float)($matchedCampaignL7->costPerClick ?? 0) : 0;
            $row['l1_spend'] = $matchedCampaignL1 ? (float)($matchedCampaignL1->spend ?? 0) : 0;
            $row['l1_cpc'] = $matchedCampaignL1 ? (float)($matchedCampaignL1->costPerClick ?? 0) : 0;
            $row['l2_spend'] = $matchedCampaignL2 ? (float)($matchedCampaignL2->spend ?? 0) : 0;
            $row['l2_cpc'] = $matchedCampaignL2 ? (float)($matchedCampaignL2->costPerClick ?? 0) : 0;
            $row['l30_spend'] = $matchedCampaignL30 ? (float)($matchedCampaignL30->spend ?? 0) : 0;

            if (!$matchedCampaignL7 && !$matchedCampaignL1 && $matchedCampaignL30 && ($row['l30_spend'] ?? 0) > 0) {
                Log::warning('Under-utilized job: campaign has L30 spend but missing L7/L1 data (possible sync issue)', [
                    'campaign_id' => $row['campaign_id'],
                    'campaignName' => $row['campaignName'],
                    'l30_spend' => $row['l30_spend'],
                ]);
            }

            // Get price from AmazonDatasheet
            $amazonSheet = $amazonDatasheets[strtoupper($pm->sku)] ?? null;
            $price = ($amazonSheet && isset($amazonSheet->price)) ? floatval($amazonSheet->price) : 0;
            // For parent SKU rows: use average of child SKUs' prices when direct price is 0
            if (($price === 0 || $price === null) && stripos($pm->sku ?? '', 'PARENT') !== false && !empty($avgPriceByParent)) {
                $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $sku))));
                $normSku = rtrim($normSku, '.');
                $normParentKey = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->parent ?? ''))));
                $normParentKey = rtrim($normParentKey, '.');
                $price = $avgPriceByParent[$normSku] ?? $avgPriceByParent[$normParentKey] ?? $avgPriceByParent[$pm->parent ?? ''] ?? $avgPriceByParent[rtrim($pm->parent ?? '', '.')] ?? 0;
                if ($price === 0 && !empty($avgPriceByParentCanonical)) {
                    $canonicalSku = preg_replace('/\s+/', '', $normSku);
                    $canonicalParentKey = preg_replace('/\s+/', '', $normParentKey);
                    $price = $avgPriceByParentCanonical[$canonicalSku] ?? $avgPriceByParentCanonical[$canonicalParentKey] ?? 0;
                }
                if ($price === 0) {
                    $parentValues = $pm->Values;
                    if (is_string($parentValues)) {
                        $parentValues = json_decode($parentValues, true) ?: [];
                    } elseif (is_object($parentValues)) {
                        $parentValues = (array) $parentValues;
                    } else {
                        $parentValues = is_array($parentValues) ? $parentValues : [];
                    }
                    $price = (isset($parentValues['msrp']) && (float)$parentValues['msrp'] > 0)
                        ? (float)$parentValues['msrp']
                        : (isset($parentValues['map']) && (float)$parentValues['map'] > 0 ? (float)$parentValues['map'] : 0);
                }
            }
            $price = (float) ($price ?? 0);

            // Calculate avg_cpc (lifetime average from daily records)
            $campaignId = $row['campaign_id'];
            $avgCpc = 0;
            try {
                $avgCpcRecord = DB::table('amazon_sp_campaign_reports')
                    ->select(DB::raw('AVG(costPerClick) as avg_cpc'))
                    ->where('campaign_id', $campaignId)
                    ->where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
                    ->where('costPerClick', '>', 0)
                    ->whereNotNull('campaign_id')
                    ->first();
                
                if ($avgCpcRecord && $avgCpcRecord->avg_cpc > 0) {
                    $avgCpc = floatval($avgCpcRecord->avg_cpc);
                }
            } catch (\Exception $e) {
                // Continue without avg_cpc if there's an error
            }

            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);
            $budget = floatval($row['utilization_budget'] ?? $row['campaignBudgetAmount'] ?? 0);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);
            $l30_spend = floatval($row['l30_spend'] ?? 0);

            // UB7: use L7 data if available; else estimate from L30 when L7 is missing but L30 has spend
            if ($l7_spend > 0 && $budget > 0) {
                $ub7 = ($l7_spend / ($budget * 7)) * 100;
            } elseif ($l30_spend > 0 && $budget > 0) {
                $estimatedL7Spend = ($l30_spend / 30) * 7;
                $ub7 = ($estimatedL7Spend / ($budget * 7)) * 100;
                Log::info('Under-utilized job: using estimated UB7 from L30', [
                    'campaign_id' => $row['campaign_id'],
                    'campaignName' => $row['campaignName'],
                    'l30_spend' => $l30_spend,
                    'estimated_ub7' => round($ub7, 2),
                ]);
            } else {
                $ub7 = 0;
            }

            // UB1: use L1 data if available; else estimate from L30 when L1 is missing but L30 has spend
            if ($l1_spend > 0 && $budget > 0) {
                $ub1 = ($l1_spend / $budget) * 100;
            } elseif ($l30_spend > 0 && $budget > 0) {
                $estimatedL1Spend = $l30_spend / 30;
                $ub1 = ($estimatedL1Spend / $budget) * 100;
                Log::info('Under-utilized job: using estimated UB1 from L30', [
                    'campaign_id' => $row['campaign_id'],
                    'campaignName' => $row['campaignName'],
                    'l30_spend' => $l30_spend,
                    'estimated_ub1' => round($ub1, 2),
                ]);
            } else {
                $ub1 = 0;
            }

            $resolved = AmazonBidUtilizationService::resolveUb(
                (string) $row['campaign_id'],
                'kw',
                ['ub7' => $ub7, 'ub1' => $ub1]
            );
            $ub7 = $resolved['ub7'];
            $ub1 = $resolved['ub1'];
            $ubSource = $resolved['source'];

            $baseBid = ($matchedCampaignL30 ? floatval($matchedCampaignL30->last_sbid ?? 0) : 0);
            if ($baseBid <= 0) {
                $baseBid = $l1_cpc > 0 ? $l1_cpc : ($l7_cpc > 0 ? $l7_cpc : 0);
            }

            $l2_spend = floatval($row['l2_spend'] ?? 0);
            $ub2 = AmazonBidUtilizationService::ub2PercentFromL2Spend($budget, $l2_spend);
            $l2_cpc = floatval($row['l2_cpc'] ?? 0);
            $fbL1 = $matchedCampaignL1 ? (float) ($matchedCampaignL1->costPerClick ?? 0) : 0.0;
            $fbL7 = $matchedCampaignL7 ? (float) ($matchedCampaignL7->costPerClick ?? 0) : 0.0;
            $cpcFallback = ($l1_cpc <= 0 && $l2_cpc <= 0 && $l7_cpc <= 0) ? max($fbL1, $fbL7) : null;
            if ($cpcFallback === null || $cpcFallback <= 0) {
                $cpcFallback = null;
            }

            $bidOut = AmazonBidUtilizationService::sbidFromUb2Ub1Cpc(
                $ub2,
                $ub1,
                $l1_cpc,
                $l2_cpc,
                $l7_cpc,
                $cpcFallback
            );
            $row['sbid'] = $bidOut['sbid'];
            $row['ub2'] = $ub2;

            $bothLowKw = AmazonAdsSbidRule::isBothBelowUtilLow($ub2, $ub1, $sbidRule);
            // Same persisted SBID rule as over-KW / grid: red+red + under band only
            if ($row['INV'] > 0 && $row['campaignName'] !== '' && $baseBid > 0 && $bothLowKw && $bidOut['band'] === 'under'
                && $row['sbid'] !== null && is_numeric($row['sbid']) && (float) $row['sbid'] > 0
                && ($row['campaignStatus'] ?? '') === 'ENABLED') {
                if ($price < 10 && $row['sbid'] > 0.10) {
                    $row['sbid'] = 0.10;
                } elseif ($price >= 10 && $price < 20 && $row['sbid'] > 0.20) {
                    $row['sbid'] = 0.20;
                }
                $row['ub7'] = $ub7;
                $row['ub1'] = $ub1;
                AmazonBidUtilizationService::logBidDecision(
                    (string) $row['campaign_id'],
                    'kw_under',
                    $ub1,
                    $baseBid,
                    (float) $row['sbid'],
                    $ubSource
                );
                $result[] = (object) $row;
            }
            }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getAutomateAmzUtilizedBgtKw: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

}