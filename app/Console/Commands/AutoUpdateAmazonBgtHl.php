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

            // For PARENT rows (HL processes only parent SKUs): avg price = average of child SKUs' prices
            $childPricesByParent = [];
            foreach ($productMasters as $pm) {
                $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku ?? '');
                $norm = preg_replace('/\s+/', ' ', $norm);
                $skuUpper = strtoupper(trim($norm));
                if (stripos($skuUpper, 'PARENT') !== false) {
                    continue;
                }
                $p = $pm->parent ?? '';
                if ($p === '') {
                    continue;
                }
                $amazonSheetChild = $amazonDatasheetsBySku[$skuUpper] ?? null;
                $childPrice = ($amazonSheetChild && isset($amazonSheetChild->price) && (float)$amazonSheetChild->price > 0)
                    ? (float)$amazonSheetChild->price
                    : null;
                if ($childPrice === null) {
                    $values = $pm->Values;
                    if (is_string($values)) {
                        $values = json_decode($values, true) ?: [];
                    } elseif (is_object($values)) {
                        $values = (array) $values;
                    } elseif (!is_array($values)) {
                        $values = [];
                    }
                    $childPrice = isset($values['msrp']) && (float)$values['msrp'] > 0
                        ? (float)$values['msrp']
                        : (isset($values['map']) && (float)$values['map'] > 0 ? (float)$values['map'] : null);
                }
                if ($childPrice !== null && $childPrice > 0) {
                    $normParent = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $p ?? ''))));
                    $normParent = rtrim($normParent, '.');
                    if (!isset($childPricesByParent[$normParent])) {
                        $childPricesByParent[$normParent] = [];
                    }
                    $childPricesByParent[$normParent][] = $childPrice;
                }
            }
            $avgPriceByParent = [];
            foreach ($childPricesByParent as $p => $prices) {
                $avgPriceByParent[$p] = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
            }

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
                $price = ($amazonSheet && isset($amazonSheet->price)) ? (float)$amazonSheet->price : 0;
                // For PARENT rows (HL processes only parent SKUs): use avg price from children when direct price is 0
                if (($price === 0 || $price === null) && stripos($sku, 'PARENT') !== false) {
                    $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $sku))));
                    $normParentKey = rtrim($normSku, '.');
                    $price = $avgPriceByParent[$normSku] ?? $avgPriceByParent[$normParentKey] ?? $avgPriceByParent[$pm->parent ?? ''] ?? $avgPriceByParent[rtrim($pm->parent ?? '', '.')] ?? 0;
                }
                $row['price'] = $price;
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

                // ACOS-based SBGT rules
                if ($acos > 25) {
                    $sbgt = 1;
                } elseif ($acos >= 20) {
                    $sbgt = 2;
                } elseif ($acos >= 15) {
                    $sbgt = 4;
                } elseif ($acos >= 10) {
                    $sbgt = 6;
                } elseif ($acos >= 5) {
                    $sbgt = 8;
                } else {
                    $sbgt = 10; // Less than 5
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