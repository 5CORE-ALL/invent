<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonDataView;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonSbCampaignReport;
use App\Models\ProductMaster;
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

        $overUtilizedCount = 0;
        $underUtilizedCount = 0;
        $correctlyUtilizedCount = 0;
        
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
            
            // For KW over-utilized: Don't filter by NRA (getAmzUtilizedBgtKw doesn't filter by NRA)
            // For PT under-utilized: Filter by NRA (getAmzUnderUtilizedBgtPt filters by NRA !== 'NRA')
            // For HL: Filter by NRA (similar to PT)
            if ($campaignType !== 'KW' && $nra === 'NRA') {
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

                $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
                if ($campaignName === '') {
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

                // Get campaignName for later use
                $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
                
                // For KW: Don't filter by campaignName (getAmzUtilizedBgtKw doesn't filter by campaignName)
                // For PT: Filter by campaignName !== '' (getAmzUnderUtilizedBgtPt filters by campaignName !== '')
                if ($campaignType === 'PT' && $campaignName === '') {
                    continue;
                }

                $budget = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
                $l7_spend = $matchedCampaignL7->spend ?? 0;
                $l1_spend = $matchedCampaignL1->spend ?? 0;
            }

            // Calculate UB7: (L7 Spend / (Budget * 7)) * 100
            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / ($budget * 1)) * 100 : 0;
            
            // For PT campaigns, mark SKU as processed (unique filter)
            if ($campaignType === 'PT') {
                $processedSkus[] = $sku;
            }

            // For KW over-utilized: Only filter by ub7 > 90 && ub1 > 90 (no NRA or campaignName filter)
            // For KW under-utilized: Filter by NRA !== 'NRA' && campaignName !== '' && ub7 < 70 (same as getAmzUnderUtilizedBgtKw line 467)
            // For PT under-utilized: Filter by NRA !== 'NRA' && campaignName !== '' && ub7 < 70
            // For HL: Similar to PT
            if ($campaignType === 'KW') {
                // KW over-utilized: Only check utilization (getAmzUtilizedBgtKw doesn't filter by NRA or campaignName)
                if ($ub7 > 90 && $ub1 > 90) {
                    $overUtilizedCount++;
                } elseif ($ub7 < 70) {
                    // KW under-utilized: Apply NRA and campaignName filters (same as getAmzUnderUtilizedBgtKw line 467)
                    if ($nra !== 'NRA' && $campaignName !== '') {
                        $underUtilizedCount++;
                    }
                } elseif ($ub7 >= 70 && $ub7 <= 90) {
                    // KW correctly-utilized: Apply NRA and campaignName filters (same as getAmzUnderUtilizedBgtKw)
                    if ($nra !== 'NRA' && $campaignName !== '') {
                        $correctlyUtilizedCount++;
                    }
                }
            } else {
                // PT and HL: Apply NRA and campaignName filters (same as getAmzUnderUtilizedBgtPt)
                if ($nra !== 'NRA' && $campaignName !== '') {
                    if ($ub7 > 90 && $ub1 > 90) {
                        $overUtilizedCount++;
                    } elseif ($ub7 < 70) {
                        $underUtilizedCount++;
                    } elseif ($ub7 >= 70 && $ub7 <= 90) {
                        $correctlyUtilizedCount++;
                    }
                }
            }
        }

        // Store in amazon_data_view table with date as SKU
        $today = now()->format('Y-m-d');
        $data = [
            'over_utilized' => $overUtilizedCount,
            'under_utilized' => $underUtilizedCount,
            'correctly_utilized' => $correctlyUtilizedCount,
            'date' => $today
        ];

        // Use date as SKU identifier for this data with campaign type
        $skuKey = 'AMAZON_UTILIZATION_' . $campaignType . '_' . $today;

        // Check if record exists for today
        $existing = AmazonDataView::where('sku', $skuKey)->first();

        if ($existing) {
            $existing->update(['value' => $data]);
            $this->info("Updated {$campaignType} utilization counts for {$today}");
        } else {
            AmazonDataView::create([
                'sku' => $skuKey,
                'value' => $data
            ]);
            $this->info("Created {$campaignType} utilization counts for {$today}");
        }

        $this->info("{$campaignType} - Over-utilized: {$overUtilizedCount}");
        $this->info("{$campaignType} - Under-utilized: {$underUtilizedCount}");
        $this->info("{$campaignType} - Correctly-utilized: {$correctlyUtilizedCount}");
    }
}
