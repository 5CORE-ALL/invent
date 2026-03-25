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
use Illuminate\Support\Facades\Log;

class AutoUpdateAmazonKwBids extends Command
{
    protected $signature = 'amazon:auto-update-over-kw-bids {--dry-run : Show what would be updated without calling API}';
    protected $description = 'Automatically update Amazon campaign keyword bids';

    /** Number of retry attempts for failed campaign updates (minimum 5 tries total for failures). */
    const MAX_RETRY_ATTEMPTS = 5;

    /** Seconds to wait between retry rounds for failed campaigns (rate-limit precaution). */
    const RETRY_DELAY_SECONDS = 5;

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            // Ensure enough time for full run including retries (up to 5 rounds of API calls)
            @ini_set('max_execution_time', 900);

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
                        $ub7 = floatval($campaign->ub7 ?? 0);
                        $ub1 = floatval($campaign->ub1 ?? 0);
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

            $campaignBudgetMapForRetry = $campaignBudgetMap; // keep for building retry lists
            $allSkipped = [];
            $result = null;
            $attempt = 0;
            $maxRetries = self::MAX_RETRY_ATTEMPTS;
            $retryDelay = self::RETRY_DELAY_SECONDS;
            $currentCampaignIds = $campaignIds;
            $currentNewBids = $newBids;

            while (true) {
                $attempt++;
                $this->info("Update attempt {$attempt} of {$maxRetries} (" . count($currentCampaignIds) . " campaigns).");

                try {
                    if ($attempt > 1) {
                        $this->info("Waiting {$retryDelay} seconds before retry (rate-limit precaution)...");
                        sleep($retryDelay);
                    }

                    $result = $updateKwBids->updateAutoCampaignKeywordsBid($currentCampaignIds, $currentNewBids);

                    if (!is_array($result)) {
                        $this->error("Unexpected result from update.");
                        break;
                    }

                    $allSkipped = array_merge($allSkipped, $result['skipped'] ?? []);

                    $failed = $result['failed'] ?? [];
                    if (empty($failed)) {
                        $this->info("All campaigns in this batch updated successfully.");
                        break;
                    }

                    $this->warn("Attempt {$attempt}: " . count($failed) . " campaign(s) failed. Will retry failed only.");
                    foreach ($failed as $f) {
                        $this->warn("  - {$f['campaign_id']}: " . ($f['error'] ?? 'unknown'));
                    }

                    if ($attempt >= $maxRetries) {
                        $this->error("Max retries ({$maxRetries}) reached. " . count($failed) . " campaign(s) still failed.");
                        $result['failed'] = $failed;
                        break;
                    }

                    // Build next batch: only failed campaign IDs with their bids
                    $currentCampaignIds = [];
                    $currentNewBids = [];
                    foreach ($failed as $f) {
                        $cid = $f['campaign_id'] ?? null;
                        if ($cid !== null && isset($campaignBudgetMapForRetry[$cid])) {
                            $currentCampaignIds[] = $cid;
                            $currentNewBids[] = $campaignBudgetMapForRetry[$cid];
                        }
                    }
                    if (empty($currentCampaignIds)) {
                        $this->warn("No retriable campaign IDs left.");
                        break;
                    }
                } catch (\Exception $e) {
                    $this->error("Attempt {$attempt} exception: " . $e->getMessage());
                    $result = ['status' => 500, 'error' => $e->getMessage(), 'failed' => []];
                    if ($attempt >= $maxRetries) {
                        break;
                    }
                    $this->info("Will retry full batch on next attempt.");
                }
            }

            // Log results
            if ($result) {
                $this->info("Update Result Status: " . (is_array($result) && isset($result['status']) ? $result['status'] : 'unknown'));
                if (is_array($result) && isset($result['message'])) {
                    $this->info("Update Message: " . $result['message']);
                }
                if (!empty($allSkipped)) {
                    $this->warn("Skipped campaigns (total): " . count($allSkipped));
                    foreach (array_slice($allSkipped, 0, 20) as $s) {
                        $this->warn("  - {$s['campaign_id']}: {$s['reason']}");
                    }
                    if (count($allSkipped) > 20) {
                        $this->warn("  ... and " . (count($allSkipped) - 20) . " more.");
                    }
                }
                if (is_array($result) && !empty($result['failed'])) {
                    $this->error("Failed campaigns after all retries: " . count($result['failed']));
                    foreach ($result['failed'] as $f) {
                        $this->error("  - {$f['campaign_id']}: " . ($f['error'] ?? 'unknown'));
                    }
                }
                if (is_array($result) && isset($result['error']) && empty($result['failed'])) {
                    $this->error("Update Error: " . $result['error']);
                }
            } else {
                $this->error("Update failed (no result).");
            }

            $total = count($campaignIds);
            $skippedCount = count($allSkipped);
            $failedCount = isset($result['failed']) ? count($result['failed']) : 0;
            $updatedCount = $total - $skippedCount - $failedCount;
            $this->info("========================================");
            $this->info("FINAL: Total={$total} | Updated={$updatedCount} | Skipped={$skippedCount} | Failed={$failedCount}");
            $this->info("========================================");

            Log::info('amazon:auto-update-over-kw-bids completed', [
                'total' => $total,
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
                'attempts' => $attempt,
            ]);
            
            $this->info("Amazon KW Bids Update completed. Total campaigns: " . count($campaignIds));

            if ($result && is_array($result) && empty($result['failed'] ?? [])) {
                $this->info("✓ Command completed successfully. All retriable campaigns updated.");
                return 0;
            }
            if ($result && is_array($result) && !empty($result['failed'])) {
                $this->warn("⚠ Command completed with " . count($result['failed']) . " campaign(s) still failed after " . self::MAX_RETRY_ATTEMPTS . " attempts.");
                return 1;
            }
            $this->warn("⚠ Command completed with warnings or errors.");
            return 1;

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
            ->where('campaignName', 'NOT LIKE', '% PT')
            ->where('campaignName', 'NOT LIKE', '% PT.')
            ->get();

        $amazonSpCampaignReportsL30 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
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

            $matchedCampaignL2 = $amazonSpCampaignReportsL2->first(function ($item) use ($sku) {
                $campaignName = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($item->campaignName, '.'))));
                $cleanSku = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($sku, '.'))));
                return $campaignName === $cleanSku || $campaignName === $cleanSku . ' KW';
            });

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($item->campaignName, '.'))));
                $cleanSku = preg_replace('/\s+/', ' ', strtoupper(trim(rtrim($sku, '.'))));
                return $campaignName === $cleanSku || $campaignName === $cleanSku . ' KW';
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1 && !$matchedCampaignL30) {
                continue;
            }

            // Skip if we've already processed this campaign ID (avoid duplicates)
            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? ($matchedCampaignL30->campaign_id ?? ''));
            if (!empty($campaignId) && isset($processedCampaignIds[$campaignId])) {
                continue;
            }
            $processedCampaignIds[$campaignId] = true;

            $row = [];
            // INV: for PARENT SKU use sum of children's INV; for child use shopify inv
            $row['INV'] = (stripos($pm->sku ?? '', 'PARENT') !== false)
                ? (int) ($childInvSumByParent[$pm->parent ?? $pm->sku ?? ''] ?? 0)
                : (int) ($shopify->inv ?? 0);
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? ($matchedCampaignL30->campaignName ?? ''));
            $row['campaignStatus'] = strtoupper(trim($matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? ($matchedCampaignL30->campaignStatus ?? 'PAUSED'))));
            // Keep utilization budget stable across windows so command-side UB matches frontend behavior.
            // Prefer the higher non-zero budget from recent windows.
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
                Log::warning('Over-utilized job: campaign has L30 spend but missing L7/L1 data (possible sync issue)', [
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

            $budget = floatval($row['utilization_budget'] ?? $row['campaignBudgetAmount'] ?? 0);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);
            $l30_spend = floatval($row['l30_spend'] ?? 0);

            // UB7: use L7 if available; else estimate from L30 when L7 missing but L30 has spend
            if ($l7_spend > 0 && $budget > 0) {
                $ub7 = ($l7_spend / ($budget * 7)) * 100;
            } elseif ($l30_spend > 0 && $budget > 0) {
                $estimatedL7Spend = ($l30_spend / 30) * 7;
                $ub7 = ($estimatedL7Spend / ($budget * 7)) * 100;
                Log::info('Over-utilized job: using estimated UB7 from L30', [
                    'campaign_id' => $row['campaign_id'],
                    'campaignName' => $row['campaignName'],
                    'l30_spend' => $l30_spend,
                    'estimated_ub7' => round($ub7, 2),
                ]);
            } else {
                $ub7 = 0;
            }

            // UB1: use L1 if available; else estimate from L30 when L1 missing but L30 has spend
            if ($l1_spend > 0 && $budget > 0) {
                $ub1 = ($l1_spend / $budget) * 100;
            } elseif ($l30_spend > 0 && $budget > 0) {
                $estimatedL1Spend = $l30_spend / 30;
                $ub1 = ($estimatedL1Spend / $budget) * 100;
                Log::info('Over-utilized job: using estimated UB1 from L30', [
                    'campaign_id' => $row['campaign_id'],
                    'campaignName' => $row['campaignName'],
                    'l30_spend' => $l30_spend,
                    'estimated_ub1' => round($ub1, 2),
                ]);
            } else {
                $ub1 = 0;
            }

            // UB2: 2-day utilization (L2 spend / budget) - for SBID condition matching tabulator
            $l2_spend = floatval($row['l2_spend'] ?? 0);
            $ub2 = ($budget > 0 && $l2_spend > 0) ? ($l2_spend / $budget) * 100 : 0;
            $ub2Red = $ub2 < 66;
            $ub2Pink = $ub2 > 99;
            $ub1Red = $ub1 < 66;
            $ub1Pink = $ub1 > 99;

            // Calculate SBID to match tabulator: only 2UB+1UB red or 2UB+1UB pink (otherwise skip)
            $l1_cpc = floatval($row['l1_cpc']);
            $l2_cpc = floatval($row['l2_cpc'] ?? 0);
            $l7_cpc = floatval($row['l7_cpc']);
            $row['sbid'] = 0;

            if ($ub2Red && $ub1Red) {
                // KW 2UB red AND 1UB red:
                // 1) L1*1.10
                // 2) if L1 is 0, L2*1.10
                // 3) if L1 and L2 are 0, L7*1.10
                // 4) if all CPC are 0, default 0.60
                if ($l1_cpc > 0) {
                    $row['sbid'] = floor($l1_cpc * 1.10 * 100) / 100;
                } elseif ($l1_cpc <= 0 && $l2_cpc > 0) {
                    $row['sbid'] = floor($l2_cpc * 1.10 * 100) / 100;
                } elseif ($l1_cpc <= 0 && $l2_cpc <= 0 && $l7_cpc > 0) {
                    $row['sbid'] = floor($l7_cpc * 1.10 * 100) / 100;
                } else {
                    $row['sbid'] = 0.60;
                }
            } elseif ($ub2Pink && $ub1Pink) {
                // KW 2UB pink AND KW 1UB pink: L1*0.90
                $row['sbid'] = floor($l1_cpc * 0.90 * 100) / 100;
            }
            // Else: conditions do not match → sbid stays 0, row will be skipped

            // Validate all required fields before adding
            if (empty($row['campaign_id'])) {
                continue; // Skip if no campaign ID
            }
            
            if (!is_numeric($row['sbid']) || $row['sbid'] <= 0) {
                continue; // Skip if invalid bid
            }

            // Include all campaigns with valid SBID and ENABLED (over-, under-, and correctly utilized)
            if (!empty($row['campaign_id']) && is_numeric($row['sbid']) && $row['sbid'] > 0 && ($row['campaignStatus'] ?? '') === 'ENABLED') {
                $row['ub7'] = $ub7;
                $row['ub1'] = $ub1;
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