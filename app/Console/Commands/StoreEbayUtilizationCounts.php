<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EbayDataView;
use App\Models\EbayThreeDataView;
use App\Models\EbayPriorityReport;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreEbayUtilizationCounts extends Command
{
    protected $signature = 'ebay:store-utilization-counts';
    protected $description = 'Store daily counts of over/under/correctly utilized eBay and eBay3 campaigns';

    public function handle()
    {
        $this->info('Starting to store eBay utilization counts...');

        // Process eBay
        $this->processEbay();

        // Process eBay3
        $this->processEbay3();

        $this->info('Completed storing eBay utilization counts.');
        return 0;
    }

    private function processEbay()
    {
        $this->info("Processing eBay campaigns...");

        $productMasters = ProductMaster::whereNull('deleted_at')
            ->orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->where('campaignStatus', 'RUNNING')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL1 = EbayPriorityReport::where('report_range', 'L1')
            ->where('campaignStatus', 'RUNNING')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where('campaignStatus', 'RUNNING')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        // Calculate total ACOS from ALL RUNNING campaigns
        $allL30Campaigns = EbayPriorityReport::where('report_range', 'L30')
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

        $overUtilizedCount = 0;
        $underUtilizedCount = 0;
        $correctlyUtilizedCount = 0;

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
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

            $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30;
            if (!$campaignForDisplay || $campaignForDisplay->campaignStatus !== 'RUNNING') {
                continue;
            }

            $price = $ebay->ebay_price ?? 0;
            if ($price < 30) {
                continue;
            }

            $budget = $campaignForDisplay->campaignBudgetAmount ?? 0;
            $l7_spend = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0) : 0;
            $l1_spend = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0) : 0;
            
            $adFees = $matchedCampaignL30 ? (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0) : 0;
            $sales = $matchedCampaignL30 ? (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0) : 0;
            $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
            if ($acos === 0) {
                $acos = 100;
            }

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            $rowAcos = $acos;
            if ($rowAcos == 0) {
                $rowAcos = 100;
            }

            $inv = $shopify->inv ?? 0;
            $l30 = $shopify->quantity ?? 0;

            // Check DIL color (exclude pink for over and under)
            $dilDecimal = (is_numeric($l30) && is_numeric($inv) && $inv !== 0) ? ($l30 / $inv) : 0;
            $dilPercent = $dilDecimal * 100;
            $isPink = ($dilPercent >= 50);

            // Over-utilized: (rowAcos > totalACOSAll && ub7 > 33) || (rowAcos <= totalACOSAll && ub7 > 90)
            if ($totalACOSAll > 0) {
                $condition1 = ($rowAcos > $totalACOSAll && $ub7 > 33);
                $condition2 = ($rowAcos <= $totalACOSAll && $ub7 > 90);
                if (($condition1 || $condition2) && !$isPink) {
                    $overUtilizedCount++;
                }
            }

            // Under-utilized: ub7 < 70 && ub1 < 70 + price >= 30 + INV > 0 + exclude pink
            if ($ub7 < 70 && $ub1 < 70 && $price >= 30 && $inv > 0 && !$isPink) {
                $underUtilizedCount++;
            }

            // Correctly-utilized: ub7 >= 70 && ub7 <= 90
            if ($ub7 >= 70 && $ub7 <= 90) {
                $correctlyUtilizedCount++;
            }
        }

        // Store in ebay_data_view table
        $today = now()->format('Y-m-d');
        $data = [
            'over_utilized' => $overUtilizedCount,
            'under_utilized' => $underUtilizedCount,
            'correctly_utilized' => $correctlyUtilizedCount,
            'date' => $today
        ];

        $skuKey = 'EBAY_UTILIZATION_' . $today;

        EbayDataView::updateOrCreate(
            ['sku' => $skuKey],
            ['value' => $data]
        );

        $this->info("eBay - Over-utilized: {$overUtilizedCount}");
        $this->info("eBay - Under-utilized: {$underUtilizedCount}");
        $this->info("eBay - Correctly-utilized: {$correctlyUtilizedCount}");
    }

    private function processEbay3()
    {
        $this->info("Processing eBay3 campaigns...");

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
            ->where('campaignStatus', 'RUNNING')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->orderBy('report_range', 'asc')
            ->get();

        $campaignMap = [];

        // Process campaigns matching ProductMaster SKUs
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
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

            foreach ($matchedReports as $campaign) {
                $campaignId = $campaign->campaign_id ?? '';
                if (empty($campaignId) || $campaign->campaignStatus !== 'RUNNING') {
                    continue;
                }

                if (!isset($campaignMap[$campaignId])) {
                    $campaignMap[$campaignId] = [
                        'campaign_id' => $campaignId,
                        'campaignBudgetAmount' => $campaign->campaignBudgetAmount ?? 0,
                        'l7_spend' => 0,
                        'l1_spend' => 0,
                        'acos' => 0,
                        'INV' => ($shopifyData[$pm->sku] ?? null) ? (int)($shopifyData[$pm->sku]->inv ?? 0) : 0,
                        'L30' => ($shopifyData[$pm->sku] ?? null) ? (int)($shopifyData[$pm->sku]->quantity ?? 0) : 0,
                    ];
                }

                $reportRange = $campaign->report_range ?? '';
                $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');

                if ($reportRange == 'L7') {
                    $campaignMap[$campaignId]['l7_spend'] = $adFees;
                }

                if ($reportRange == 'L1') {
                    $campaignMap[$campaignId]['l1_spend'] = $adFees;
                }

                if ($reportRange == 'L30') {
                    if ($sales > 0) {
                        $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                    } else if ($adFees > 0 && $sales == 0) {
                        $campaignMap[$campaignId]['acos'] = 100;
                    }
                }
            }
        }

        // Process campaigns that don't match ProductMaster SKUs (additional RUNNING campaigns)
        $allCampaignIds = $reports->where('campaignStatus', 'RUNNING')->pluck('campaign_id')->unique();
        $processedCampaignIds = array_keys($campaignMap);
        
        foreach ($allCampaignIds as $campaignId) {
            if (in_array($campaignId, $processedCampaignIds)) {
                continue; // Already processed
            }

            $campaignReports = $reports->where('campaign_id', $campaignId)->where('campaignStatus', 'RUNNING');
            if ($campaignReports->isEmpty()) {
                continue;
            }

            $firstCampaign = $campaignReports->first();
            $campaignName = $firstCampaign->campaign_name ?? '';
            
            // Try to find matching SKU in ProductMaster for INV/L30 data
            $matchedSku = null;
            foreach ($productMasters as $pm) {
                if (strtoupper(trim($pm->sku)) === strtoupper(trim($campaignName))) {
                    $matchedSku = $pm->sku;
                    break;
                }
            }

            $campaignMap[$campaignId] = [
                'campaign_id' => $campaignId,
                'campaignBudgetAmount' => $firstCampaign->campaignBudgetAmount ?? 0,
                'l7_spend' => 0,
                'l1_spend' => 0,
                'acos' => 0,
                'INV' => ($matchedSku && isset($shopifyData[$matchedSku])) ? (int)($shopifyData[$matchedSku]->inv ?? 0) : 0,
                'L30' => ($matchedSku && isset($shopifyData[$matchedSku])) ? (int)($shopifyData[$matchedSku]->quantity ?? 0) : 0,
            ];

            foreach ($campaignReports as $campaign) {
                $reportRange = $campaign->report_range ?? '';
                $adFees = (float) str_replace(['USD ', ','], '', $campaign->cpc_ad_fees_payout_currency ?? '0');
                $sales = (float) str_replace(['USD ', ','], '', $campaign->cpc_sale_amount_payout_currency ?? '0');

                if ($reportRange == 'L7') {
                    $campaignMap[$campaignId]['l7_spend'] = $adFees;
                }

                if ($reportRange == 'L1') {
                    $campaignMap[$campaignId]['l1_spend'] = $adFees;
                }

                if ($reportRange == 'L30') {
                    if ($sales > 0) {
                        $campaignMap[$campaignId]['acos'] = round(($adFees / $sales) * 100, 2);
                    } else if ($adFees > 0 && $sales == 0) {
                        $campaignMap[$campaignId]['acos'] = 100;
                    }
                }
            }
        }

        // Calculate total ACOS from ALL RUNNING campaigns
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

        // Count campaigns by utilization type
        $overUtilizedCount = 0;
        $underUtilizedCount = 0;
        $correctlyUtilizedCount = 0;

        foreach ($campaignMap as $campaign) {
            $budget = $campaign['campaignBudgetAmount'] ?? 0;
            $l7_spend = $campaign['l7_spend'] ?? 0;
            $l1_spend = $campaign['l1_spend'] ?? 0;
            $acos = $campaign['acos'] ?? 0;
            $l30 = $campaign['L30'] ?? 0;
            $inv = $campaign['INV'] ?? 0;

            $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
            $ub1 = $budget > 0 ? ($l1_spend / $budget) * 100 : 0;

            $rowAcos = $acos;
            if ($rowAcos == 0) {
                $rowAcos = 100;
            }

            // Check DIL color (exclude pink for over and under)
            $dilDecimal = (is_numeric($l30) && is_numeric($inv) && $inv !== 0) ? ($l30 / $inv) : 0;
            $dilPercent = $dilDecimal * 100;
            $isPink = ($dilPercent >= 50);

            // Categorize campaigns with priority: Over > Under > Correctly (mutually exclusive)
            $categorized = false;
            
            // Over-utilized: (rowAcos > totalACOSAll && ub7 > 33) || (rowAcos <= totalACOSAll && ub7 > 90)
            if ($totalACOSAll > 0 && !$isPink) {
                $condition1 = ($rowAcos > $totalACOSAll && $ub7 > 33);
                $condition2 = ($rowAcos <= $totalACOSAll && $ub7 > 90);
                if ($condition1 || $condition2) {
                    $overUtilizedCount++;
                    $categorized = true;
                }
            }

            // Under-utilized: ub7 < 70 && ub1 < 70 (only if not already over-utilized)
            if (!$categorized && $ub7 < 70 && $ub1 < 70 && !$isPink) {
                $underUtilizedCount++;
                $categorized = true;
            }

            // Correctly-utilized: (ub7 >= 70 && ub7 <= 90) && (ub1 >= 70 && ub1 <= 90) (only if not already categorized)
            if (!$categorized && (($ub7 >= 70 && $ub7 <= 90) && ($ub1 >= 70 && $ub1 <= 90))) {
                $correctlyUtilizedCount++;
                $categorized = true;
            }
        }

        // Store in ebay3_data_view table
        $today = now()->format('Y-m-d');
        $data = [
            'over_utilized' => $overUtilizedCount,
            'under_utilized' => $underUtilizedCount,
            'correctly_utilized' => $correctlyUtilizedCount,
            'date' => $today
        ];

        $skuKey = 'EBAY3_UTILIZATION_' . $today;

        EbayThreeDataView::updateOrCreate(
            ['sku' => $skuKey],
            ['value' => $data]
        );

        $totalCounted = $overUtilizedCount + $underUtilizedCount + $correctlyUtilizedCount;
        $totalCampaigns = count($campaignMap);
        
        $this->info("eBay3 - Over-utilized: {$overUtilizedCount}");
        $this->info("eBay3 - Under-utilized: {$underUtilizedCount}");
        $this->info("eBay3 - Correctly-utilized: {$correctlyUtilizedCount}");
        $this->info("eBay3 - Total campaigns in map: {$totalCampaigns}");
        $this->info("eBay3 - Total counted: {$totalCounted}");
        
        if ($totalCounted != $totalCampaigns) {
            $this->warn("Warning: Some campaigns are not categorized! Missing: " . ($totalCampaigns - $totalCounted));
        }
    }
}
