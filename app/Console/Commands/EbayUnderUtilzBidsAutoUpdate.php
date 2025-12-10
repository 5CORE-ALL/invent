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
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🚀 Starting eBay Under-Utilized Bids Auto-Update");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $updateOverUtilizedBids = new EbayOverUtilizedBgtController;

        $campaigns = $this->getEbayOverUtilizCampaign();

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

            $row['l7_spend'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0);
            $row['l7_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0);
            $row['l1_spend'] = (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0);
            $row['l1_cpc'] = (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0);

            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);

            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;

            if($ub7 < 10){
                $row['sbid'] = 0.50;
            }else{
                $row['sbid'] = floor($l7_cpc * 1.10 * 100) / 100;
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

            if ($row['NR'] !== 'NRA' && $ub7 < 70 && $row['price'] >= 30 && $row['INV'] > 0) {
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