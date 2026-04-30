<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\Ebay3UtilizedAdsController;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayThreeDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Ebay3UtilizedBidsAutoUpdate extends Command
{
    protected $signature = 'ebay3:auto-update-utilized-bids {--dry-run : Run without actually updating bids}';
    protected $description = 'Automatically update eBay3 campaign keyword bids for over and under-utilized campaigns';

    public function handle()
    {
        try {
            set_time_limit(0);
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '1024M');

            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $isDryRun = $this->option('dry-run');
            
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🚀 Starting eBay3 Utilized Bids Auto-Update");
            if ($isDryRun) {
                $this->warn("⚠️  DRY-RUN MODE: No bids will be updated on eBay");
            }
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            $updateUtilizedBids = $isDryRun ? null : new Ebay3UtilizedAdsController;

            $campaigns = $this->getEbay3UtilizedCampaigns();

            if (empty($campaigns)) {
                $this->warn("⚠️  No campaigns matched filter conditions.");
                return 0;
            }

            $validCampaigns = array_filter($campaigns, fn($campaign) => !empty($campaign->campaign_id) && !empty($campaign->sbid) && floatval($campaign->sbid) > 0);
            
            if (empty($validCampaigns)) {
                $this->warn("⚠️  No valid campaigns found (all have empty campaign_id or zero/blank sbid).");
                return 0;
            }
            
            $overCount = count(array_filter($validCampaigns, fn($c) => $c->isOverUtilized ?? false));
            $underCount = count($validCampaigns) - $overCount;
            
            $this->info("📊 Found " . count($validCampaigns) . " campaigns to update (Over: {$overCount}, Under: {$underCount})");
            $this->info("");

            $this->info("📋 Campaigns to be updated:");
            foreach ($validCampaigns as $index => $campaign) {
                $campaignName = $campaign->campaignName ?? 'Unknown';
                $campaignId = $campaign->campaign_id ?? 'N/A';
                $newBid = $campaign->sbid ?? 0;
                $lastSbid = !empty($campaign->last_sbid) && $campaign->last_sbid !== '0' ? (float)$campaign->last_sbid : 0;
                $type = ($campaign->isOverUtilized ?? false) ? 'Over' : 'Under';
                
                $this->line("   " . ($index + 1) . ". [{$type}] Campaign: {$campaignName} | ID: {$campaignId} | Last SBID: \${$lastSbid} | New SBID: \${$newBid}");
            }
            
            $this->info("");

            $campaignNames = collect($validCampaigns)->pluck('campaignName', 'campaign_id')->toArray();
            $campaignBidMap = collect($validCampaigns)->pluck('sbid', 'campaign_id')->toArray();

            $batchSize = 5;
            $campaignBatches = array_chunk($validCampaigns, $batchSize);
            $totalBatches = count($campaignBatches);
            
            if ($isDryRun) {
                $this->info("🔍 DRY-RUN: Would update bids for {$totalBatches} batch(es)");
                $this->info("");
                
                $allResults = [];
                $totalSuccess = 0;
                $totalFailed = 0;
                $hasError = false;
                
                foreach ($campaignBatches as $batchIndex => $batch) {
                    $batchNumber = $batchIndex + 1;
                    $this->info("📦 [DRY-RUN] Batch {$batchNumber}/{$totalBatches}:");
                    
                    foreach ($batch as $index => $campaign) {
                        $campaignId = $campaign->campaign_id ?? 'unknown';
                        $sbid = $campaign->sbid ?? 0;
                        $campaignName = $campaign->campaignName ?? 'Unknown';
                        $lastSbid = !empty($campaign->last_sbid) && $campaign->last_sbid !== '0' ? (float)$campaign->last_sbid : 0;
                        
                        $this->line("   " . ($index + 1) . ". Campaign: {$campaignName}");
                        $this->line("      ID: {$campaignId} | Last SBID: \${$lastSbid} | New SBID: \${$sbid}");
                        
                        $allResults[] = [
                            "campaign_id" => $campaignId,
                            "campaign_name" => $campaignName,
                            "status" => "success",
                            "message" => "DRY-RUN: Would update bid from \${$lastSbid} to \${$sbid}"
                        ];
                        $totalSuccess++;
                    }
                    $this->info("");
                }
            } else {
                $this->info("🔄 Updating bids via eBay3 API (processing in {$totalBatches} batches of {$batchSize} campaigns each)...");
                $this->info("");

                $allResults = [];
                $totalSuccess = 0;
                $totalFailed = 0;
                $hasError = false;

                foreach ($campaignBatches as $batchIndex => $batch) {
                    $batchNumber = $batchIndex + 1;
                    $this->info("📦 Processing batch {$batchNumber}/{$totalBatches}:");
                    
                    foreach ($batch as $index => $campaign) {
                        $campaignId = $campaign->campaign_id ?? 'unknown';
                        $sbid = $campaign->sbid ?? 0;
                        $campaignName = $campaign->campaignName ?? 'Unknown';
                        $lastSbid = !empty($campaign->last_sbid) && $campaign->last_sbid !== '0' ? (float)$campaign->last_sbid : 0;
                        
                        $this->line("   " . ($index + 1) . ". Campaign: {$campaignName} | ID: {$campaignId} | Last SBID: \${$lastSbid} | New SBID: \${$sbid}");
                    }
                    $this->info("");
                    
                    $campaignIds = collect($batch)->pluck('campaign_id')->toArray();
                    $newBids = collect($batch)->pluck('sbid')->toArray();
                    
                    try {
                        $result = $updateUtilizedBids->updateAutoKeywordsBidDynamic($campaignIds, $newBids);
                        
                        $resultData = $result->getData(true);
                        $status = $resultData['status'] ?? 'unknown';
                        $data = $resultData['data'] ?? [];
                        
                        if ($status != 200) {
                            $hasError = true;
                        }
                        
                        $allResults = array_merge($allResults, $data);
                        
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
                        $this->error("   ❌ Batch {$batchNumber} failed: " . $e->getMessage());
                        
                        foreach ($batch as $campaign) {
                            $allResults[] = [
                                "campaign_id" => $campaign->campaign_id ?? 'unknown',
                                "status" => "error",
                                "message" => $e->getMessage(),
                            ];
                            $totalFailed++;
                        }
                    }
                    
                    if ($batchIndex < $totalBatches - 1) {
                        sleep(2);
                    }
                }
            }

            $this->info("");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📊 Update Results");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Status: " . (!$hasError ? "✅ Success" : ($totalSuccess > 0 ? "⚠️  Partial Success" : "❌ Failed")));
            $this->info("");

            $campaignResults = [];
            foreach ($allResults as $item) {
                $campId = $item['campaign_id'] ?? 'unknown';
                if (!isset($campaignResults[$campId])) {
                    $campaignResults[$campId] = [
                        'campaign_name' => $campaignNames[$campId] ?? 'Unknown',
                        'success' => 0,
                        'failed' => 0,
                        'errors' => []
                    ];
                }
                
                if (($item['status'] ?? '') === 'error') {
                    $campaignResults[$campId]['failed']++;
                    $campaignResults[$campId]['errors'][] = $item['message'] ?? 'Unknown error';
                } else {
                    $campaignResults[$campId]['success']++;
                }
            }

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

            $this->info("");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            if ($isDryRun) {
                $this->info("📈 DRY-RUN Summary: {$totalSuccess} keywords would be updated, {$totalFailed} would fail");
            } else {
                $this->info("📈 Summary: {$totalSuccess} keywords updated successfully, {$totalFailed} failed");
            }
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getEbay3UtilizedCampaigns()
    {
        try {
            $normalizeSku = fn ($sku) => trim(preg_replace('/[^\S\r\n]+/u', ' ', strtoupper(trim($sku))));

            $productMasters = ProductMaster::whereNull('deleted_at')
                ->orderBy('parent', 'asc')
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

            $shopifyByPm = ShopifySku::mapByProductSkus($skus);
            $shopifyData = [];
            foreach ($productMasters as $pm) {
                $nk = $normalizeSku($pm->sku);
                if ($nk === '') {
                    continue;
                }
                $row = $shopifyByPm->get($pm->sku);
                if ($row !== null) {
                    $shopifyData[$nk] = $row;
                }
            }
            $ebayMetricData = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
            $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->orderBy('report_range', 'asc')
                ->get();

            $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
            $lastSbidMap = Ebay3PriorityReport::where('report_range', $dayBeforeYesterday)
                ->where('campaignStatus', 'RUNNING')
                ->where('campaign_name', 'NOT LIKE', 'Campaign %')
                ->where('campaign_name', 'NOT LIKE', 'General - %')
                ->where('campaign_name', 'NOT LIKE', 'Default%')
                ->pluck('last_sbid', 'campaign_id')
                ->filter();
            
            $result = [];
            $campaignMap = [];

            foreach ($productMasters as $pm) {
                // Only process PARENT SKUs for Ebay3
                if (stripos($pm->sku, 'PARENT') === false) {
                    continue;
                }

                $normalizedSku = $normalizeSku($pm->sku);
                $sku = $normalizedSku;
                $shopify = $shopifyData[$normalizedSku] ?? ShopifySku::firstForProductSku($pm->sku);
                $ebay = $ebayMetricData[$normalizedSku] ?? Ebay3Metric::where('sku', $pm->sku)->first();

                $nrValue = '';
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $nrValue = $raw['NR'] ?? null;
                    }
                }

                if ($nrValue == 'NRA') {
                    continue;
                }

                $matchedReports = $reports->filter(function ($item) use ($sku, $normalizeSku) {
                    $campaignName = $item->campaign_name ?? '';
                    $normalizedCampaignName = $normalizeSku($campaignName);
                    return $normalizedCampaignName === $sku;
                });

                if ($matchedReports->isEmpty()) {
                    continue;
                }

                foreach ($matchedReports as $campaign) {
                    $campaignId = $campaign->campaign_id ?? '';
                    
                    if (empty($campaignId)) {
                        continue;
                    }

                    if (!isset($campaignMap[$campaignId])) {
                        $campaignMap[$campaignId] = [
                            'sku' => $pm->sku,
                            'campaign_id' => $campaignId,
                            'campaignName' => $campaign->campaign_name ?? '',
                            'campaignBudgetAmount' => $campaign->campaignBudgetAmount ?? 0,
                            'campaignStatus' => $campaign->campaignStatus ?? '',
                            'INV' => (int)($shopify->inv ?? 0),
                            'L30' => (int)($shopify->quantity ?? 0),
                            'l7_spend' => 0,
                            'l7_cpc' => 0,
                            'l1_spend' => 0,
                            'l1_cpc' => 0,
                            'acos' => 0,
                            'NR' => $nrValue,
                            'price' => (float)($ebay->ebay_price ?? 0),
                            'ebay_l30' => (float)($ebay->ebay_l30 ?? 0),
                            'views' => (float)($ebay->views ?? 0),
                            'last_sbid' => $lastSbidMap[$campaignId] ?? '',
                        ];
                    }

                    $reportRange = $campaign->report_range ?? '';
                    $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                    $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
                    $cpc = (float) str_replace(['USD ', ','], '', $campaign->cost_per_click ?? '0');

                    if ($reportRange == 'L7') {
                        $campaignMap[$campaignId]['l7_spend'] = $adFees;
                        $campaignMap[$campaignId]['l7_cpc'] = $cpc;
                    } elseif ($reportRange == 'L1') {
                        $campaignMap[$campaignId]['l1_spend'] = $adFees;
                        $campaignMap[$campaignId]['l1_cpc'] = $cpc;
                    } elseif ($reportRange == 'L30') {
                        $campaignMap[$campaignId]['acos'] = $sales > 0 ? round(($adFees / $sales) * 100, 2) : ($adFees > 0 ? 100 : 0);
                    }
                }
            }

            foreach ($campaignMap as $campaignId => $row) {
                if ($row['campaignStatus'] !== 'RUNNING') {
                    continue;
                }

                $l7_spend = (float)$row['l7_spend'];
                $l1_cpc = (float)$row['l1_cpc'];
                $l7_cpc = (float)$row['l7_cpc'];
                $l1_spend = (float)$row['l1_spend'];
                $budget = (float)$row['campaignBudgetAmount'];
                $price = (float)$row['price'];
                $inv = (float)$row['INV'];

                $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;
                
                // Over-utilized: both UB7 and UB1 > 99%
                $isOverUtilized = ($ub7 > 99 && $ub1 > 99);
                
                // Under-utilized: both UB7 and UB1 < 66%
                $isUnderUtilized = !$isOverUtilized && $ub7 < 66 && $ub1 < 66;

                if (!$isOverUtilized && !$isUnderUtilized) {
                    continue;
                }

                $sbid = 0;

                // PMT S BID rule based on SCVR (CVR color thresholds)
                $ebayL30Sold = floatval($row['ebay_l30'] ?? 0);
                $ebayViews   = floatval($row['views'] ?? 0);

                if ($ebayL30Sold == 0) {
                    $sbid = 0; // 0 sold → no bid
                } elseif ($ebayViews <= 0) {
                    $sbid = 0;
                } else {
                    $scvr = ($ebayL30Sold / $ebayViews) * 100;
                    if ($scvr <= 4) {
                        $sbid = 9.1;
                    } elseif ($scvr <= 7) {
                        $sbid = 7.1;
                    } elseif ($scvr <= 13) {
                        $sbid = 4.1;
                    } else {
                        $sbid = 2.1;
                    }
                }

                if ($sbid > 0) {
                    $row['sbid'] = $sbid;
                    $row['isOverUtilized'] = $isOverUtilized;
                    $row['isUnderUtilized'] = $isUnderUtilized;
                    $result[] = (object) $row;
                }
            }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getEbay3UtilizedCampaigns: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }
}
