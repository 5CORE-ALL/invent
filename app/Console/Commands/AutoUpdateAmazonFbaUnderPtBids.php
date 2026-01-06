<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaTable;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;

class AutoUpdateAmazonFbaUnderPtBids extends Command
{
    protected $signature = 'amazon-fba:auto-update-under-pt-bids';
    protected $description = 'Automatically update Amazon FBA campaign keyword bids';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $this->info("Starting Amazon bids auto-update...");

            // Check database connection
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $updateKwBids = new AmazonSpBudgetController;

            $campaigns = $this->getAutomateAmzFbaUnderUtilizedBgtKw();

            // Close connection after data fetching
            DB::disconnect();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                return 0;
            }

            // Filter out campaigns with empty/null campaign_id
            $validCampaigns = collect($campaigns)->filter(function ($campaign) {
                return !empty($campaign->campaign_id) && isset($campaign->sbid) && $campaign->sbid > 0;
            })->values();

            if ($validCampaigns->isEmpty()) {
                $this->warn("No valid campaigns found (all have empty campaign_id or invalid bid).");
                return 0;
            }

            $campaignIds = $validCampaigns->pluck('campaign_id')->toArray();
            $newBids = $validCampaigns->pluck('sbid')->toArray();

            // Ensure both arrays have the same length
            if (count($campaignIds) !== count($newBids)) {
                $this->error("Error: Campaign IDs and bids arrays have different lengths!");
                return 1;
            }

            try {
                $result = $updateKwBids->updateAutoCampaignTargetsBid($campaignIds, $newBids);
                $this->info("Update Result: " . json_encode($result));
            } catch (\Exception $e) {
                $this->error("Error updating campaign bids: " . $e->getMessage());
                return 1;
            }

        } finally {
            // Ensure connection is closed
            DB::disconnect();
        }

        return 0;
    }

    public function getAutomateAmzFbaUnderUtilizedBgtKw()
    {
        try {
            $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
                ->orderBy('seller_sku', 'asc')
                ->get();

            $sellerSkus = $fbaData->pluck('seller_sku')->filter()->unique()->values()->all();
            $baseSkus = $fbaData->pluck('base_sku')->filter()->unique()->values()->all();

            // Return empty array if no SKUs found
            if (empty($sellerSkus) || empty($baseSkus)) {
                return [];
            }

            $shopifyData = ShopifySku::whereIn('sku', $baseSkus)->get()->keyBy('sku');

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            })
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                }
            })
            ->where(function ($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            })
            ->get();


        $result = [];

        foreach ($fbaData as $fba) {
            $sellerSku = strtoupper($fba->seller_sku);
            $baseSku = $fba->base_sku;

            $shopify = $shopifyData[$baseSku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sellerSku) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                return (
                    str_contains($cleanName, $sellerSku . ' PT') || str_contains($cleanName, $sellerSku . ' PT.')
                ); 
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sellerSku) {
                $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));

                return (
                    str_contains($cleanName, $sellerSku . ' PT') || str_contains($cleanName, $sellerSku . ' PT.')
                ); 
            });

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');

            // Skip if campaign_id is empty
            if (empty($row['campaign_id'])) {
                continue;
            }

            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? '');
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;

            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);
            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // New SBID rule
            if($row['campaignName'] != '' && ($ub7 < 70 && $ub1 < 70)) {
                if ($ub7 < 10 || $l7_cpc == 0) {
                    $row['sbid'] = 0.50;
                } else if ($l7_cpc > 0 && $l7_cpc < 0.30) {
                    $row['sbid'] = round($l7_cpc + 0.20, 2);
                } else {
                    $row['sbid'] = floor($l7_cpc * 1.10 * 100) / 100;
                }
                $result[] = (object) $row;
            }
        }
        return $result;
        } catch (\Exception $e) {
            $this->info("Error in getAutomateAmzFbaUnderUtilizedBgtKw: " . $e->getMessage());
            return [];
        }
    }
}