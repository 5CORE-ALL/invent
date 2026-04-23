<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\AmazonSbBudgetController;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Models\AmazonDataView;
use App\Models\AmazonSbCampaignReport;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\Amazon\AmazonBidUtilizationService;
use App\Support\AmazonAdsSbidRule;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoUpdateAmazonHlBids extends Command
{
    protected $signature = 'amazon:auto-update-over-hl-bids';
    protected $description = 'Automatically update Amazon campaign keyword bids';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $this->info("Starting Amazon HL bids auto-update...");

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

            $updateKwBids = new AmazonSbBudgetController;

            $campaigns = $this->getAutomateAmzUtilizedBgtHl();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                $this->warn("No campaigns found - check filters and data availability");
                return 0;
            }

            $this->info("Found " . count($campaigns) . " campaigns to process.");

            // Build a map to handle duplicate campaign IDs properly
            $campaignBudgetMap = [];
            $campaignDetails = [];
            $sbidRuleLog = AmazonAdsSbidRule::resolvedRule();

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
                        $ub2 = floatval($campaign->ub2 ?? 0);
                        $bothHigh = AmazonAdsSbidRule::isBothAboveUtilHigh($ub2, $ub1, $sbidRuleLog);
                        $bothLow = AmazonAdsSbidRule::isBothBelowUtilLow($ub2, $ub1, $sbidRuleLog);
                        $campaignBudgetMap[$campaignId] = $sbid;
                        $campaignDetails[$campaignId] = [
                            'name' => $campaignName,
                            'bid' => $sbid,
                            'ub7' => round($ub7, 2),
                            'ub2' => round($ub2, 2),
                            'ub1' => round($ub1, 2),
                            'over_utilized' => $bothHigh,
                            'under_utilized' => $bothLow,
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
                $this->info("  - 7UB: " . ($details['ub7'] ?? 0) . "% | 2UB: " . ($details['ub2'] ?? 0) . "% | 1UB: " . ($details['ub1'] ?? 0) . "%");
                $this->info("  - Pink+Pink (over): " . (!empty($details['over_utilized']) ? 'Yes' : 'No') . " | Red+Red (under): " . (!empty($details['under_utilized']) ? 'Yes' : 'No'));
                $this->info("  - INV: " . ($details['inv'] ?? 0));
                $this->info("---");
            }
            $this->info("========================================");

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
                    
                    $result = $updateKwBids->updateAutoCampaignSbKeywordsBid($campaignIds, $newBids);
                    
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
            
            $this->info("Amazon HL Bids Update completed. Total campaigns: " . count($campaignIds));

            if ($result && is_array($result) && ($result['status'] ?? 0) == 200) {
                $persistedRows = 0;
                foreach ($campaignBudgetMap as $cid => $bid) {
                    $persistedRows += AmazonBidUtilizationService::persistSbSbidM((string) $cid, (float) $bid);
                }
                Log::info('amazon:auto-update-over-hl-bids persisted sbid_m to L30', [
                    'campaigns' => count($campaignBudgetMap),
                    'l30_rows_updated' => $persistedRows,
                ]);
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

    public function getAutomateAmzUtilizedBgtHl()
    {
        try {
            $sbidRule = AmazonAdsSbidRule::resolvedRule();
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
            
            if (!empty($skus)) {
                $shopifyData = ShopifySku::mapByProductSkus($skus);
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

        $latestDateSb = DB::table('amazon_sb_campaign_reports')
            ->where('ad_type', 'SPONSORED_BRANDS')
            ->whereRaw("report_date_range REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->max('report_date_range');
        $l2DateSb = $latestDateSb
            ? date('Y-m-d', strtotime($latestDateSb.' -1 day'))
            : date('Y-m-d', strtotime('-2 days'));
        $amazonSpCampaignReportsL2 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', $l2DateSb)
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $amazonSpCampaignReportsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where('report_date_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->get();

        $result = [];
        $processedCampaignIds = []; // Track to avoid processing same campaign multiple times

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                // Normalize spaces: replace multiple spaces with single space
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                $expected1 = $cleanSku;                
                $expected2 = $cleanSku . ' HEAD';      

                return ($cleanName === $expected1 || $cleanName === $expected2);
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                // Normalize spaces: replace multiple spaces with single space
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                $expected1 = $cleanSku;
                $expected2 = $cleanSku . ' HEAD';

                return ($cleanName === $expected1 || $cleanName === $expected2);
            });

            $matchedCampaignL2 = $amazonSpCampaignReportsL2->first(function ($item) use ($sku) {
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                $expected1 = $cleanSku;
                $expected2 = $cleanSku . ' HEAD';

                return ($cleanName === $expected1 || $cleanName === $expected2);
            });

            $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName)));
                $cleanSku = preg_replace('/\s+/', ' ', $sku);
                $expected1 = $cleanSku;
                $expected2 = $cleanSku . ' HEAD';
                return ($cleanName === $expected1 || $cleanName === $expected2);
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

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['campaign_id'] = $campaignId;
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignStatus'] = strtoupper(trim($matchedCampaignL7->campaignStatus ?? ($matchedCampaignL1->campaignStatus ?? 'PAUSED')));
            // Align HL budget source with frontend preference (L30 first, then L2/L7/L1 fallback).
            $budgetCandidates = [
                floatval(($matchedCampaignL30 ? $matchedCampaignL30->campaignBudgetAmount : null) ?? 0),
                floatval($matchedCampaignL2 ? ($matchedCampaignL2->campaignBudgetAmount ?? 0) : 0),
                floatval($matchedCampaignL7->campaignBudgetAmount ?? 0),
                floatval($matchedCampaignL1->campaignBudgetAmount ?? 0),
            ];
            $budgetCandidates = array_values(array_filter($budgetCandidates, function ($v) {
                return $v > 0;
            }));
            $utilizationBudget = !empty($budgetCandidates) ? $budgetCandidates[0] : 0;
            $row['campaignBudgetAmount'] = $utilizationBudget;
            $row['utilization_budget'] = $utilizationBudget;
            $row['l7_spend'] = $matchedCampaignL7->cost ?? 0;

            $costPerClick7 = ($matchedCampaignL7 && $matchedCampaignL7->clicks > 0)
                ? ($matchedCampaignL7->cost / $matchedCampaignL7->clicks)
                : 0;

            $costPerClick1 = ($matchedCampaignL1 && $matchedCampaignL1->clicks > 0)
                ? ($matchedCampaignL1->cost / $matchedCampaignL1->clicks)
                : 0;

            $row['l7_cpc']   = $costPerClick7;
            $row['l1_spend'] = $matchedCampaignL1->cost ?? 0;
            $row['l1_cpc']   = $costPerClick1;
            $row['l2_spend'] = $matchedCampaignL2 ? (float) ($matchedCampaignL2->cost ?? 0) : 0;
            $costPerClick2 = ($matchedCampaignL2 && $matchedCampaignL2->clicks > 0)
                ? ($matchedCampaignL2->cost / $matchedCampaignL2->clicks)
                : 0;
            $row['l2_cpc'] = $costPerClick2;

            // Calculate avg_cpc (lifetime average from daily records)
            $avgCpc = 0;
            try {
                $avgCpcRecord = DB::table('amazon_sb_campaign_reports')
                    ->select(DB::raw('AVG(CASE WHEN clicks > 0 THEN cost / clicks ELSE 0 END) as avg_cpc'))
                    ->where('campaign_id', $campaignId)
                    ->where('ad_type', 'SPONSORED_BRANDS')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
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

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            $resolved = AmazonBidUtilizationService::resolveUb(
                (string) $row['campaign_id'],
                'hl',
                ['ub7' => $ub7, 'ub1' => $ub1]
            );
            $ub7 = $resolved['ub7'];
            $ub1 = $resolved['ub1'];
            $ubSource = $resolved['source'];

            $l1_cpc = floatval($row['l1_cpc']);
            $l2_cpc = floatval($row['l2_cpc'] ?? 0);
            $l7_cpc = floatval($row['l7_cpc']);
            $l2_spend = floatval($row['l2_spend'] ?? 0);
            $ub2 = AmazonBidUtilizationService::ub2PercentFromL2Spend($budget, $l2_spend);

            $cpcFallback = ($l1_cpc <= 0 && $l2_cpc <= 0 && $l7_cpc <= 0) ? max($l1_cpc, $l7_cpc) : null;
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

            $baseBid = ($matchedCampaignL30 ? floatval($matchedCampaignL30->last_sbid ?? 0) : 0);
            if ($baseBid <= 0) {
                $baseBid = $l1_cpc > 0 ? $l1_cpc : ($l7_cpc > 0 ? $l7_cpc : 0);
            }

            // Validate all required fields before adding
            if (empty($row['campaign_id'])) {
                continue; // Skip if no campaign ID
            }

            if ($row['sbid'] === null || ! is_numeric($row['sbid']) || $row['sbid'] <= 0) {
                continue;
            }

            $bothLow = AmazonAdsSbidRule::isBothBelowUtilLow($ub2, $ub1, $sbidRule);
            $bothHigh = AmazonAdsSbidRule::isBothAboveUtilHigh($ub2, $ub1, $sbidRule);
            if (! (($bothLow && $bidOut['band'] === 'under') || ($bothHigh && $bidOut['band'] === 'over'))) {
                continue;
            }

            if (($row['campaignStatus'] ?? '') !== 'ENABLED') {
                continue;
            }

            AmazonBidUtilizationService::logBidDecision(
                (string) $row['campaign_id'],
                $bidOut['band'] === 'over' ? 'hl_over' : 'hl_under',
                $ub1,
                $baseBid > 0 ? $baseBid : ($l1_cpc > 0 ? $l1_cpc : ($l2_cpc > 0 ? $l2_cpc : ($l7_cpc > 0 ? $l7_cpc : 0.0))),
                (float) $row['sbid'],
                $ubSource
            );

            $result[] = (object) $row;

        }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getAutomateAmzUtilizedBgtHl: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

}