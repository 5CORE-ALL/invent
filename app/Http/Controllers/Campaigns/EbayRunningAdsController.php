<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbayGeneralReport;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ADVMastersData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EbayRunningAdsController extends Controller
{
    public function index()
    {
        // Get chart data for last 30 days
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        $data = DB::table('ebay_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get()
            ->keyBy('report_date');

        // Create array for all 30 days with data or zeros
        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;

            if (isset($data[$date])) {
                $row = $data[$date];
                $clicksVal = (int) $row->clicks;
                $spendVal = (float) $row->spend;
                $salesVal = (float) $row->ad_sales;
                $soldVal = (int) $row->ad_sold;

                $clicks[] = $clicksVal;
                $spend[] = $spendVal;
                $adSales[] = $salesVal;
                $adSold[] = $soldVal;

                // ACOS = (Spend / Sales) * 100
                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                // CVR = (Ad Sold / Clicks) * 100
                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                $clicks[] = 0;
                $spend[] = 0;
                $adSales[] = 0;
                $adSold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }
        }

        return view('campaign.ebay-running-ads', compact('dates', 'clicks', 'spend', 'acos', 'cvr'))
            ->with('ad_sales', $adSales)
            ->with('ad_sold', $adSold);
    }

    public function getEbayRunningDataSave(Request $request)
    {
        return ADVMastersData::getEbayRunningDataSaveProceed($request);
    }

    public function getEbayRunningAdsData()
    {
        $normalizeSku = fn($sku) => strtoupper(trim($sku));

        $productMasters = ProductMaster::whereNull('deleted_at')
            ->orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $productMasterSkus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();

        // Get additional RUNNING campaigns that are not in ProductMaster but are valid SKUs
        $additionalRunningCampaigns = EbayPriorityReport::where('report_range', 'L7')
            ->where('campaignStatus', 'RUNNING')
            ->whereNotNull('campaign_name')
            ->where('campaign_name', '!=', '')
            ->where('campaign_name', 'NOT LIKE', 'Campaign %')
            ->where('campaign_name', 'NOT LIKE', 'General - %')
            ->where('campaign_name', 'NOT LIKE', 'Default%')
            ->get()
            ->pluck('campaign_name')
            ->unique()
            ->filter(function($name) use ($productMasterSkus, $normalizeSku) {
                $nameUpper = $normalizeSku($name);
                return !in_array($nameUpper, $productMasterSkus);
            })
            ->values()
            ->all();

        // Merge both lists
        $skus = array_merge($productMasterSkus, $additionalRunningCampaigns);

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        $ebayMetricData = DB::connection('apicentral')->table('ebay_one_metrics')
            ->select('sku', 'ebay_price', 'item_id')
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(fn($item) => $normalizeSku($item->sku));

        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        $ebayCampaignReportsL7 = EbayPriorityReport::where('report_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->orderByRaw("CASE WHEN campaignStatus = 'RUNNING' THEN 0 ELSE 1 END")
            ->get();

        $itemIds = $ebayMetricData->pluck('item_id')->toArray();
        
        $ebayGeneralReportsL30 = EbayGeneralReport::where('report_range', 'L30')
            ->whereIn('listing_id', $itemIds)
            ->get();

        $ebayGeneralReportsL7 = EbayGeneralReport::where('report_range', 'L7')
            ->whereIn('listing_id', $itemIds)
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$sku] ?? null;
            $ebay = $ebayMetricData[$sku] ?? null;

            // Find matching campaigns, prioritize RUNNING status
            $matchedCampaignsL30 = $ebayCampaignReportsL30->filter(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });
            
            $matchedCampaignL30 = $matchedCampaignsL30->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL30->first();

            $matchedCampaignsL7 = $ebayCampaignReportsL7->filter(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });
            
            $matchedCampaignL7 = $matchedCampaignsL7->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL7->first();
            
            $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebay) {
                if (!$ebay || empty($ebay->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
            });

            $matchedGeneralL7 = $ebayGeneralReportsL7->first(function ($item) use ($ebay) {
                if (!$ebay || empty($ebay->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
            });

            $row = [];

            $row['parent'] = $parent;
            $row['sku'] = $pm->sku;
            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;
            $row['e_l30'] = $ebay->ebay_l30 ?? 0;
            
            // Use L7 campaign if exists, otherwise fallback to L30
            $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30;
            $row['campaignName'] = $campaignForDisplay->campaign_name ?? '';
            $row['campaignStatus'] = $campaignForDisplay->campaignStatus ?? '';

            //kw
            $row['kw_spend_L30'] = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $row['kw_spend_L7'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0);
            $row['kw_sales_L30'] = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0);
            $row['kw_sales_L7'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_sale_amount_payout_currency ?? 0);
            $row['kw_sold_L30'] = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);
            $row['kw_sold_L7'] = (int) ($matchedCampaignL7->cpc_attributed_sales ?? 0);
            $row['kw_clicks_L30'] = (int) ($matchedCampaignL30?->cpc_clicks ?? 0);
            $row['kw_clicks_L7'] = (int) ($matchedCampaignL7?->cpc_clicks ?? 0);
            $row['kw_impr_L30'] = (int) ($matchedCampaignL30?->cpc_impressions ?? 0);
            $row['kw_impr_L7'] = (int) ($matchedCampaignL7?->cpc_impressions ?? 0);

            //pmt
            $row['pmt_spend_L30'] = (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
            $row['pmt_sales_L30'] = (float) str_replace('USD ', '', $matchedGeneralL30->sale_amount ?? 0);
            $row['pmt_spend_L7'] = (float) str_replace('USD ', '', $matchedGeneralL7->ad_fees ?? 0);
            $row['pmt_sales_L7'] = (float) str_replace('USD ', '', $matchedGeneralL7->sale_amount ?? 0);

            $row['pmt_sold_L30'] = (int) ($matchedGeneralL30->sales ?? 0);
            $row['pmt_sold_L7'] = (int) ($matchedGeneralL7->sales ?? 0);
            $row['pmt_clicks_L30'] = (int) ($matchedGeneralL30->clicks ?? 0);
            $row['pmt_clicks_L7'] = (int) ($matchedGeneralL7->clicks ?? 0);
            $row['pmt_impr_L30'] = (int) ($matchedGeneralL30->impressions ?? 0);
            $row['pmt_impr_L7'] = (int) ($matchedGeneralL7->impressions ?? 0);

            $row['SPEND_L30'] = $row['kw_spend_L30'] + $row['pmt_spend_L30'];
            $row['SPEND_L7'] = $row['kw_spend_L7'] + $row['pmt_spend_L7'];
            $row['SALES_L30'] = $row['kw_sales_L30'] + $row['pmt_sales_L30'];
            $row['SALES_L7'] = $row['kw_sales_L7'] + $row['pmt_sales_L7'];
            $row['SOLD_L30'] = $row['kw_sold_L30'] + $row['pmt_sold_L30'];
            $row['SOLD_L7'] = $row['kw_sold_L7'] + $row['pmt_sold_L7'];
            $row['CLICKS_L30'] = $row['kw_clicks_L30'] + $row['pmt_clicks_L30'];
            $row['CLICKS_L7'] = $row['kw_clicks_L7'] + $row['pmt_clicks_L7'];
            $row['IMP_L30'] = $row['kw_impr_L30'] + $row['pmt_impr_L30'];
            $row['IMP_L7'] = $row['kw_impr_L7'] + $row['pmt_impr_L7'];

            $row['NR'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? '';
                }
            }

            if($row['campaignName'] !== ''){
                $result[] = $row;
            }
        }

        // Now process additional RUNNING campaigns that are not in ProductMaster
        foreach ($additionalRunningCampaigns as $campaignSku) {
            $sku = $normalizeSku($campaignSku);
            $shopify = $shopifyData[$sku] ?? null;
            $ebay = $ebayMetricData[$sku] ?? null;

            // Find matching campaigns
            $matchedCampaignsL30 = $ebayCampaignReportsL30->filter(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === $sku;
            });
            
            $matchedCampaignL30 = $matchedCampaignsL30->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL30->first();

            $matchedCampaignsL7 = $ebayCampaignReportsL7->filter(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === $sku;
            });
            
            $matchedCampaignL7 = $matchedCampaignsL7->first(function ($item) {
                return $item->campaignStatus === 'RUNNING';
            }) ?? $matchedCampaignsL7->first();

            $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebay) {
                if (!$ebay || empty($ebay->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
            });

            $matchedGeneralL7 = $ebayGeneralReportsL7->first(function ($item) use ($ebay) {
                if (!$ebay || empty($ebay->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
            });

            $row = [];
            $row['parent'] = '';
            $row['sku'] = $campaignSku;
            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;
            $row['e_l30'] = $ebay->ebay_l30 ?? 0;
            
            $campaignForDisplay = $matchedCampaignL7 ?? $matchedCampaignL30;
            $row['campaignName'] = $campaignForDisplay->campaign_name ?? '';
            $row['campaignStatus'] = $campaignForDisplay->campaignStatus ?? '';

            $row['kw_spend_L30'] = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0);
            $row['kw_spend_L7'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0);
            $row['kw_sales_L30'] = (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0);
            $row['kw_sales_L7'] = (float) str_replace('USD ', '', $matchedCampaignL7->cpc_sale_amount_payout_currency ?? 0);
            $row['kw_sold_L30'] = (int) ($matchedCampaignL30->cpc_attributed_sales ?? 0);
            $row['kw_sold_L7'] = (int) ($matchedCampaignL7->cpc_attributed_sales ?? 0);
            $row['kw_clicks_L30'] = (int) ($matchedCampaignL30?->cpc_clicks ?? 0);
            $row['kw_clicks_L7'] = (int) ($matchedCampaignL7?->cpc_clicks ?? 0);
            $row['kw_impr_L30'] = (int) ($matchedCampaignL30?->cpc_impressions ?? 0);
            $row['kw_impr_L7'] = (int) ($matchedCampaignL7?->cpc_impressions ?? 0);

            $row['pmt_spend_L30'] = (float) str_replace('USD ', '', $matchedGeneralL30->ad_fees ?? 0);
            $row['pmt_sales_L30'] = (float) str_replace('USD ', '', $matchedGeneralL30->sale_amount ?? 0);
            $row['pmt_spend_L7'] = (float) str_replace('USD ', '', $matchedGeneralL7->ad_fees ?? 0);
            $row['pmt_sales_L7'] = (float) str_replace('USD ', '', $matchedGeneralL7->sale_amount ?? 0);

            $row['pmt_sold_L30'] = (int) ($matchedGeneralL30->sales ?? 0);
            $row['pmt_sold_L7'] = (int) ($matchedGeneralL7->sales ?? 0);
            $row['pmt_clicks_L30'] = (int) ($matchedGeneralL30->clicks ?? 0);
            $row['pmt_clicks_L7'] = (int) ($matchedGeneralL7->clicks ?? 0);
            $row['pmt_impr_L30'] = (int) ($matchedGeneralL30->impressions ?? 0);
            $row['pmt_impr_L7'] = (int) ($matchedGeneralL7->impressions ?? 0);

            $row['SPEND_L30'] = $row['kw_spend_L30'] + $row['pmt_spend_L30'];
            $row['SPEND_L7'] = $row['kw_spend_L7'] + $row['pmt_spend_L7'];
            $row['SALES_L30'] = $row['kw_sales_L30'] + $row['pmt_sales_L30'];
            $row['SALES_L7'] = $row['kw_sales_L7'] + $row['pmt_sales_L7'];
            $row['SOLD_L30'] = $row['kw_sold_L30'] + $row['pmt_sold_L30'];
            $row['SOLD_L7'] = $row['kw_sold_L7'] + $row['pmt_sold_L7'];
            $row['CLICKS_L30'] = $row['kw_clicks_L30'] + $row['pmt_clicks_L30'];
            $row['CLICKS_L7'] = $row['kw_clicks_L7'] + $row['pmt_clicks_L7'];
            $row['IMP_L30'] = $row['kw_impr_L30'] + $row['pmt_impr_L30'];
            $row['IMP_L7'] = $row['kw_impr_L7'] + $row['pmt_impr_L7'];

            $row['NR'] = '';

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function getCampaignChartData(Request $request)
    {
        $campaignName = $request->input('campaign_name');
        
        if (!$campaignName) {
            return response()->json(['error' => 'Campaign name is required'], 400);
        }

        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

        $data = DB::table('ebay_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('campaign_name', $campaignName)
            ->where('report_range', 'L30')
            ->whereDate('updated_at', '>=', $thirtyDaysAgo->format('Y-m-d'))
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get()
            ->keyBy('report_date');

        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];

        for ($i = 30; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;

            if (isset($data[$date])) {
                $row = $data[$date];
                $clicksVal = (int) $row->clicks;
                $spendVal = (float) $row->spend;
                $salesVal = (float) $row->ad_sales;
                $soldVal = (int) $row->ad_sold;

                $clicks[] = $clicksVal;
                $spend[] = $spendVal;
                $adSales[] = $salesVal;
                $adSold[] = $soldVal;

                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                $clicks[] = 0;
                $spend[] = 0;
                $adSales[] = 0;
                $adSold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }
        }

        return response()->json([
            'dates'  => $dates,
            'clicks' => $clicks,
            'spend'  => $spend,
            'ad_sales'  => $adSales,
            'ad_sold' => $adSold,
            'acos' => $acos,
            'cvr' => $cvr,
        ]);
    }

    public function filterRunningAds(Request $request)
    {
        $start = \Carbon\Carbon::parse($request->startDate);
        $end   = \Carbon\Carbon::parse($request->endDate);

        $data = DB::table('ebay_priority_reports')
            ->selectRaw('
                DATE(updated_at) as report_date,
                SUM(cpc_clicks) as clicks,
                SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")) as spend,
                SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")) as ad_sales,
                SUM(cpc_attributed_sales) as ad_sold
            ')
            ->where('report_range', 'L30')
            ->whereBetween(DB::raw('DATE(updated_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('report_date', 'asc')
            ->get()
            ->keyBy('report_date');

        $dates = [];
        $clicks = [];
        $spend = [];
        $adSales = [];
        $adSold = [];
        $acos = [];
        $cvr = [];
        
        $totalClicks = 0;
        $totalSpend = 0;
        $totalAdSales = 0;
        $totalAdSold = 0;

        $currentDate = $start->copy();
        while ($currentDate->lte($end)) {
            $dateStr = $currentDate->format('Y-m-d');
            $dates[] = $dateStr;

            if (isset($data[$dateStr])) {
                $row = $data[$dateStr];
                $clicksVal = (int) $row->clicks;
                $spendVal = (float) $row->spend;
                $salesVal = (float) $row->ad_sales;
                $soldVal = (int) $row->ad_sold;

                $clicks[] = $clicksVal;
                $spend[] = $spendVal;
                $adSales[] = $salesVal;
                $adSold[] = $soldVal;

                $totalClicks += $clicksVal;
                $totalSpend += $spendVal;
                $totalAdSales += $salesVal;
                $totalAdSold += $soldVal;

                $acosVal = $salesVal > 0 ? ($spendVal / $salesVal) * 100 : 0;
                $acos[] = round($acosVal, 2);

                $cvrVal = $clicksVal > 0 ? ($soldVal / $clicksVal) * 100 : 0;
                $cvr[] = round($cvrVal, 2);
            } else {
                $clicks[] = 0;
                $spend[] = 0;
                $adSales[] = 0;
                $adSold[] = 0;
                $acos[] = 0;
                $cvr[] = 0;
            }

            $currentDate->addDay();
        }

        return response()->json([
            'dates'  => $dates,
            'clicks' => $clicks,
            'spend'  => $spend,
            'ad_sales'  => $adSales,
            'ad_sold' => $adSold,
            'acos' => $acos,
            'cvr' => $cvr,
            'totals' => [
                'clicks' => $totalClicks,
                'spend'  => $totalSpend,
                'ad_sales'  => $totalAdSales,
                'ad_sold' => $totalAdSold,
            ]
        ]);
    }
}
