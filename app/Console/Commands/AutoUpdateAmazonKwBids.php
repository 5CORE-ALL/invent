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
use Illuminate\Support\Facades\DB;

class AutoUpdateAmazonKwBids extends Command
{
    protected $signature = 'amazon:auto-update-over-kw-bids {--dry-run : Show what would be updated without calling API}';
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
            $this->info("Starting Amazon bids auto-update..." . ($dryRun ? " [DRY RUN - no API calls]" : ""));

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
                $this->warn("No campaigns found - check filters and data availability");
                return 0;
            }

            $this->info("Found " . count($campaigns) . " campaigns to process.");

            // Build a map to handle duplicate campaign IDs properly
            $campaignBudgetMap = [];
            $campaignDetails = [];
            
            foreach ($campaigns as $campaign) {
                $campaignId = $campaign->campaign_id ?? '';
                $sbid = $campaign->sbid ?? 0;
                $campaignName = $campaign->campaignName ?? '';
                
                if (!empty($campaignId) && $sbid > 0) {
                    // Only add if we haven't seen this campaign ID before
                    if (!isset($campaignBudgetMap[$campaignId])) {
                        $budget = floatval($campaign->campaignBudgetAmount ?? 0);
                        $l7_spend = floatval($campaign->l7_spend ?? 0);
                        $l1_spend = floatval($campaign->l1_spend ?? 0);
                        $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
                        $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;
                        $pinkPink = ($ub7 > 99 && $ub1 > 99);
                        $campaignBudgetMap[$campaignId] = $sbid;
                        $campaignDetails[$campaignId] = [
                            'name' => $campaignName,
                            'bid' => $sbid,
                            'ub7' => round($ub7, 2),
                            'ub1' => round($ub1, 2),
                            'pink_pink' => $pinkPink,
                            'inv' => (int)($campaign->INV ?? 0)
                        ];
                    } else {
                        // Log duplicate but keep first one
                        $this->warn("Duplicate campaign ID skipped: {$campaignId} ({$campaignName}). Already using bid: {$campaignBudgetMap[$campaignId]}");
                    }
                }
            }

            $campaignIds = array_keys($campaignBudgetMap);
            $newBids = array_values($campaignBudgetMap);

            if (empty($campaignIds)) {
                $this->warn("No valid campaign IDs found to update.");
                return 0;
            }

            // Validate arrays are aligned
            if (count($campaignIds) !== count($newBids)) {
                $this->error("Mismatch: " . count($campaignIds) . " campaign IDs but " . count($newBids) . " bids!");
                $this->error("Campaign ID and bid array mismatch", [
                    'campaign_ids_count' => count($campaignIds),
                    'bids_count' => count($newBids)
                ]);
                return 1;
            }

            $this->info("Found " . count($campaignIds) . " unique campaigns to update.");
            
            // Log campaigns with names
            $this->info("========================================");
            $this->info("CAMPAIGNS TO UPDATE:");
            $this->info("========================================");
            foreach ($campaignDetails as $campaignId => $details) {
                $this->info("Campaign Name: {$details['name']}");
                $this->info("  - Campaign ID: {$campaignId}");
                $this->info("  - Bid: {$details['bid']}");
                $this->info("  - 7UB: " . ($details['ub7'] ?? 0) . "% | 1UB: " . ($details['ub1'] ?? 0) . "%");
                $this->info("  - Pink+Pink (Over): " . (!empty($details['pink_pink']) ? 'Yes' : 'No'));
                $this->info("  - INV: " . ($details['inv'] ?? 0));
                $this->info("---");
            }
            $this->info("========================================");

            if ($dryRun) {
                $this->newLine();
                $this->warn("DRY RUN: No API call made. Remove --dry-run to apply updates.");
                $this->info("✓ Dry run completed. Total campaigns that would be updated: " . count($campaignIds));
                return 0;
            }

            // Validate all bids are valid before sending
            $invalidBids = [];
            foreach ($newBids as $index => $bid) {
                if (!is_numeric($bid) || $bid <= 0 || $bid > 1000) {
                    $invalidBids[] = [
                        'index' => $index,
                        'campaign_id' => $campaignIds[$index] ?? 'unknown',
                        'bid' => $bid
                    ];
                }
            }
            
            if (!empty($invalidBids)) {
                $this->error("Found " . count($invalidBids) . " invalid bids. Skipping update.");
                $this->error("Invalid bids detected", ['invalid_bids' => $invalidBids]);
                return 1;
            }

            // Retry logic for API calls
            $maxRetries = 3;
            $result = null;
            $lastError = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    if ($attempt > 1) {
                        $this->info("Retry attempt {$attempt} of {$maxRetries}...");
                        sleep(2); // Wait 2 seconds before retry
                    }
                    
                    $result = $updateKwBids->updateAutoCampaignKeywordsBid($campaignIds, $newBids);
                    
                    // Check if result indicates success
                    if (is_array($result)) {
                        $status = $result['status'] ?? null;
                        if ($status == 200 || (isset($result['message']) && stripos($result['message'], 'success') !== false)) {
                            break; // Success, exit retry loop
                        }
                        
                        // Check for retryable errors
                        $error = $result['error'] ?? '';
                        if (stripos($error, 'timeout') !== false || 
                            stripos($error, 'connection') !== false ||
                            stripos($error, '500') !== false ||
                            stripos($error, '503') !== false) {
                            $lastError = $result;
                            if ($attempt < $maxRetries) {
                                continue; // Retry
                            }
                        }
                    }
                    
                    break; // Exit loop if we got a result (success or non-retryable error)
                    
                } catch (\GuzzleHttp\Exception\ServerException $e) {
                    $lastError = ['error' => $e->getMessage(), 'type' => 'ServerException'];
                    if ($attempt < $maxRetries) {
                        continue; // Retry server errors
                    }
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    $lastError = ['error' => $e->getMessage(), 'type' => 'ClientException'];
                    // Don't retry client errors (4xx), they're usually permanent
                    break;
                } catch (\Exception $e) {
                    $lastError = ['error' => $e->getMessage(), 'type' => 'Exception'];
                    if ($attempt < $maxRetries) {
                        continue; // Retry other exceptions
                    }
                }
            }
            
            // Log results
            if ($result) {
                $this->info("Update Result Status: " . (is_array($result) && isset($result['status']) ? $result['status'] : 'unknown'));
                if (is_array($result) && isset($result['message'])) {
                    $this->info("Update Message: " . $result['message']);
                }
                if (is_array($result) && isset($result['error'])) {
                    $this->error("Update Error: " . $result['error']);
                }
            } else {
                $this->error("Update failed after {$maxRetries} attempts");
                if ($lastError) {
                    $this->error("Last Error: " . ($lastError['error'] ?? json_encode($lastError)));
                }
            }
            
            $this->info("Amazon KW Bids Update completed. Total campaigns: " . count($campaignIds));

            if ($result && is_array($result) && ($result['status'] ?? 0) == 200) {
                $this->info("✓ Command completed successfully");
                return 0;
            } else {
                $this->warn("⚠ Command completed with warnings or errors");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getAutomateAmzUtilizedBgtKw()
    {
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                $this->warn("No product masters found in database!");
                $this->warn("No ProductMaster records found");
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
                $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
                $amazonDatasheets = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
            }

            // NRA check: skip SKUs with NRA = 'NRA' (same as frontend - controller does not add these rows)
            $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            // For PARENT rows: INV = sum of child SKUs' INV (same as BgtKw/BgtPt)
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

        // Exclude only Product Targeting campaigns (name ends with " PT" or " PT."). Do NOT use '%PT' - it excludes PARENT (contains "PT").
        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->get();

        $result = [];
        $processedCampaignIds = []; // Track to avoid processing same campaign multiple times

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                // Normalize spaces: replace multiple spaces with single space
                $campaignName = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($item->campaignName, '.'))));
                $cleanSku = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($sku, '.'))));
                // Match campaign with or without " KW" suffix
                return $campaignName === $cleanSku || $campaignName === $cleanSku . ' KW';
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                // Normalize spaces: replace multiple spaces with single space
                $campaignName = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($item->campaignName, '.'))));
                $cleanSku = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($sku, '.'))));
                // Match campaign with or without " KW" suffix
                return $campaignName === $cleanSku || $campaignName === $cleanSku . ' KW';
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            // Skip if we've already processed this campaign ID (avoid duplicates)
            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            if (!empty($campaignId) && isset($processedCampaignIds[$campaignId])) {
                continue;
            }
            $processedCampaignIds[$campaignId] = true;

            // Skip NRA = 'NRA' (matches frontend - these rows are not in controller output)
            $nrRaw = $nrValues[$pm->sku] ?? null;
            $nrArr = is_array($nrRaw) ? $nrRaw : (is_string($nrRaw) ? json_decode($nrRaw, true) : []);
            if (is_array($nrArr) && isset($nrArr['NRA']) && trim((string)$nrArr['NRA']) === 'NRA') {
                continue;
            }

            $row = [];
            // INV: for PARENT SKU use sum of children's INV; for child use shopify inv
            $row['INV'] = (stripos($pm->sku ?? '', 'PARENT') !== false)
                ? (int) ($childInvSumByParent[$pm->parent ?? $pm->sku ?? ''] ?? 0)
                : (int) ($shopify->inv ?? 0);
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignStatus'] = strtoupper(trim($matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? 'PAUSED')));
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;

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

            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // Calculate SBID based on blade file logic
            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);
            // Parent rows now have avg price, so apply same price-based rules for all rows (including PARENT)
            // Special case - If UB7 and UB1 = 0%, use price-based default
            if ($ub7 === 0 && $ub1 === 0) {
                if ($price < 50) {
                    $row['sbid'] = 0.50;
                } else if ($price >= 50 && $price < 100) {
                    $row['sbid'] = 1.00;
                } else if ($price >= 100 && $price < 200) {
                    $row['sbid'] = 1.50;
                } else {
                    $row['sbid'] = 2.00;
                }
            } else {
                // Over-utilized: Priority - L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
                if ($l1_cpc > 0) {
                    $row['sbid'] = floor($l1_cpc * 0.90 * 100) / 100;
                } else if ($l7_cpc > 0) {
                    $row['sbid'] = floor($l7_cpc * 0.90 * 100) / 100;
                } else if ($avgCpc > 0) {
                    $row['sbid'] = floor($avgCpc * 0.90 * 100) / 100;
                } else {
                    $row['sbid'] = 1.00;
                }
            }
            // Apply price-based caps (parent rows now have avg price, so apply for all rows)
            if ($price < 10 && $row['sbid'] > 0.10) {
                $row['sbid'] = 0.10;
            } else if ($price >= 10 && $price < 20 && $row['sbid'] > 0.20) {
                $row['sbid'] = 0.20;
            }

            // Validate all required fields before adding
            if (empty($row['campaign_id'])) {
                continue; // Skip if no campaign ID
            }
            
            if (!is_numeric($row['sbid']) || $row['sbid'] <= 0) {
                continue; // Skip if invalid bid
            }

            // Include only OVER-utilized + ENABLED (matches frontend: Over filter excludes PAUSED).
            // Frontend shows only ENABLED when filtering by utilization type; command must match.
            if ($row['INV'] > 0 && ($ub7 > 99 && $ub1 > 99) && ($row['campaignStatus'] ?? '') === 'ENABLED') {
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