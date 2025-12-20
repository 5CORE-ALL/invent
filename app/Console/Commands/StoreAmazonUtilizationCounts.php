<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonDataView;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSbCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreAmazonUtilizationCounts extends Command
{
    protected $signature = 'amazon:store-utilization-counts';
    protected $description = 'Store daily counts of over/under/correctly utilized Amazon KW, PT, and HL campaigns';

    public function handle()
    {
        $this->info('Starting to store Amazon utilization counts...');

        // Process KW, PT, and HL campaigns
        $this->processCampaignType('KW');
        $this->processCampaignType('PT');
        $this->processCampaignType('HL');

        return 0;
    }

    private function processCampaignType($campaignType)
    {
        $this->info("Processing {$campaignType} campaigns...");

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Get NRA values to filter out NRA campaigns (same as in getAmzUnderUtilizedBgtKw)
        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        
        // Get INV values from ShopifySku for KW and PT campaigns
        $shopifyData = [];
        if ($campaignType === 'KW' || $campaignType === 'PT') {
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        }

        // Handle HL campaigns differently (use AmazonSbCampaignReport)
        if ($campaignType === 'HL') {
            $amazonSbCampaignReportsL7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L7')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();

            $amazonSbCampaignReportsL1 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L1')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->where('campaignStatus', '!=', 'ARCHIVED')
                ->get();
        } else {
            // For KW and PT, use AmazonSpCampaignReport
            $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L7')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                })
                ->where('campaignStatus', '!=', 'ARCHIVED');

            $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->where('report_date_range', 'L1')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                })
                ->where('campaignStatus', '!=', 'ARCHIVED');

            // Filter by campaign type
            if ($campaignType === 'PT') {
                $amazonSpCampaignReportsL7->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                      ->orWhere('campaignName', 'LIKE', '% PT.');
                });
                $amazonSpCampaignReportsL1->where(function($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                      ->orWhere('campaignName', 'LIKE', '% PT.');
                });
            } else {
                $amazonSpCampaignReportsL7->where('campaignName', 'NOT LIKE', '%PT')
                                          ->where('campaignName', 'NOT LIKE', '%PT.');
                $amazonSpCampaignReportsL1->where('campaignName', 'NOT LIKE', '%PT')
                                          ->where('campaignName', 'NOT LIKE', '%PT.');
            }

            $amazonSpCampaignReportsL7 = $amazonSpCampaignReportsL7->get();
            $amazonSpCampaignReportsL1 = $amazonSpCampaignReportsL1->get();
        }

        // Store all processed campaigns with their data (similar to controller's campaignMap)
        $campaignMap = [];
        
        // For PT campaigns, we need to track unique SKUs (same as getAmzUnderUtilizedBgtPt)
        $processedSkus = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            
            // For PT campaigns, apply unique SKU filter (same as getAmzUnderUtilizedBgtPt line 637)
            if ($campaignType === 'PT' && in_array($sku, $processedSkus)) {
                continue;
            }

            // Check NRA filter (same as in getAmzUnderUtilizedBgtKw/getAmzUnderUtilizedBgtPt)
            $nra = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                }
            }
            
            // Filter by NRA for all campaign types (KW, PT, HL)
            if ($nra === 'NRA') {
                continue;
            }

            if ($campaignType === 'HL') {
                // HL campaigns matching logic (SKU or SKU + ' HEAD')
                $matchedCampaignL7 = $amazonSbCampaignReportsL7->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });

                $matchedCampaignL1 = $amazonSbCampaignReportsL1->first(function ($item) use ($sku) {
                    $cleanName = strtoupper(trim($item->campaignName));
                    $expected1 = $sku;
                    $expected2 = $sku . ' HEAD';
                    return ($cleanName === $expected1 || $cleanName === $expected2);
                });

                if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                    continue;
                }

                $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
                $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
                if (empty($campaignId) || empty($campaignName)) {
                    continue;
                }

                $budget = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
                $l7_spend = $matchedCampaignL7->cost ?? 0; // HL uses 'cost' field
                $l1_spend = $matchedCampaignL1->cost ?? 0; // HL uses 'cost' field
            } else {
                // KW and PT campaigns matching logic
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sku, $campaignType) {
                    $campaignName = strtoupper(trim($item->campaignName));
                    $cleanSku = strtoupper(trim($sku));
                    
                    if ($campaignType === 'PT') {
                        // Exact match like getAmzUnderUtilizedBgtPt (line 545-549)
                        return ($campaignName === $cleanSku . ' PT' || $campaignName === $cleanSku . ' PT.');
                    } else {
                        // KW: Exact match like getAmzUtilizedBgtKw (line 1151-1154)
                        $cleanName = strtoupper(trim(rtrim($campaignName, '.')));
                        $cleanSkuTrimmed = strtoupper(trim(rtrim($cleanSku, '.')));
                        return $cleanName === $cleanSkuTrimmed;
                    }
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sku, $campaignType) {
                    $campaignName = strtoupper(trim($item->campaignName));
                    $cleanSku = strtoupper(trim($sku));
                    
                    if ($campaignType === 'PT') {
                        // Exact match like getAmzUnderUtilizedBgtPt (line 545-549)
                        return ($campaignName === $cleanSku . ' PT' || $campaignName === $cleanSku . ' PT.');
                    } else {
                        // KW: Exact match like getAmzUtilizedBgtKw (line 1157-1160)
                        $cleanName = strtoupper(trim(rtrim($campaignName, '.')));
                        $cleanSkuTrimmed = strtoupper(trim(rtrim($cleanSku, '.')));
                        return $cleanName === $cleanSkuTrimmed;
                    }
                });

                if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                    continue;
                }

                // Get campaignId and campaignName for later use
                $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
                $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
                
                // Skip if no campaign ID or name
                if (empty($campaignId) || empty($campaignName)) {
                    continue;
                }
                
                // For KW: Don't filter by campaignName (getAmzUtilizedBgtKw doesn't filter by campaignName)
                // For PT: Filter by campaignName !== '' (getAmzUnderUtilizedBgtPt filters by campaignName !== '')
                if ($campaignType === 'PT' && $campaignName === '') {
                    continue;
                }

                $budget = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
                $l7_spend = $matchedCampaignL7->spend ?? 0;
                $l1_spend = $matchedCampaignL1->spend ?? 0;
            }

            // Get INV for this SKU (for KW and PT)
            $shopify = ($campaignType === 'KW' || $campaignType === 'PT') ? ($shopifyData[$pm->sku] ?? null) : null;
            $inv = ($campaignType === 'KW' || $campaignType === 'PT') ? ($shopify ? ($shopify->inv ?? 0) : 0) : 0;
            
            // Store campaign data in map (similar to controller) - only once per campaign_id
            if (!isset($campaignMap[$campaignId])) {
                $campaignMap[$campaignId] = [
                    'campaign_id' => $campaignId,
                    'campaignName' => $campaignName,
                    'budget' => $budget,
                    'l7_spend' => $l7_spend,
                    'l1_spend' => $l1_spend,
                    'inv' => $inv
                ];
            } else {
                // Update spend values if we have better data
                if ($l7_spend > 0) {
                    $campaignMap[$campaignId]['l7_spend'] = $l7_spend;
                }
                if ($l1_spend > 0) {
                    $campaignMap[$campaignId]['l1_spend'] = $l1_spend;
                }
                // Update INV if current SKU has INV > 0 (prefer INV > 0 over INV = 0)
                if (($campaignType === 'KW' || $campaignType === 'PT') && floatval($inv) > 0) {
                    $campaignMap[$campaignId]['inv'] = $inv;
                }
            }
            
            // For PT campaigns, mark SKU as processed (unique filter)
            if ($campaignType === 'PT') {
                $processedSkus[] = $sku;
            }
        }

        // Now count unique campaigns from campaignMap
        $overUtilizedCount7ub = 0;
        $underUtilizedCount7ub = 0;
        $correctlyUtilizedCount7ub = 0;
        
        $overUtilizedCount7ub1ub = 0;
        $underUtilizedCount7ub1ub = 0;
        $correctlyUtilizedCount7ub1ub = 0;
        
        foreach ($campaignMap as $campaignId => $campaignData) {
            // For KW and PT: Skip campaigns with INV = 0
            if (($campaignType === 'KW' || $campaignType === 'PT') && floatval($campaignData['inv']) <= 0) {
                continue;
            }
            
            $budget = $campaignData['budget'] ?? 0;
            $l7_spend = $campaignData['l7_spend'] ?? 0;
            $l1_spend = $campaignData['l1_spend'] ?? 0;
            
            // Calculate UB7 and UB1
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / ($budget * 1)) * 100 : 0;
            
            // Categorize based on 7UB only condition
            if ($ub7 > 90) {
                $overUtilizedCount7ub++;
            } elseif ($ub7 < 70) {
                $underUtilizedCount7ub++;
            } elseif ($ub7 >= 70 && $ub7 <= 90) {
                $correctlyUtilizedCount7ub++;
            }
            
            // Categorize based on 7UB + 1UB condition
            if ($ub7 > 90 && $ub1 > 90) {
                $overUtilizedCount7ub1ub++;
            } elseif ($ub7 < 70 && $ub1 < 70) {
                $underUtilizedCount7ub1ub++;
            } elseif ($ub7 >= 70 && $ub7 <= 90 && $ub1 >= 70 && $ub1 <= 90) {
                $correctlyUtilizedCount7ub1ub++;
            }
        }

        // Store in amazon_data_view table with date as SKU
        $today = now()->format('Y-m-d');
        $tomorrow = now()->copy()->addDay()->format('Y-m-d');
        
        // Data for today (with actual counts)
        $data = [
            // 7UB only condition
            'over_utilized_7ub' => $overUtilizedCount7ub,
            'under_utilized_7ub' => $underUtilizedCount7ub,
            'correctly_utilized_7ub' => $correctlyUtilizedCount7ub,
            // 7UB + 1UB condition
            'over_utilized_7ub_1ub' => $overUtilizedCount7ub1ub,
            'under_utilized_7ub_1ub' => $underUtilizedCount7ub1ub,
            'correctly_utilized_7ub_1ub' => $correctlyUtilizedCount7ub1ub,
            'date' => $today
        ];

        // Blank data for tomorrow (all counts as 0)
        $blankData = [
            // 7UB only condition
            'over_utilized_7ub' => 0,
            'under_utilized_7ub' => 0,
            'correctly_utilized_7ub' => 0,
            // 7UB + 1UB condition
            'over_utilized_7ub_1ub' => 0,
            'under_utilized_7ub_1ub' => 0,
            'correctly_utilized_7ub_1ub' => 0,
            'date' => $tomorrow
        ];

        // Use date as SKU identifier for this data with campaign type
        $skuKeyToday = 'AMAZON_UTILIZATION_' . $campaignType . '_' . $today;
        $skuKeyTomorrow = 'AMAZON_UTILIZATION_' . $campaignType . '_' . $tomorrow;

        // Insert/Update today's data
        $existingToday = AmazonDataView::where('sku', $skuKeyToday)->first();

        if ($existingToday) {
            $existingToday->update(['value' => $data]);
            $this->info("Updated {$campaignType} utilization counts for {$today}");
        } else {
            AmazonDataView::create([
                'sku' => $skuKeyToday,
                'value' => $data
            ]);
            $this->info("Created {$campaignType} utilization counts for {$today}");
        }

        // Insert/Update tomorrow's blank data (only if it doesn't exist)
        $existingTomorrow = AmazonDataView::where('sku', $skuKeyTomorrow)->first();

        if (!$existingTomorrow) {
            AmazonDataView::create([
                'sku' => $skuKeyTomorrow,
                'value' => $blankData
            ]);
            $this->info("Created blank {$campaignType} utilization counts for {$tomorrow}");
        } else {
            $this->info("Tomorrow's data already exists for {$tomorrow}, skipping blank data creation");
        }

        $this->info("{$campaignType} - 7UB Condition:");
        $this->info("  Over-utilized: {$overUtilizedCount7ub}");
        $this->info("  Under-utilized: {$underUtilizedCount7ub}");
        $this->info("  Correctly-utilized: {$correctlyUtilizedCount7ub}");
        $this->info("{$campaignType} - 7UB + 1UB Condition:");
        $this->info("  Over-utilized: {$overUtilizedCount7ub1ub}");
        $this->info("  Under-utilized: {$underUtilizedCount7ub1ub}");
        $this->info("  Correctly-utilized: {$correctlyUtilizedCount7ub1ub}");
    }
}
