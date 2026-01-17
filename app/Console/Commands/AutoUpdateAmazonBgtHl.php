<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSbCampaignReport;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AutoUpdateAmazonBgtHl extends Command
{
    protected $signature = 'amazon:auto-update-amz-bgt-hl';
    protected $description = 'Automatically update Amazon campaign bgt price';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $this->info("Starting Amazon bgts auto-update...");

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

            $updateKwBgts = new AmazonACOSController;

            $campaigns = $this->amazonAcosHlControlData();

            // Close connection after data fetching
            DB::connection()->disconnect();

            if (empty($campaigns)) {
                $this->warn("No campaigns matched filter conditions.");
                return 0;
            }

            // Filter out campaigns with empty/null campaign_id or invalid sbgt
            $validCampaigns = collect($campaigns)->filter(function ($campaign) {
                return !empty($campaign->campaign_id) && isset($campaign->sbgt) && $campaign->sbgt > 0;
            })->values();

            if ($validCampaigns->isEmpty()) {
                $this->warn("No valid campaigns found (all have empty campaign_id or invalid budget).");
                return 0;
            }

            $campaignIds = $validCampaigns->pluck('campaign_id')->toArray();
            $newBgts = $validCampaigns->pluck('sbgt')->toArray();

            // Ensure both arrays have the same length
            if (count($campaignIds) !== count($newBgts)) {
                $this->error("Error: Campaign IDs and budgets arrays have different lengths!");
                return 1;
            }

            try {
                $result = $updateKwBgts->updateAutoAmazonSbCampaignBgt($campaignIds, $newBgts);
                
                // Show detailed campaign information for verification
                $this->info("\n========================================");
                $this->info("CAMPAIGN BUDGET UPDATE SUMMARY (HL)");
                $this->info("========================================\n");
                
                foreach ($validCampaigns as $campaign) {
                    $this->info("Campaign: " . ($campaign->campaign_name ?? $campaign->campaignName ?? 'N/A'));
                    $this->info("  Price: $" . number_format($campaign->price ?? 0, 2));
                    $this->info("  ACOS: " . number_format($campaign->acos_L30 ?? 0, 2) . "%");
                    $this->info("  New Budget: $" . ($campaign->sbgt ?? 0));
                    $this->info("  Campaign ID: " . ($campaign->campaign_id ?? 'N/A'));
                    $this->info("---");
                }
                
                $this->info("\nTotal Campaigns: " . count($campaignIds));
                $this->info("========================================\n");
                
                if (isset($result['status']) && $result['status'] !== 200) {
                    $this->error("Budget update failed: " . ($result['message'] ?? 'Unknown error'));
                    return 1;
                }
                
                $this->info("Successfully prepared " . count($campaignIds) . " campaign budgets for update.");
                
            } catch (\Exception $e) {
                $this->error("Error updating campaign budgets: " . $e->getMessage());
                Log::error("Error updating Amazon SB campaign budgets: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                return 1;
            }

        } finally {
            // Ensure connection is closed
            DB::connection()->disconnect();
        }

        return 0;
    }

    public function amazonAcosHlControlData()
    {
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            // Return empty array if no SKUs found
            if (empty($skus)) {
                return [];
            }

            $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');
            
            $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku ?? '');
            });
            
            $amazonSpCampaignReportsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L30')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->get();

            $result = [];

            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku ?? '');

                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;

                $matchedCampaignL30 = $amazonSpCampaignReportsL30->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName ?? ''));
                    $expected1 = $sku;                
                    $expected2 = $sku . ' HEAD';      

                    return ($cleanName === $expected1 || $cleanName === $expected2)
                        && strtoupper($item->campaignStatus ?? '') === 'ENABLED';
                });

                if (!$matchedCampaignL30) {
                    continue;
                }

                // Skip if campaign_id is empty
                if (empty($matchedCampaignL30->campaign_id)) {
                    continue;
                }

                $row = [];
                $row['price']  = $amazonSheet->price ?? 0;
                $row['campaign_id'] = $matchedCampaignL30->campaign_id ?? '';
                $row['campaign_name'] = $matchedCampaignL30->campaignName ?? '';

                $sales = $matchedCampaignL30->sales ?? 0;
                $cost = $matchedCampaignL30->cost ?? 0;
                if ($cost > 0 && $sales > 0) {
                    $row['acos_L30'] = round(($cost / $sales) * 100, 2);
                } elseif ($cost > 0 && $sales == 0) {
                    $row['acos_L30'] = 100;
                } else {
                    $row['acos_L30'] = 0;
                }

                $row['spend_l30']       = $matchedCampaignL30->cost ?? 0;
                $row['ad_sales_l30']    = $matchedCampaignL30->sales ?? 0;

                $acos = (float) ($row['acos_L30'] ?? 0);

                $tpft = 0;
                $nra = '';
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) $raw = json_decode($raw, true);
                    if (is_array($raw)) {
                        $tpft = isset($raw['TPFT']) ? (int) floor($raw['TPFT']) : 0;
                        $nra = $raw['NRA'] ?? '';
                    }
                }
                $row['TPFT'] = $tpft;

                // Skip if NRA === 'NRA' (matching frontend filter)
                if ($nra === 'NRA') {
                    continue;
                }

                $acos = (float) ($row['acos_L30'] ?? 0);
                $spend = (float) ($row['spend_l30'] ?? 0);
                $sales = (float) ($row['ad_sales_l30'] ?? 0);

                // New sbgt rule: Budget = 10% of price (rounded up)
                // BUT if ACOS > 20%, then budget = $1
                $price = (float) ($row['price'] ?? 0);
                if ($acos > 20) {
                    $sbgt = 1;
                } else {
                    $sbgt = ceil($price * 0.10);
                }

                $row['sbgt'] = $sbgt;

                $result[] = (object) $row;
            }

        return $result;
        } catch (\Exception $e) {
            Log::error("Error in amazonAcosHlControlData: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
}