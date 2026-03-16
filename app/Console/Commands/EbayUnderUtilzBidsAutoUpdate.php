<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\EbayOverUtilizedBgtController;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EbayUnderUtilzBidsAutoUpdate extends Command
{
    protected $signature = 'ebay:auto-update-under-bids';

    protected $description = 'Automatically update Ebay campaign keyword bids';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            // Set unlimited execution time for long-running processes
            set_time_limit(0);
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '1024M');

            // Check database connection (without creating persistent connection)
            try {
                DB::connection()->getPdo();
                $this->info('✓ Database connection OK');
                // Immediately disconnect after check to prevent connection buildup
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error('✗ Database connection failed: '.$e->getMessage());

                return 1;
            }

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('🚀 Starting eBay Under-Utilized Bids Auto-Update');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            $updateOverUtilizedBids = new EbayOverUtilizedBgtController;

            $campaigns = $this->getEbayOverUtilizCampaign();

            if (empty($campaigns)) {
                $this->warn('⚠️  No campaigns matched filter conditions.');

                return 0;
            }

            // Filter out campaigns with empty campaign_id or zero/blank sbid
            $validCampaigns = array_filter($campaigns, function ($campaign) {
                return ! empty($campaign->campaign_id) && ! empty($campaign->sbid) && floatval($campaign->sbid) > 0;
            });

            if (empty($validCampaigns)) {
                $this->warn('⚠️  No valid campaigns found (all have empty campaign_id or zero/blank sbid).');

                return 0;
            }

            $this->info('📊 Found '.count($validCampaigns).' campaigns to update');
            $this->info('');

            // Log all campaigns before update with detailed SBID calculation info
            $this->info('📋 Campaigns to be updated (with SBID calculation details):');
            foreach ($validCampaigns as $index => $campaign) {
                $campaignName = $campaign->campaign_name ?? 'Unknown';
                $campaignId = $campaign->campaign_id ?? 'N/A';
                $newBid = $campaign->sbid ?? 0;
                $lastSbid = !empty($campaign->last_sbid) && $campaign->last_sbid !== '0' ? (float)$campaign->last_sbid : 0;
                $l1Cpc = $campaign->l1_cpc ?? 0;
                $l7Cpc = $campaign->l7_cpc ?? 0;

                // Calculate UB7 and UB1 for display
                $budget = floatval($campaign->campaignBudgetAmount ?? 0);
                $l7Spend = floatval($campaign->l7_spend ?? 0);
                $l1Spend = floatval($campaign->l1_spend ?? 0);
                $ub7 = $budget > 0 ? ($l7Spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1Spend / $budget) * 100 : 0;

                // Get rule applied and base bid source from campaign object
                $ruleApplied = $campaign->rule_applied ?? 'Unknown rule';
                $baseBidSource = $campaign->base_bid_source ?? 'unknown';

                // Calculate base bid for display
                $baseBid = 0;
                if ($lastSbid > 0) {
                    $baseBid = $lastSbid;
                } elseif ($l1Cpc > 0) {
                    $baseBid = $l1Cpc;
                } elseif ($l7Cpc > 0) {
                    $baseBid = $l7Cpc;
                }

                $this->line('   '.($index + 1).". Campaign: {$campaignName}");
                $this->line("       ID: {$campaignId} | UB7: ".number_format($ub7, 2).'% | UB1: '.number_format($ub1, 2).'%');
                $this->line("       Base Bid: \${$baseBid} (Source: {$baseBidSource}) | Last SBID: \${$lastSbid} | New SBID: \${$newBid}");
                $this->line("       Rule Applied: {$ruleApplied}");
                $this->line('');
            }

            $this->info('');

            $campaignNames = collect($validCampaigns)->pluck('campaign_name', 'campaign_id')->toArray();

            // Create mapping of campaign_id to bid for easy lookup
            $campaignBidMap = [];
            foreach ($validCampaigns as $campaign) {
                $campaignBidMap[$campaign->campaign_id] = $campaign->sbid ?? 0;
            }

            // Process campaigns in batches to avoid timeout
            $batchSize = 5; // Process 5 campaigns at a time
            $campaignBatches = array_chunk($validCampaigns, $batchSize);
            $totalBatches = count($campaignBatches);

            $this->info("🔄 Updating bids via eBay API (processing in {$totalBatches} batches of {$batchSize} campaigns each)...");
            $this->info('');

            $allResults = [];
            $totalSuccess = 0;
            $totalFailed = 0;
            $hasError = false;

        foreach ($campaignBatches as $batchIndex => $batch) {
            $batchNumber = $batchIndex + 1;
            $this->info("📦 Processing batch {$batchNumber}/{$totalBatches}:");
            
            // Log campaigns in this batch before processing
            foreach ($batch as $index => $campaign) {
                $campaignId = $campaign->campaign_id ?? 'unknown';
                $sbid = $campaign->sbid ?? 0;
                $campaignName = $campaign->campaign_name ?? 'Unknown';
                $lastSbid = !empty($campaign->last_sbid) && $campaign->last_sbid !== '0' ? (float)$campaign->last_sbid : 0;
                
                $this->line("   " . ($index + 1) . ". Campaign: {$campaignName} | ID: {$campaignId} | Last SBID: \${$lastSbid} | New SBID: \${$sbid}");
            }
            $this->info("");
            
            $campaignIds = collect($batch)->pluck('campaign_id')->toArray();
            $newBids = collect($batch)->pluck('sbid')->toArray();

                try {
                    $result = $updateOverUtilizedBids->updateAutoKeywordsBidDynamic($campaignIds, $newBids);

                    // Parse the result
                    $resultData = $result->getData(true);
                    $status = $resultData['status'] ?? 'unknown';
                    $data = $resultData['data'] ?? [];

                    if ($status != 200) {
                        $hasError = true;
                    }

                    // Merge results
                    $allResults = array_merge($allResults, $data);

                    // Count successes and failures for this batch
                    foreach ($data as $item) {
                        if (($item['status'] ?? '') === 'error') {
                            $totalFailed++;
                        } else {
                            $totalSuccess++;
                        }
                    }

                    $this->info("   ✅ Batch {$batchNumber} completed");

                } catch (\Exception $e) {
                    $hasError = true;
                    $this->error("   ❌ Batch {$batchNumber} failed: ".$e->getMessage());

                    // Add error entries for all campaigns in this batch
                    foreach ($batch as $campaign) {
                        $allResults[] = [
                            'campaign_id' => $campaign->campaign_id ?? 'unknown',
                            'status' => 'error',
                            'message' => $e->getMessage(),
                        ];
                        $totalFailed++;
                    }
                }

                // Small delay between batches to avoid rate limiting
                if ($batchIndex < $totalBatches - 1) {
                    sleep(2);
                }
            }

            $this->info('');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📊 Update Results');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('Status: '.(! $hasError ? '✅ Success' : ($totalSuccess > 0 ? '⚠️  Partial Success' : '❌ Failed')));
            $this->info('');

            // Group results by campaign_id
            $campaignResults = [];
            foreach ($allResults as $item) {
                $campId = $item['campaign_id'] ?? 'unknown';
                if (! isset($campaignResults[$campId])) {
                    $campaignResults[$campId] = [
                        'campaign_name' => $campaignNames[$campId] ?? 'Unknown',
                        'success' => 0,
                        'failed' => 0,
                        'errors' => [],
                    ];
                }

                if (($item['status'] ?? '') === 'error') {
                    $campaignResults[$campId]['failed']++;
                    $campaignResults[$campId]['errors'][] = $item['message'] ?? 'Unknown error';
                } else {
                    $campaignResults[$campId]['success']++;
                }
            }

            // Display results per campaign
            foreach ($campaignResults as $campId => $result) {
                $campaignName = $result['campaign_name'];
                $success = $result['success'];
                $failed = $result['failed'];
                $newBid = $campaignBidMap[$campId] ?? 'N/A';

            if ($failed > 0) {
                $this->warn("   ❌ Campaign: {$campaignName}");
                $this->warn("      ID: {$campId} | SBID: \${$newBid} | Success: {$success} keywords | Failed: {$failed} keywords");
                foreach (array_unique($result['errors']) as $error) {
                    $this->error("      Error: {$error}");
                }
            } else {
                $this->info("   ✅ Campaign: {$campaignName}");
                $this->info("      ID: {$campId} | SBID: \${$newBid} | Updated: {$success} keywords");
            }
            }

            $this->info('');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("📈 Summary: {$totalSuccess} keywords updated successfully, {$totalFailed} failed");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            return 0;
        } catch (\Exception $e) {
            $this->error('✗ Error occurred: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getEbayOverUtilizCampaign()
    {
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            if ($productMasters->isEmpty()) {
                $this->warn('No product masters found in database!');

                return [];
            }

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            if (empty($skus)) {
                $this->warn('No valid SKUs found!');

                return [];
            }

            // SKU normalization function
            $normalizeSku = function ($sku) {
                $sku = trim($sku);
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);

                return strtoupper($sku);
            };

            $shopifyData = [];
            $nrValues = [];
            $ebayMetricData = [];

            if (! empty($skus)) {
                // Normalize ShopifySku data keys
                $shopifyRaw = ShopifySku::whereIn('sku', $skus)->get();
                $shopifyData = collect();
                foreach ($shopifyRaw as $item) {
                    $normalizedKey = $normalizeSku($item->sku);
                    $shopifyData[$normalizedKey] = $item;
                }

                $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

                // Normalize EbayMetric data keys
                $ebayMetricRaw = EbayMetric::whereIn('sku', $skus)->get();
                $ebayMetricData = collect();
                foreach ($ebayMetricRaw as $item) {
                    $normalizedKey = $normalizeSku($item->sku);
                    $ebayMetricData[$normalizedKey] = $item;
                }
            }

            $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%'.$sku.'%');
                    }
                })
                ->get();

            $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaign_name', 'LIKE', '%'.$sku.'%');
                    }
                })
                ->get();

            // Fetch last_sbid from day-before-yesterday's date records
            $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
            $lastSbidReports = EbayPriorityReport::where('report_range', $dayBeforeYesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->get();
            
            $lastSbidMap = [];
            foreach ($lastSbidReports as $report) {
                if (!empty($report->campaign_id) && !empty($report->last_sbid)) {
                    $lastSbidMap[$report->campaign_id] = $report->last_sbid;
                }
            }

            $result = [];

            foreach ($productMasters as $pm) {
                $normalizedSku = $normalizeSku($pm->sku);

                $shopify = $shopifyData[$normalizedSku] ?? null;

                $ebay = $ebayMetricData[$normalizedSku] ?? null;

                $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($normalizedSku, $normalizeSku) {
                    $campaignName = $normalizeSku(rtrim($item->campaign_name, '.'));

                    return $campaignName === $normalizedSku;
                });

                $matchedCampaignL1 = $ebayCampaignReportsL1->first(function ($item) use ($normalizedSku, $normalizeSku) {
                    $campaignName = $normalizeSku(rtrim($item->campaign_name, '.'));

                    return $campaignName === $normalizedSku;
                });

                if (! $matchedCampaignL7 && ! $matchedCampaignL1) {
                    continue;
                }

                $row = [];
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;
                $row['price'] = $ebay->ebay_price ?? 0;
            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaign_id'] = $campaignId;
            $row['campaign_name'] = $matchedCampaignL7->campaign_name ?? ($matchedCampaignL1->campaign_name ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');

            $row['l7_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0');
            $row['l7_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cost_per_click ?? '0');
            $row['l1_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0');
            $row['l1_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cost_per_click ?? '0');
            $row['last_sbid'] = $lastSbidMap[$campaignId] ?? '';

                $l1_cpc = floatval($row['l1_cpc']);
                $l7_cpc = floatval($row['l7_cpc']);

                $budget = floatval($row['campaignBudgetAmount']);
                $l7_spend = floatval($row['l7_spend']);
                $l1_spend = floatval($row['l1_spend']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // Calculate SBID using new UB1-based rules
            // Get base bid from last_sbid, fallback to L1_CPC or L7_CPC if last_sbid is 0
            $lastSbidRaw = $row['last_sbid'] ?? '';
            $baseBid = 0;
            
            // Parse last_sbid, treat empty/0 as 0
            if (!empty($lastSbidRaw) && $lastSbidRaw !== '0' && $lastSbidRaw !== 0) {
                $baseBid = floatval($lastSbidRaw);
                if (is_nan($baseBid) || $baseBid <= 0) {
                    $baseBid = 0;
                }
            }
            
            // If last_sbid is 0, use L1_CPC or L7_CPC as fallback
            if ($baseBid == 0) {
                $baseBid = $l1_cpc > 0 ? $l1_cpc : ($l7_cpc > 0 ? $l7_cpc : 0);
            }
            
            if ($baseBid > 0) {
                // If UB1 < 33%: increase bid by 0.10
                if ($ub1 < 33) {
                    $row['sbid'] = floor(($baseBid + 0.10) * 100) / 100;
                    $row['rule_applied'] = "UB1 < 33%: Base Bid (\${$baseBid}) + \$0.10";
                }
                // If UB1 is 33% to 66%: increase bid by 10%
                elseif ($ub1 >= 33 && $ub1 < 66) {
                    $row['sbid'] = floor($baseBid * 1.10 * 100) / 100;
                    $row['rule_applied'] = "UB1 33%-66%: Base Bid (\${$baseBid}) × 1.10";
                } else {
                    // For UB1 >= 66%, use base bid (no increase)
                    $row['sbid'] = floor($baseBid * 100) / 100;
                    $row['rule_applied'] = "UB1 ≥ 66%: Base Bid (\${$baseBid}) - No increase";
                }
            } else {
                $row['sbid'] = 0;
                $row['rule_applied'] = "No base bid available (last_sbid/L1CPC/L7CPC all zero)";
            }
            
            // Store base bid source for logging
            if (!empty($lastSbidRaw) && $lastSbidRaw !== '0' && $lastSbidRaw !== 0) {
                $row['base_bid_source'] = "last_sbid";
            } elseif ($l1_cpc > 0) {
                $row['base_bid_source'] = "L1CPC";
            } elseif ($l7_cpc > 0) {
                $row['base_bid_source'] = "L7CPC";
            } else {
                $row['base_bid_source'] = "none";
            }

                $row['NR'] = '';
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (! is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $row['NR'] = $raw['NR'] ?? null;
                    }
                }

                if ($row['NR'] !== 'NRA' && $ub7 < 66 && $ub1 < 66 && $row['INV'] > 0) {
                    $result[] = (object) $row;
                }

            }

            DB::connection()->disconnect();

            return $result;

        } catch (\Exception $e) {
            $this->error('Error in getEbayOverUtilizCampaign: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

    private function getDilColor($l30, $inv)
    {
        if ($inv == 0) {
            return 'red';
        }

        $percent = ($l30 / $inv) * 100;

        if ($percent < 16.66) {
            return 'red';
        }
        if ($percent >= 16.66 && $percent < 25) {
            return 'yellow';
        }
        if ($percent >= 25 && $percent < 50) {
            return 'green';
        }

        return 'pink';
    }
}
