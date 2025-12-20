<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\EbayOverUtilizedBgtController;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;

class EbayPriceLessBidsAutoUpdate extends Command
{
    protected $signature = 'ebay:auto-update-price-less-bids';
    protected $description = 'Automatically update Ebay campaign keyword bids for price less than 20';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🚀 Starting eBay Price Less Bids Auto-Update");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $updateOverUtilizedBids = new EbayOverUtilizedBgtController;

        $campaigns = $this->getEbayPriceLessBidsCampaign();

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

        $campaignIds = collect($validCampaigns)->pluck('campaign_id')->toArray();
        $newBids = collect($validCampaigns)->pluck('sbid')->toArray();
        $campaignNames = collect($validCampaigns)->pluck('campaignName', 'campaign_id')->toArray();
        
        // Create mapping of campaign_id to bid for easy lookup
        $campaignBidMap = [];
        foreach ($validCampaigns as $campaign) {
            $campaignBidMap[$campaign->campaign_id] = $campaign->sbid ?? 0;
        }

        $this->info("🔄 Processing campaigns in batches of 10...");
        
        // Process in smaller batches to avoid timeout
        $chunks = array_chunk($campaignIds, 10);
        $bidChunks = array_chunk($newBids, 10);
        
        $totalSuccess = 0;
        $totalFailed = 0;
        $allCampaignResults = [];
        
        foreach ($chunks as $index => $chunk) {
            $this->info("Processing batch " . ($index + 1) . " of " . count($chunks) . "...");
            
            // Get campaign names for this batch
            $batchCampaigns = array_slice($validCampaigns, $index * 10, 10);
            $batchCampaignNames = collect($batchCampaigns)->pluck('campaignName')->toArray();
            
            $this->info("Campaigns: " . implode(', ', array_slice($batchCampaignNames, 0, 3)) . (count($batchCampaignNames) > 3 ? '...' : ''));
            
            try {
                $result = $updateOverUtilizedBids->updateAutoKeywordsBidDynamic($chunk, $bidChunks[$index]);
                $resultData = $result->getData(true);
                
                // Store results for detailed reporting
                foreach ($resultData['data'] ?? [] as $item) {
                    $allCampaignResults[] = $item;
                }
                
                $batchSuccess = count(array_filter($resultData['data'] ?? [], function($item) {
                    $status = strtolower($item['status'] ?? '');
                    return $status !== 'error' && $status !== '';
                }));
                
                $batchFailed = count($resultData['data'] ?? []) - $batchSuccess;
                
                $totalSuccess += $batchSuccess;
                $totalFailed += $batchFailed;
                
                $this->info("Batch " . ($index + 1) . " - Status: " . ($resultData['status'] ?? 'unknown') . " | Success: {$batchSuccess} | Failed: {$batchFailed}");
                
            } catch (\Exception $e) {
                $this->error("Batch " . ($index + 1) . " failed: " . $e->getMessage());
                $totalFailed += count($chunk);
            }
            
            // Small delay between batches to avoid rate limiting
            if ($index < count($chunks) - 1) {
                sleep(5);
            }
        }

        $this->info("");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Detailed Results by Campaign");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Group results by campaign_id
        $campaignResults = [];
        foreach ($allCampaignResults as $item) {
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

    public function getEbayPriceLessBidsCampaign(){

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');

        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->where('campaignStatus', 'RUNNING')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->where('campaignStatus', 'RUNNING')
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
            $row['sku']    = $pm->sku; // Add SKU to row
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['price']  = $ebay->ebay_price ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaign_name ?? ($matchedCampaignL1->campaign_name ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaign_budget_amount ?? ($matchedCampaignL1->campaign_budget_amount ?? 0);
            $row['l7_spend'] = $matchedCampaignL7->ad_fees ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->ad_fees ?? 0;
            $row['l7_cpc'] = 0;
            $row['l1_cpc'] = 0;
            
            // Calculate L7 CPC
            $l7_clicks = $matchedCampaignL7->clicks ?? 0;
            if ($l7_clicks > 0) {
                $row['l7_cpc'] = floatval($row['l7_spend']) / $l7_clicks;
            }
            
            // Calculate L1 CPC
            $l1_clicks = $matchedCampaignL1->clicks ?? 0;
            if ($l1_clicks > 0) {
                $row['l1_cpc'] = floatval($row['l1_spend']) / $l1_clicks;
            }
            
            $row['sbid'] = 0;

            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);
            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            
            // Calculate SBID based on budget utilization
            if($ub7 < 70){
                // Under-utilized: increase bid by 5%
                $row['sbid'] = round($l7_cpc * 1.05, 2);
            }else if($ub7 > 90){
                // Over-utilized: decrease bid by 10%
                $row['sbid'] = round($l1_cpc * 0.90, 2);
            }else{
                // Correctly utilized (70-90): keep current bid
                $row['sbid'] = round($l7_cpc, 2);
            }
            
            // Apply price-based SBID caps - this runs AFTER the ub7 calculation
            if($row['price'] < 30 && $row['campaignName'] !== ''){
                // Force exact bid values based on price ranges (consistent with EbayKwAdsController)
                if($row['price'] <= 10){
                    $row['sbid'] = max(0.10, min($row['sbid'], 0.10));  // Exactly 0.10
                }
                elseif($row['price'] > 10 && $row['price'] <= 20){
                    $row['sbid'] = max(0.20, min($row['sbid'], 0.20));  // Exactly 0.20
                }
                elseif($row['price'] > 20 && $row['price'] <= 30){
                    $row['sbid'] = max(0.40, min($row['sbid'], 0.40));  // Exactly 0.40
                }
                
                // Only show data under price 30, exclude PARENT SKUs, and only show items with campaigns
                // Also exclude INV <= 0 (zero and negative inventory)
                if($row['price'] < 30 && stripos($row['sku'], 'PARENT') === false && $row['campaignName'] !== '' && $row['INV'] > 0){
                    $result[] = (object) $row;
                }
            }

        }

        return $result;
    }


}