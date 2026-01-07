<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use Illuminate\Console\Command;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

class AutoUpdateAmzUnderKwBids extends Command
{
    protected $signature = 'amazon:auto-update-under-kw-bids';
    protected $description = 'Automatically update Amazon campaign keyword bids';

    protected $profileId;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $this->info("Starting Amazon bids auto-update...");

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

            $updateKwBids = new AmazonSpBudgetController;

            $campaigns = $this->getAutomateAmzUtilizedBgtKw();

            if (empty($campaigns)) {
            $this->warn("No campaigns matched filter conditions.");
            return 0;
        }

            // Filter out campaigns with invalid data
            $validCampaigns = collect($campaigns)->filter(function ($campaign) {
                return !empty($campaign->campaign_id) && 
                       isset($campaign->sbid) && 
                       is_numeric($campaign->sbid) && 
                       $campaign->sbid > 0;
            })->values();

            if ($validCampaigns->isEmpty()) {
                $this->warn("No valid campaigns found (missing campaign_id or invalid bid).");
                return 0;
            }

            $this->info("Found " . $validCampaigns->count() . " valid campaigns to update.");
            $this->line("");

            // Log campaigns before update
            $this->info("Campaigns to be updated:");
            foreach ($validCampaigns as $campaign) {
                $campaignName = $campaign->campaignName ?? 'N/A';
                $newBid = $campaign->sbid ?? 0;
                $this->line("  Campaign: {$campaignName} | New Bid: {$newBid}");
            }
            $this->line("");

            $campaignIds = $validCampaigns->pluck('campaign_id')->toArray();
            $newBids = $validCampaigns->pluck('sbid')->toArray();

            // Validate arrays are aligned
            if (count($campaignIds) !== count($newBids)) {
                $this->error("✗ Array mismatch: campaign IDs and bids count don't match!");
                return 1;
            }

            try {
                $result = $updateKwBids->updateAutoCampaignKeywordsBid($campaignIds, $newBids);

                //Handle Response object (when no keywords found)
                if (is_object($result) && method_exists($result, 'getData')) {
                    $result = $result->getData(true);
                }

                // Check for errors
                if (is_array($result) && isset($result['status'])) {
                    if ($result['status'] == 200) {
                        $this->info("✓ Bid update completed successfully!");
                        $this->line("");
                        $this->info("Updated campaigns:");
                        foreach ($validCampaigns as $campaign) {
                            $campaignName = $campaign->campaignName ?? 'N/A';
                            $newBid = $campaign->sbid ?? 0;
                            $this->line("  Campaign: {$campaignName} | New Bid: {$newBid}");
                        }
                    } else {
                        $this->error("✗ Bid update failed!");
                        $this->error("Status: " . $result['status']);
                        if (isset($result['message'])) {
                            $this->error("Message: " . $result['message']);
                        }
                        if (isset($result['error'])) {
                            $this->error("Error: " . $result['error']);
                        }
                        return 1;
                    }
                } else {
                    // Handle unexpected response format
                    $this->warn("Unexpected response format from update method.");
                    if (is_array($result) || is_object($result)) {
                        $this->line("Response: " . json_encode($result));
                    } else {
                        $this->line("Response type: " . gettype($result));
                    }
                }

                $this->info('Campaigns to be updated: ' . count($campaigns));

            } catch (\Exception $e) {
                $this->error("✗ Exception occurred during bid update:");
                $this->error($e->getMessage());
                $this->error("Stack trace: " . $e->getTraceAsString());
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error in handle: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    public function getAutomateAmzUtilizedBgtKw()
    {
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
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

            $shopifyData = [];
            $amazonDatasheets = [];
            $nrValues = [];

            if (!empty($skus)) {
                $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
                $amazonDatasheets = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
                $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');
            }

        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);

            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku) {
                $campaignName = strtoupper(trim(rtrim($item->campaignName, '.')));
                $cleanSku = strtoupper(trim(rtrim($sku, '.')));
                return $campaignName === $cleanSku;
            });

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $row = [];
            $row['INV']    = $shopify->inv ?? 0;
            $row['campaign_id'] = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $row['campaignName'] = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
            $row['l7_spend'] = $matchedCampaignL7->spend ?? 0;
            $row['l7_cpc'] = $matchedCampaignL7->costPerClick ?? 0;
            $row['l1_spend'] = $matchedCampaignL1->spend ?? 0;
            $row['l1_cpc'] = $matchedCampaignL1->costPerClick ?? 0;

            // Get price from AmazonDatasheet
            $amazonSheet = $amazonDatasheets[strtoupper($pm->sku)] ?? null;
            $price = ($amazonSheet && isset($amazonSheet->price)) ? floatval($amazonSheet->price) : 0;

            // Calculate avg_cpc (lifetime average from daily records)
            $campaignId = $row['campaign_id'];
            $avgCpc = 0;
            try {
                $avgCpcRecord = DB::table('amazon_sp_campaign_reports')
                    ->select(DB::raw('AVG(costPerClick) as avg_cpc'))
                    ->where('campaign_id', $campaignId)
                    ->where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
                    ->where('costPerClick', '>', 0)
                    ->whereNotNull('campaign_id')
                    ->first();
                
                if ($avgCpcRecord && $avgCpcRecord->avg_cpc > 0) {
                    $avgCpc = floatval($avgCpcRecord->avg_cpc);
                }
            } catch (\Exception $e) {
                // Continue without avg_cpc if there's an error
            }

            $row['NRA'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRA'] = $raw['NRA'] ?? null;
                }
            }

            $l1_cpc = floatval($row['l1_cpc']);
            $l7_cpc = floatval($row['l7_cpc']);
            $budget = floatval($row['campaignBudgetAmount']);
            $l7_spend = floatval($row['l7_spend']);
            $l1_spend = floatval($row['l1_spend']);
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            // Under-utilized rule: INV > 0, NRA !== 'NRA', campaignName !== '', ub7 < 66 && ub1 < 66
            if ($row['INV'] > 0 && $row['NRA'] !== 'NRA' && $row['campaignName'] !== '' && ($ub7 < 66 && $ub1 < 66)) {
                // Calculate SBID based on blade file logic
                // Special case: If UB7 and UB1 = 0%, use price-based default
                if ($ub7 === 0 && $ub1 === 0) {
                    if ($price < 50) {
                        $row['sbid'] = 0.50;
                    } else if ($price >= 50 && $price < 100) {
                        $row['sbid'] = 1.00;
                    } else if ($price >= 100 && $price < 200) {
                        $row['sbid'] = 1.50;
                    } else {
                        $row['sbid'] = 2.00;
                    }
                } else {
                    // Under-utilized: Priority - L1 CPC → L7 CPC → AVG CPC → 1.00, then increase by 10%
                    if ($l1_cpc > 0) {
                        $row['sbid'] = floor($l1_cpc * 1.10 * 100) / 100;
                    } else if ($l7_cpc > 0) {
                        $row['sbid'] = floor($l7_cpc * 1.10 * 100) / 100;
                    } else if ($avgCpc > 0) {
                        $row['sbid'] = floor($avgCpc * 1.10 * 100) / 100;
                    } else {
                        $row['sbid'] = 1.00;
                    }
                }
                
                // Apply price-based caps
                if ($price < 10 && $row['sbid'] > 0.10) {
                    $row['sbid'] = 0.10;
                } else if ($price >= 10 && $price < 20 && $row['sbid'] > 0.20) {
                    $row['sbid'] = 0.20;
                }
                
                $result[] = (object) $row;
            }
            }

            DB::connection()->disconnect();
            return $result;
        
        } catch (\Exception $e) {
            $this->error("Error in getAutomateAmzUtilizedBgtKw: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return [];
        } finally {
            DB::connection()->disconnect();
        }
    }

}