<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\Ebay3UtilizedAdsController;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayThreeDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;

class Ebay3OverUtilzBidsAutoUpdate extends Command
{
    protected $signature = 'ebay3:auto-update-over-bids';
    protected $description = 'Automatically update eBay3 campaign keyword bids for over-utilized campaigns';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🚀 Starting eBay3 Over-Utilized Bids Auto-Update");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $updateOverUtilizedBids = new Ebay3UtilizedAdsController;

        $campaigns = $this->getEbay3OverUtilizCampaign();

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

        $this->info("🔄 Updating bids via eBay3 API...");
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

    public function getEbay3OverUtilizCampaign()
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

        // Calculate total ACOS from ALL RUNNING campaigns (L30 data)
        $allL30Campaigns = Ebay3PriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get();

        $totalSpendAll = 0;
        $totalSalesAll = 0;

        foreach ($allL30Campaigns as $campaign) {
            $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
            $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');
            $totalSpendAll += $adFees;
            $totalSalesAll += $sales;
        }

        $totalACOSAll = $totalSalesAll > 0 ? ($totalSpendAll / $totalSalesAll) * 100 : 0;

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
            $budget = floatval($row['campaignBudgetAmount']);
            
            // Calculate UB7 (7-day budget utilization)
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            
            // Calculate ACOS
            $rowAcos = $row['acos'];
            if ($rowAcos === 0) {
                $rowAcos = 100; // Treat 0 as 100 for comparison
            }

            // Calculate SBID: l1_cpc * 0.95 (as per view logic)
            $row['sbid'] = floor($l1_cpc * 0.95 * 100) / 100;

            // Apply filter conditions:
            // Condition 1: ACOS > TOTAL_ACOS AND UB7 > 33%
            // OR
            // Condition 2: ACOS <= TOTAL_ACOS AND UB7 > 90%
            $condition1 = ($rowAcos > $totalACOSAll && $ub7 > 33);
            $condition2 = ($rowAcos <= $totalACOSAll && $ub7 > 90);
            $matchesCondition = $condition1 || $condition2;

            // Other filters: NR !== 'NRA', price >= 30, INV > 0, DIL not pink
            if ($matchesCondition && $row['NR'] !== 'NRA') {
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
