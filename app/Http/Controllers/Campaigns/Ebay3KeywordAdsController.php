<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Ebay3Metric;
use App\Models\Ebay3PriorityReport;
use App\Models\EbayDataView;
use App\Models\EbayThreeDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;

class Ebay3KeywordAdsController extends Controller
{
    public function ebay3KeywordAdsView(){
        return view('campaign.ebay-three.keyword-ads');
    }

    public function getEbay3KeywordAdsData()
    {
        $productMasters = ProductMaster::orderBy('parent')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues    = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $allCampaigns = Ebay3PriorityReport::all();

        $normalize = fn($value) => is_string($value) ? strtoupper(trim($value)) : $value;

        $periods = ['L7', 'L15', 'L30', 'L60'];
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = $normalize($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [
                'parent' => $parent,
                'sku'    => $pm->sku,
                'INV'    => $shopify->inv ?? 0,
                'L30'    => $shopify->quantity ?? 0,
                'NR'     => ''
            ];

            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) $row['NR'] = $raw['NR'] ?? null;
            }

            $matchedCampaigns = $allCampaigns->filter(function ($c) use ($sku, $normalize, $pm) {
                if (!$c->campaign_name) {
                    return false;
                }
                
                $campaignName = $normalize($c->campaign_name);
                
                // Exact match first
                if ($campaignName === $sku) {
                    return true;
                }
                
                // For PARENT campaigns, also match campaigns that contain or start with the SKU part (without "PARENT ")
                if (str_starts_with($pm->sku, 'PARENT ')) {
                    $skuWithoutParent = str_replace('PARENT ', '', $pm->sku);
                    $skuWithoutParentNormalized = $normalize($skuWithoutParent);
                    
                    // Match campaigns that start with the SKU (e.g., "12 WF" matches "12 WF A", "12 WF B", etc.)
                    if (stripos($campaignName, $skuWithoutParentNormalized) === 0) {
                        return true;
                    }
                    
                    // Also try exact match with the SKU without "PARENT " (in case campaign is named "12 WF" not "PARENT 12 WF")
                    if ($campaignName === $skuWithoutParentNormalized) {
                        return true;
                    }
                }
                
                return false;
            });


            if ($matchedCampaigns->isEmpty()) continue;

            $row['campaignName']        = $matchedCampaigns->pluck('campaign_name')->unique()->implode(', ');
            $row['campaignBudgetAmount'] = $matchedCampaigns->sum('campaignBudgetAmount');
            
            // Prioritize RUNNING status, otherwise use the first unique status
            $statuses = $matchedCampaigns->pluck('campaignStatus')->unique()->values();
            $runningStatus = $statuses->first(function ($status) {
                return strtoupper(trim($status)) === 'RUNNING';
            });
            $row['campaignStatus'] = $runningStatus ?? $statuses->first() ?? '';

            foreach ($periods as $period) {
                $periodMatches = $matchedCampaigns->where('report_range', $period);

                $impressions = $periodMatches->sum('cpc_impressions');
                $clicks      = $periodMatches->sum('cpc_clicks');
                $adFees      = $periodMatches->sum(fn($item) => (float) str_replace('USD ', '', $item->cpc_ad_fees_payout_currency ?? 0));
                $sales       = $periodMatches->sum(fn($item) => (float) str_replace('USD ', '', $item->cpc_sale_amount_payout_currency ?? 0));
                $unitsSold   = $periodMatches->sum('unitsSold');

                $cpc  = $clicks > 0 ? ($adFees / $clicks) : 0;
                $acos = $sales > 0 ? ($adFees / $sales) * 100 : ($adFees > 0 ? 100 : 0);

                $row["impressions_" . strtolower($period)] = $impressions;
                $row["clicks_" . strtolower($period)]      = $clicks;
                $row["ad_sales_" . strtolower($period)]    = $sales;
                $row["ad_sold_" . strtolower($period)]     = $unitsSold;
                $row["spend_" . strtolower($period)]       = $adFees;
                $row["acos_" . strtolower($period)]        = $acos;
                $row["cpc_" . strtolower($period)]         = $cpc;
            }

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function ebay3PriceLessThanThirtyAdsView(){
        return view('campaign.ebay-three.ebay-less-thirty-kw-ads');
    }

    public function ebay3PriceLessThanThirtyAdsData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayMetrics = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
            ->orderBy('report_range', 'asc')
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper(trim($pm->sku));
            $parent = strtoupper(trim($pm->parent));
            $shopify = $shopifyData[$pm->sku] ?? null;

            // 🔹 Get NR
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

            // 🔹 Match only campaigns whose name == sku (exact match)
            $matchedReports = $reports->filter(function ($item) use ($sku) {
                return stripos(trim($item->campaign_name ?? ''), $sku) === 0; // starts with
            });


            // skip if no exact match
            if ($matchedReports->isEmpty()) {
                continue;
            }

            foreach ($matchedReports as $campaign) {
                $row = [];
                $row['parent'] = $parent;
                $row['sku'] = $pm->sku;
                $row['report_range'] = $campaign->report_range;
                $row['campaign_id'] = $campaign->campaign_id ?? '';
                $row['campaignName'] = $campaign->campaign_name ?? '';
                $row['campaignBudgetAmount'] = $campaign->campaignBudgetAmount ?? 0;
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;
                $row['price'] = isset($ebayMetrics[$pm->sku]) ? ($ebayMetrics[$pm->sku]->ebay_price ?? 0) : 0;

                $adFees = (float) str_replace('USD ', '', $campaign->cpc_ad_fees_payout_currency ?? 0);
                $sales  = (float) str_replace('USD ', '', $campaign->cpc_sale_amount_payout_currency ?? 0);

                // L7 / L1 data
                $row['l7_spend'] = (float) str_replace('USD ', '', $campaign->report_range == 'L7' ? $campaign->cpc_ad_fees_payout_currency ?? 0 : 0);
                $row['l7_cpc']   = (float) str_replace('USD ', '', $campaign->report_range == 'L7' ? $campaign->cost_per_click ?? 0 : 0);
                $row['l1_spend'] = (float) str_replace('USD ', '', $campaign->report_range == 'L1' ? $campaign->cpc_ad_fees_payout_currency ?? 0 : 0);
                $row['l1_cpc']   = (float) str_replace('USD ', '', $campaign->report_range == 'L1' ? $campaign->cost_per_click ?? 0 : 0);

                // ACOS calc
                $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
                $row['acos'] = ($adFees > 0 && $sales == 0) ? 100 : round($acos, 2);

                $row['adFees'] = $adFees;
                $row['sales']  = $sales;
                $row['NR']     = $nrValue;

                // SBID logic
                if ($row['price'] < 30 && $row['price'] > 0) {
                    if ($row['price'] < 10) {
                        $row['sbid'] = 0.10;
                    } elseif ($row['price'] > 10 && $row['price'] <= 20) {
                        $row['sbid'] = 0.20;
                    } elseif ($row['price'] > 20 && $row['price'] <= 30) {
                        $row['sbid'] = 0.30;
                    }
                }

                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function ebay3MakeNewKwAdsView(){
        return view('campaign.ebay-three.make-new-kw-ads');
    }

    public function getEbay3MMakeNewKwAdsData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayCampaignReportsL7 = Ebay3PriorityReport::where('report_range', 'L7')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL1 = Ebay3PriorityReport::where('report_range', 'L1')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $ebayCampaignReportsL30 = Ebay3PriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$pm->sku] ?? null;

            $matchedCampaignL7 = $ebayCampaignReportsL7->first(function ($item) use ($sku) {
                return $item->campaign_name && stripos($item->campaign_name, $sku) !== false;
            });

            $matchedCampaignL1 = $ebayCampaignReportsL1->first(function ($item) use ($sku) {
                return $item->campaign_name && stripos($item->campaign_name, $sku) !== false;
            });

            $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                return $item->campaign_name && stripos($item->campaign_name, $sku) !== false;
            });

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['campaign_id'] = $matchedCampaignL7 ? ($matchedCampaignL7->campaign_id ?? '') : ($matchedCampaignL1 ? ($matchedCampaignL1->campaign_id ?? '') : '');
            $row['campaignName'] = $matchedCampaignL7 ? ($matchedCampaignL7->campaign_name ?? '') : ($matchedCampaignL1 ? ($matchedCampaignL1->campaign_name ?? '') : '');
            $row['campaignBudgetAmount'] = $matchedCampaignL7 ? ($matchedCampaignL7->campaignBudgetAmount ?? '') : ($matchedCampaignL1 ? ($matchedCampaignL1->campaignBudgetAmount ?? '') : '');

            $adFees   = $matchedCampaignL30 ? (float) str_replace('USD ', '', $matchedCampaignL30->cpc_ad_fees_payout_currency ?? 0) : 0;
            $sales    = $matchedCampaignL30 ? (float) str_replace('USD ', '', $matchedCampaignL30->cpc_sale_amount_payout_currency ?? 0) : 0;

            $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
            
            if($adFees > 0 && $sales === 0){
                $row['acos'] = 100;
            }else{
                $row['acos'] = $acos;
            }

            $row['l7_spend'] = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cpc_ad_fees_payout_currency ?? 0) : 0;
            $row['l7_cpc'] = $matchedCampaignL7 ? (float) str_replace('USD ', '', $matchedCampaignL7->cost_per_click ?? 0) : 0;
            $row['l1_spend'] = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cpc_ad_fees_payout_currency ?? 0) : 0;
            $row['l1_cpc'] = $matchedCampaignL1 ? (float) str_replace('USD ', '', $matchedCampaignL1->cost_per_click ?? 0) : 0;

            $row['NR'] = '';
            $row['NRL'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['NRL'] = $raw['NRL'] ?? null;

                }
            }
            if ($row['campaignName'] === '' && ($row['NR'] !== 'NRA' && $row['NRL'] !== 'NRL')) {
                $result[] = (object) $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }
}
