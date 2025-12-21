<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmazonDataView;
use App\Models\AmazonSpCampaignReport;
use App\Models\FbaTable;
use App\Models\FbaManualData;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;

class StoreAmazonFbaUtilizationCounts extends Command
{
    protected $signature = 'amazon-fba:store-utilization-counts';
    protected $description = 'Store daily counts of over/under/correctly utilized Amazon FBA KW and PT campaigns';

    public function handle()
    {
        $this->info('Starting to store Amazon FBA utilization counts...');

        // Process KW and PT campaigns for FBA
        $this->processCampaignType('KW');
        $this->processCampaignType('PT');

        return 0;
    }

    private function processCampaignType($campaignType)
    {
        $this->info("Processing FBA {$campaignType} campaigns...");

        // Get all FBA records
        $fbaData = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->orderBy('seller_sku', 'asc')
            ->get();

        // Extract seller SKUs for campaigns matching
        $sellerSkus = $fbaData->pluck('seller_sku')->unique()->toArray();

        // Get base SKUs (without FBA) for Shopify data
        $baseSkus = $fbaData->map(function ($item) {
            $sku = $item->seller_sku;
            $base = preg_replace('/\s*FBA\s*/i', '', $sku);
            return strtoupper(trim($base));
        })->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $baseSkus)
            ->get()
            ->keyBy(function ($item) {
                return trim(strtoupper($item->sku));
            });

        $nrValues = FbaManualData::whereIn('sku', $sellerSkus)->pluck('data', 'sku');

        // Get FBA campaigns based on type
        $amazonSpCampaignReportsL7 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L7')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED');

        $amazonSpCampaignReportsL1 = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L1')
            ->where(function ($q) use ($sellerSkus) {
                foreach ($sellerSkus as $sku) {
                    $q->orWhere('campaignName', 'LIKE', '%' . $sku . '%');
                }
            })
            ->where('campaignStatus', '!=', 'ARCHIVED');

        // Filter by campaign type
        if ($campaignType === 'PT') {
            $amazonSpCampaignReportsL7->where(function($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                  ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            });
            $amazonSpCampaignReportsL1->where(function($q) {
                $q->where('campaignName', 'LIKE', '%FBA PT%')
                  ->orWhere('campaignName', 'LIKE', '%FBA PT.%');
            });
        } else {
            $amazonSpCampaignReportsL7->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'");
            $amazonSpCampaignReportsL1->whereRaw("LOWER(TRIM(TRAILING '.' FROM campaignName)) NOT LIKE '% pt'");
        }

        $amazonSpCampaignReportsL7 = $amazonSpCampaignReportsL7->get();
        $amazonSpCampaignReportsL1 = $amazonSpCampaignReportsL1->get();

        // Store all processed campaigns with their data
        $campaignMap = [];
        
        // For PT campaigns, we need to track unique SKUs (base SKU without FBA)
        $processedBaseSkus = [];

        foreach ($fbaData as $fba) {
            $sellerSku = $fba->seller_sku;
            $sellerSkuUpper = strtoupper(trim($sellerSku));
            
            // Get base SKU (without FBA)
            $baseSku = preg_replace('/\s*FBA\s*/i', '', $sellerSku);
            $baseSkuUpper = strtoupper(trim($baseSku));

            // For PT campaigns, apply unique base SKU filter (same as getAmazonFbaUtilizedPtAdsData)
            if ($campaignType === 'PT' && in_array($baseSkuUpper, $processedBaseSkus)) {
                continue;
            }

            // Check NRA filter
            $nra = '';
            if (isset($nrValues[$sellerSku])) {
                $raw = $nrValues[$sellerSku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $nra = $raw['NRA'] ?? '';
                }
            }
            
            if ($nra === 'NRA') {
                continue;
            }

            // Match campaigns
            if ($campaignType === 'PT') {
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sellerSkuUpper) {
                    $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sellerSkuUpper, '.')));
                    $expected = $cleanSku . ' PT';
                    return $cleanName === $expected || $cleanName === ($expected . '.');
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sellerSkuUpper) {
                    $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                    $cleanSku = strtoupper(trim(rtrim($sellerSkuUpper, '.')));
                    $expected = $cleanSku . ' PT';
                    return $cleanName === $expected || $cleanName === ($expected . '.');
                });
            } else {
                $matchedCampaignL7 = $amazonSpCampaignReportsL7->first(function ($item) use ($sellerSkuUpper) {
                    $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                    return (
                        str_contains($cleanName, $sellerSkuUpper)
                        && !str_ends_with($cleanName, ' PT')
                        && !str_ends_with($cleanName, ' PT.')
                    );
                });

                $matchedCampaignL1 = $amazonSpCampaignReportsL1->first(function ($item) use ($sellerSkuUpper) {
                    $cleanName = strtoupper(trim(rtrim($item->campaignName, '.')));
                    return (
                        str_contains($cleanName, $sellerSkuUpper)
                        && !str_ends_with($cleanName, ' PT')
                        && !str_ends_with($cleanName, ' PT.')
                    );
                });
            }

            if (!$matchedCampaignL7 && !$matchedCampaignL1) {
                continue;
            }

            $campaignId = $matchedCampaignL7->campaign_id ?? ($matchedCampaignL1->campaign_id ?? '');
            $campaignName = $matchedCampaignL7->campaignName ?? ($matchedCampaignL1->campaignName ?? '');
            
            if (empty($campaignId) || empty($campaignName)) {
                continue;
            }

            // Get INV from FBA table (quantity_available) - same as controller
            $inv = $fba->quantity_available ?? 0;

            // Store campaign data in map (only once per campaign_id)
            if (!isset($campaignMap[$campaignId])) {
                $budget = $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? 0);
                $l7_spend = $matchedCampaignL7->spend ?? 0;
                $l1_spend = $matchedCampaignL1->spend ?? 0;

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
                if (($matchedCampaignL7->spend ?? 0) > 0) {
                    $campaignMap[$campaignId]['l7_spend'] = $matchedCampaignL7->spend ?? 0;
                }
                if (($matchedCampaignL1->spend ?? 0) > 0) {
                    $campaignMap[$campaignId]['l1_spend'] = $matchedCampaignL1->spend ?? 0;
                }
                // Update INV if current SKU has INV > 0 (prefer INV > 0 over INV = 0)
                if (floatval($inv) > 0) {
                    $campaignMap[$campaignId]['inv'] = $inv;
                }
            }
            
            // For PT campaigns, mark base SKU as processed (unique filter)
            if ($campaignType === 'PT') {
                $processedBaseSkus[] = $baseSkuUpper;
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
            // Skip campaigns with INV = 0 (same as regular Amazon command for KW and PT)
            if (floatval($campaignData['inv']) <= 0) {
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

        // Use date as SKU identifier for this data with campaign type (prefix with FBA)
        $skuKeyToday = 'AMAZON_FBA_UTILIZATION_' . $campaignType . '_' . $today;
        $skuKeyTomorrow = 'AMAZON_FBA_UTILIZATION_' . $campaignType . '_' . $tomorrow;

        // Insert/Update today's data
        $existingToday = AmazonDataView::where('sku', $skuKeyToday)->first();

        if ($existingToday) {
            $existingToday->update(['value' => $data]);
            $this->info("Updated FBA {$campaignType} utilization counts for {$today}");
        } else {
            AmazonDataView::create([
                'sku' => $skuKeyToday,
                'value' => $data
            ]);
            $this->info("Created FBA {$campaignType} utilization counts for {$today}");
        }

        // Insert/Update tomorrow's blank data (only if it doesn't exist)
        $existingTomorrow = AmazonDataView::where('sku', $skuKeyTomorrow)->first();

        if (!$existingTomorrow) {
            AmazonDataView::create([
                'sku' => $skuKeyTomorrow,
                'value' => $blankData
            ]);
            $this->info("Created blank FBA {$campaignType} utilization counts for {$tomorrow}");
        } else {
            $this->info("Tomorrow's data already exists for {$tomorrow}, skipping blank data creation");
        }

        $this->info("FBA {$campaignType} - 7UB Condition:");
        $this->info("  Over-utilized: {$overUtilizedCount7ub}");
        $this->info("  Under-utilized: {$underUtilizedCount7ub}");
        $this->info("  Correctly-utilized: {$correctlyUtilizedCount7ub}");
        $this->info("FBA {$campaignType} - 7UB + 1UB Condition:");
        $this->info("  Over-utilized: {$overUtilizedCount7ub1ub}");
        $this->info("  Under-utilized: {$underUtilizedCount7ub1ub}");
        $this->info("  Correctly-utilized: {$correctlyUtilizedCount7ub1ub}");
    }
}

