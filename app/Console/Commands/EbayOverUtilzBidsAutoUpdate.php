<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\EbayOverUtilizedBgtController;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;

class EbayOverUtilzBidsAutoUpdate extends Command
{
    protected $signature = 'ebay:auto-update-over-bids';
    protected $description = 'Automatically update Ebay campaign keyword bids';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🚀 Starting eBay Over-Utilized Bids Auto-Update");
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

        // Log all campaigns before update
        $this->info("📋 Campaigns to be updated:");
        foreach ($validCampaigns as $index => $campaign) {
            $campaignName = $campaign->campaign_name ?? 'Unknown';
            $campaignId = $campaign->campaign_id ?? 'N/A';
            $newBid = $campaign->sbid ?? 0;
            $oldCpc = $campaign->l7_cpc ?? 0;
            
            $this->line("   " . ($index + 1) . ". Campaign: {$campaignName} | ID: {$campaignId} | Old CPC: \${$oldCpc} | New Bid: \${$newBid}");
        }
        
        $this->info("");

        $campaignIds = collect($validCampaigns)->pluck('campaign_id')->toArray();
        $newBids = collect($validCampaigns)->pluck('sbid')->toArray();
        $campaignNames = collect($validCampaigns)->pluck('campaign_name', 'campaign_id')->toArray();
        
        // Create mapping of campaign_id to bid for easy lookup
        $campaignBidMap = [];
        foreach ($validCampaigns as $campaign) {
            $campaignBidMap[$campaign->campaign_id] = $campaign->sbid ?? 0;
        }

        $this->info("🔄 Updating bids via eBay API...");
        $result = $updateOverUtilizedBids->updateAutoKeywordsBidDynamic($campaignIds, $newBids);
        
        // Parse the result
        $resultData = $result->getData(true);
        $status = $resultData['status'] ?? 'unknown';
        $message = $resultData['message'] ?? 'No message';
        $data = $resultData['data'] ?? [];

        $this->info("");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Update Results");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Status: " . ($status == 200 ? "✅ Success" : ($status == 207 ? "⚠️  Partial Success" : "❌ Failed")));
        $this->info("Message: {$message}");
        $this->info("");

        // Group results by campaign_id
        $campaignResults = [];
        foreach ($data as $item) {
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
        $totalSuccess = 0;
        $totalFailed = 0;
        
        foreach ($campaignResults as $campId => $result) {
            $campaignName = $result['campaign_name'];
            $success = $result['success'];
            $failed = $result['failed'];
            $newBid = $campaignBidMap[$campId] ?? 'N/A';
            
            $totalSuccess += $success;
            $totalFailed += $failed;
            
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
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        // Calculate total ACOS from ALL RUNNING campaigns (L30 data)
        $allL30Campaigns = EbayPriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $totalSpendAll = 0;
        $totalSalesAll = 0;

        foreach ($allL30Campaigns as $campaign) {
            $adFees = (float) str_replace('USD ', '', $campaign->cpc_ad_fees_payout_currency ?? 0);
            $sales = (float) str_replace('USD ', '', $campaign->cpc_sale_amount_payout_currency ?? 0);
            $totalSpendAll += $adFees;
            $totalSalesAll += $sales;
        }

        $totalACOSAll = $totalSalesAll > 0 ? ($totalSpendAll / $totalSalesAll) * 100 : 0;

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

            $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaign_name, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1 && !$matchedCampaignL30) {
                continue;
            }

            // Use L7 if available, otherwise fall back to L30
            $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30;
            
            // Only process RUNNING campaigns
            if (!$campaignForDisplay || $campaignForDisplay->campaignStatus !== 'RUNNING') {
                continue;
            }

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['price']  = $ebay->ebay_price ?? 0;
            $row['campaign_id'] = $campaignForDisplay->campaign_id ?? '';
            $row['campaign_name'] = $campaignForDisplay->campaign_name ?? '';
            $row['campaignBudgetAmount'] = $campaignForDisplay->campaignBudgetAmount ?? '';
            $row['sku'] = $pm->sku;

            $row['l7_spend'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0);
            $row['l7_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0);
            $row['l1_spend'] = (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0);
            $row['l1_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0);

            // Calculate ACOS from L30 data (use L30 if available, otherwise use L7)
            $matchedCampaignL30 = $matchedCampaignL30 ?? $matchedCampaignL7;
            $adFeesL30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $salesL30 = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0);
            $acos = $salesL30 > 0 ? ($adFeesL30 / $salesL30) * 100 : 0;
            
            // If acos is 0 (no sales or no ad fees), set it to 100 for comparison
            if ($acos === 0) {
                $rowAcos = 100;
            } else {
                $rowAcos = $acos;
            }

            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);
            
            // Calculate SBID - handle cases where l1_cpc is 0 or missing
            if($l1_cpc > 0){
                $row['sbid'] = floor($l1_cpc * 0.90 * 100) / 100;
            }elseif($l7_cpc > 0){
                // If l1_cpc is 0, use l7_cpc as fallback
                $row['sbid'] = floor($l7_cpc * 0.90 * 100) / 100;
            }else{
                // If both are 0, use default minimum bid
                $row['sbid'] = 0.50;
            }

            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;

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

            // Apply filter conditions:
            // Condition 1: ACOS > TOTAL_ACOS AND UB7 > 33%
            // OR
            // Condition 2: ACOS <= TOTAL_ACOS AND UB7 > 90%
            $condition1 = ($rowAcos > $totalACOSAll && $ub7 > 33);
            $condition2 = ($rowAcos <= $totalACOSAll && $ub7 > 90);
            $matchesCondition = $condition1 || $condition2;

            // Other filters: NR !== 'NRA', price >= 30, INV > 0, DIL not pink
            if ($matchesCondition && $row['NR'] !== 'NRA' && $row['INV'] > 0) {
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