<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\Ebay3UtilizedAdsController;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayThreeDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;

class Ebay3UnderUtilzBidsAutoUpdate extends Command
{
    protected $signature = 'ebay3:auto-update-under-bids';
    protected $description = 'Automatically update eBay3 campaign keyword bids for under-utilized campaigns';

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
        $this->info("🚀 Starting eBay3 Under-Utilized Bids Auto-Update");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $updateUnderUtilizedBids = new Ebay3UtilizedAdsController;

        $campaigns = $this->getEbay3UnderUtilizCampaign();

        if (empty($campaigns)) {
            $this->warn("⚠️  No campaigns matched filter conditions.");
            return 0;
        }

        // Filter out campaigns with empty campaign_id
        $validCampaigns = array_filter($campaigns, function($campaign) {
            return !empty($campaign->campaign_id);
        });
        
        if (empty($validCampaigns)) {
            $this->warn("⚠️  No valid campaigns found (all have empty campaign_id).");
            return 0;
        }
        
        $this->info("📊 Found " . count($validCampaigns) . " campaigns to update");
        $this->info("");

        // Log all campaigns before update
        $this->info("📋 Campaigns to be updated:");
        foreach ($validCampaigns as $index => $campaign) {
            $campaignName = $campaign->campaignName ?? 'Unknown';
            $campaignId = $campaign->campaign_id ?? 'N/A';
            $newBid = $campaign->sbid ?? 0;
            $oldCpc = $campaign->l7_cpc ?? 0;
            
            $this->line("   " . ($index + 1) . ". Campaign: {$campaignName} | ID: {$campaignId} | Old CPC: \${$oldCpc} | New Bid: \${$newBid}");
        }
        
        $this->info("");

        $campaignNames = collect($validCampaigns)->pluck('campaignName', 'campaign_id')->toArray();
        
        // Create mapping of campaign_id to bid for easy lookup
        $campaignBidMap = [];
        foreach ($validCampaigns as $campaign) {
            $campaignBidMap[$campaign->campaign_id] = $campaign->sbid ?? 0;
        }

        // Process campaigns in batches to avoid timeout
        $batchSize = 5; // Process 5 campaigns at a time
        $campaignBatches = array_chunk($validCampaigns, $batchSize);
        $totalBatches = count($campaignBatches);
        
        $this->info("🔄 Updating bids via eBay3 API (processing in {$totalBatches} batches of {$batchSize} campaigns each)...");
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
                $result = $updateUnderUtilizedBids->updateAutoKeywordsBidDynamic($campaignIds, $newBids);
                
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

    public function getEbay3UnderUtilizCampaign()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayMetricData = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy('sku');

        $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
            ->orderBy('report_range', 'asc')
            ->get();

        $result = [];
        $campaignMap = []; // Group by campaign_id to avoid duplicates

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

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

            // Skip if NR is NRA
            if ($nrValue == 'NRA') {
                continue;
            }

            $matchedReports = $reports->filter(function ($item) use ($sku) {
                $campaignSku = strtoupper(trim($item->campaign_name ?? ''));
                return $campaignSku === $sku;
            });

            if ($matchedReports->isEmpty()) {
                continue;
            }

            // Group reports by campaign_id to combine L7, L1, L30 data
            foreach ($matchedReports as $campaign) {
                $campaignId = $campaign->campaign_id ?? '';
                
                if (empty($campaignId)) {
                    continue;
                }

                // Create or get existing row for this campaign
                if (!isset($campaignMap[$campaignId])) {
                    $campaignMap[$campaignId] = [
                        'parent' => $parent,
                        'sku' => $pm->sku,
                        'campaign_id' => $campaignId,
                        'campaignName' => $campaign->campaign_name ?? '',
                        'campaignBudgetAmount' => $campaign->campaignBudgetAmount ?? 0,
                        'campaignStatus' => $campaign->campaignStatus ?? '',
                        'INV' => ($shopify && isset($shopify->inv)) ? (int)$shopify->inv : 0,
                        'L30' => ($shopify && isset($shopify->quantity)) ? (int)$shopify->quantity : 0,
                        'l7_spend' => 0,
                        'l7_cpc' => 0,
                        'l1_spend' => 0,
                        'l1_cpc' => 0,
                        'acos' => 0,
                        'adFees' => 0,
                        'sales' => 0,
                        'NR' => $nrValue,
                        'price' => ($ebayMetricData[$pm->sku] ?? null) ? ($ebayMetricData[$pm->sku]->ebay_price ?? 0) : 0,
                    ];
                }

                $reportRange = $campaign->report_range ?? '';
                $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
                $cpc = (float) str_replace(['USD ', ','], '', $campaign->cost_per_click ?? '0');

                // Set L7 data
                if ($reportRange == 'L7') {
                    $campaignMap[$campaignId]['l7_spend'] = $adFees;
                    $campaignMap[$campaignId]['l7_cpc'] = $cpc;
                }

                // Set L1 data
                if ($reportRange == 'L1') {
                    $campaignMap[$campaignId]['l1_spend'] = $adFees;
                    $campaignMap[$campaignId]['l1_cpc'] = $cpc;
                }

                // Calculate ACOS from L30 data (or use the latest available)
                if ($reportRange == 'L30') {
                    $campaignMap[$campaignId]['adFees'] = $adFees;
                    $campaignMap[$campaignId]['sales'] = $sales;
                    
                    if ($sales > 0) {
                        $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                    } else if ($adFees > 0 && $sales == 0) {
                        $campaignMap[$campaignId]['acos'] = 100;
                    }
                }
            }
        }

        // Convert map to array and apply filters
        foreach ($campaignMap as $campaignId => $row) {
            // Only process RUNNING campaigns
            if ($row['campaignStatus'] !== 'RUNNING') {
                continue;
            }

            $l7_spend = floatval($row['l7_spend']);
            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);
            $budget = floatval($row['campaignBudgetAmount']);
            $l1_spend = floatval($row['l1_spend']);
            
            // Calculate UB7 (7-day budget utilization)
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // Calculate SBID based on UB7 ranges:
            // - If UB7 < 10%: SBID = 0.50
            // - If UB7 is between 10%-50%: SBID = L7_CPC * 1.2
            // - Else (UB7 > 50%): SBID = L7_CPC * 1.10
            if($ub7 < 10){
                $row['sbid'] = 0.50;
            }elseif($ub7 >= 10 && $ub7 <= 50){
                $row['sbid'] = floor($l7_cpc * 1.20 * 100) / 100;
            }else{
                $row['sbid'] = floor($l7_cpc * 1.10 * 100) / 100;
            }

            // Apply filter conditions: UB7 < 70% (under-utilized)
            // Other filters: NR !== 'NRA', price >= 30, INV > 0, DIL not pink
            if (($ub7 < 70 && $ub1 < 70) && $row['NR'] !== 'NRA') {
                $dilColor = $this->getDilColor($row['L30'], $row['INV']);
                if ($dilColor !== 'pink') {
                    $result[] = (object) $row;
                }
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
