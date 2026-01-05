<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\EbayOverUtilizedBgtController;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;

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
        // Set unlimited execution time for long-running processes
        set_time_limit(0);
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🚀 Starting eBay Under-Utilized Bids Auto-Update");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $updateOverUtilizedBids = new EbayOverUtilizedBgtController;

        $campaigns = $this->getEbayOverUtilizCampaign();

        if (empty($campaigns)) {
            $this->warn("⚠️  No campaigns matched filter conditions.");
            return 0;
        }

        // Filter out campaigns with empty campaign_id or zero/blank sbid
        $validCampaigns = array_filter($campaigns, function($campaign) {
            return !empty($campaign->campaign_id) && !empty($campaign->sbid) && floatval($campaign->sbid) > 0;
        });
        
        if (empty($validCampaigns)) {
            $this->warn("⚠️  No valid campaigns found (all have empty campaign_id or zero/blank sbid).");
            return 0;
        }
        
        $this->info("📊 Found " . count($validCampaigns) . " campaigns to update");
        $this->info("");

        // Log all campaigns before update with detailed SBID calculation info
        $this->info("📋 Campaigns to be updated (with SBID calculation details):");
        foreach ($validCampaigns as $index => $campaign) {
            $campaignName = $campaign->campaign_name ?? 'Unknown';
            $campaignId = $campaign->campaign_id ?? 'N/A';
            $newBid = $campaign->sbid ?? 0;
            $l1Cpc = $campaign->l1_cpc ?? 0;
            $l7Cpc = $campaign->l7_cpc ?? 0;
            $price = $campaign->price ?? 0;
            
            // Calculate UB7 and UB1 for display
            $budget = floatval($campaign->campaignBudgetAmount ?? 0);
            $l7Spend = floatval($campaign->l7_spend ?? 0);
            $l1Spend = floatval($campaign->l1_spend ?? 0);
            $ub7 = $budget > 0 ? ($l7Spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1Spend / $budget) * 100 : 0;
            
            // Determine which rule was applied
            $ruleApplied = '';
            if ($ub7 == 0 && $ub1 == 0) {
                $ruleApplied = 'Price-based (UB7=0%, UB1=0%)';
            } else {
                $cpcToUse = ($l1Cpc > 0) ? $l1Cpc : (($l7Cpc > 0) ? $l7Cpc : 0);
                if ($cpcToUse > 0) {
                    if ($cpcToUse < 0.10) {
                        $ruleApplied = "CPC-based (L1CPC={$l1Cpc}, L7CPC={$l7Cpc}) × 2.00";
                    } elseif ($cpcToUse >= 0.10 && $cpcToUse <= 0.20) {
                        $ruleApplied = "CPC-based (L1CPC={$l1Cpc}, L7CPC={$l7Cpc}) × 1.50";
                    } elseif ($cpcToUse >= 0.21 && $cpcToUse <= 0.30) {
                        $ruleApplied = "CPC-based (L1CPC={$l1Cpc}, L7CPC={$l7Cpc}) × 1.25";
                    } else {
                        $ruleApplied = "CPC-based (L1CPC={$l1Cpc}, L7CPC={$l7Cpc}) × 1.10";
                    }
                    if ($price < 20) {
                        $ruleApplied .= " [Price cap: <\$20 → max 0.20]";
                    }
                } else {
                    $ruleApplied = 'Default (both CPC=0) → 0.75';
                }
            }
            
            $this->line("   " . ($index + 1) . ". Campaign: {$campaignName}");
            $this->line("       ID: {$campaignId} | Price: \${$price} | UB7: " . number_format($ub7, 2) . "% | UB1: " . number_format($ub1, 2) . "%");
            $this->line("       L1CPC: \${$l1Cpc} | L7CPC: \${$l7Cpc} | Calculated SBID: \${$newBid}");
            $this->line("       Rule Applied: {$ruleApplied}");
            $this->line("");
        }
        
        $this->info("");

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
        $this->info("");

        $allResults = [];
        $totalSuccess = 0;
        $totalFailed = 0;
        $hasError = false;

        foreach ($campaignBatches as $batchIndex => $batch) {
            $batchNumber = $batchIndex + 1;
            $this->info("📦 Processing batch {$batchNumber}/{$totalBatches}...");
            
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
                $this->error("   ❌ Batch {$batchNumber} failed: " . $e->getMessage());
                
                // Add error entries for all campaigns in this batch
                foreach ($batch as $campaign) {
                    $allResults[] = [
                        "campaign_id" => $campaign->campaign_id ?? 'unknown',
                        "status" => "error",
                        "message" => $e->getMessage(),
                    ];
                    $totalFailed++;
                }
            }
            
            // Small delay between batches to avoid rate limiting
            if ($batchIndex < $totalBatches - 1) {
                sleep(2);
            }
        }

        $this->info("");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Update Results");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Status: " . (!$hasError ? "✅ Success" : ($totalSuccess > 0 ? "⚠️  Partial Success" : "❌ Failed")));
        $this->info("");

        // Group results by campaign_id
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

        // Display results per campaign
        foreach ($campaignResults as $campId => $result) {
            $campaignName = $result['campaign_name'];
            $success = $result['success'];
            $failed = $result['failed'];
            $newBid = $campaignBidMap[$campId] ?? 'N/A';
            
            if ($failed > 0) {
                $this->warn("   ❌ Campaign: {$campaignName} (ID: {$campId}) | Bid: \${$newBid}");
                $this->warn("      Success: {$success} keywords | Failed: {$failed} keywords");
                foreach (array_unique($result['errors']) as $error) {
                    $this->error("      Error: {$error}");
                }
            } else {
                $this->info("   ✅ Campaign: {$campaignName} (ID: {$campId}) | Bid: \${$newBid} | Updated: {$success} keywords");
            }
        }

        $this->info("");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📈 Summary: {$totalSuccess} keywords updated successfully, {$totalFailed} failed");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return 0;
    }

    public function getEbayOverUtilizCampaign(){

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');

        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
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
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $shopify = $shopifyData[$pm->sku] ?? null;

            $ebay = $ebayMetricData[$pm->sku] ?? null;

            $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL1 = $ebayCampaignReportsL1->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['price']  = $ebay->ebay_price ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaign_name'] = $matchedCampaignL7->campaign_name ?? ($matchedCampaignL1->campaign_name ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');

            $row['l7_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? '0');
            $row['l7_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL7->cost_per_click ?? '0');
            $row['l1_spend'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? '0');
            $row['l1_cpc'] = (float) str_replace(['USD ', ','], '', $matchedCampaignL1->cost_per_click ?? '0');

            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);

            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // Calculate SBID using under-utilized rules
            $price = floatval($row['price']);
            
            // Special rule: If UB7 = 0% and UB1 = 0%, use price-based SBID
            if ($ub7 == 0 && $ub1 == 0) {
                if ($price < 20) {
                    $row['sbid'] = 0.20;
                } elseif ($price >= 20 && $price < 50) {
                    $row['sbid'] = 0.75;
                } elseif ($price >= 50 && $price < 100) {
                    $row['sbid'] = 1.00;
                } elseif ($price >= 100 && $price < 200) {
                    $row['sbid'] = 1.50;
                } else {
                    $row['sbid'] = 2.00;
                }
            } else {
                // Use L1CPC if available (not 0), otherwise use L7CPC
                $cpcToUse = ($l1_cpc > 0) ? $l1_cpc : (($l7_cpc > 0) ? $l7_cpc : 0);
                
                if ($cpcToUse > 0) {
                    if ($cpcToUse < 0.10) {
                        $row['sbid'] = floor($cpcToUse * 2.00 * 100) / 100;
                    } elseif ($cpcToUse >= 0.10 && $cpcToUse <= 0.20) {
                        $row['sbid'] = floor($cpcToUse * 1.50 * 100) / 100;
                    } elseif ($cpcToUse >= 0.21 && $cpcToUse <= 0.30) {
                        $row['sbid'] = floor($cpcToUse * 1.25 * 100) / 100;
                    } else {
                        $row['sbid'] = floor($cpcToUse * 1.10 * 100) / 100;
                    }
                    
                    // Price cap: If price < $20, cap SBID at 0.20
                    if ($price < 20) {
                        $row['sbid'] = min($row['sbid'], 0.20);
                    }
                } else {
                    // If both L1CPC and L7CPC are 0, use default
                    $row['sbid'] = 0.75;
                }
            }

            $row['NR'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
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

        return $result;
    }

    private function getDilColor($l30, $inv)
    {
        if ($inv == 0) {
            return 'red';
        }

        $percent = ($l30 / $inv) * 100;

        if ($percent < 16.66) return 'red';
        if ($percent >= 16.66 && $percent < 25) return 'yellow';
        if ($percent >= 25 && $percent < 50) return 'green';
        return 'pink';
    }


}