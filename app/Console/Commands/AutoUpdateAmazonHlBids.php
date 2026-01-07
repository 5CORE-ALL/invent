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
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

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
            
            foreach ($campaigns as $campaign) {
                $campaignId = $campaign->campaign_id ?? '';
                $sbid = $campaign->sbid ?? 0;
                $campaignName = $campaign->campaignName ?? '';
                
                if (!empty($campaignId) && $sbid > 0) {
                    // Only add if we haven't seen this campaign ID before
                    if (!isset($campaignBudgetMap[$campaignId])) {
                        $campaignBudgetMap[$campaignId] = $sbid;
                        $campaignDetails[$campaignId] = [
                            'name' => $campaignName,
                            'bid' => $sbid
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
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
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

            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // Calculate SBID for HL campaigns (no price-based rules)
            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);
            
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

            // Validate all required fields before adding
            if (empty($row['campaign_id'])) {
                continue; // Skip if no campaign ID
            }
            
            if (!is_numeric($row['sbid']) || $row['sbid'] <= 0) {
                continue; // Skip if invalid bid
            }

            if ($ub7 > 99 && $ub1 > 99) {
                $result[] = (object) $row;
            }

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