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

class Ebay3PinkDilAdController extends Controller
{
    public function index()
    {
        return view('campaign.ebay-three.pink-dil-ads');
    }

    public function getEbay3PinkDilAdsData()
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $nrValues = EbayThreeDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $reports = Ebay3PriorityReport::whereIn('report_range', ['L7', 'L1', 'L30'])
            ->orderBy('report_range', 'asc')
            ->get();

        $result = [];
        $matchedCampaignIds = []; 

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData[$pm->sku] ?? null;

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

            $matchedReports = $reports->filter(function ($item) use ($sku) {
                return stripos($item->campaign_name ?? '', $sku) !== false;
            });

            if ($matchedReports->isEmpty()) {
                continue;
            }

            foreach ($matchedReports as $campaign) {
                $matchedCampaignIds[] = $campaign->id;

                $row = [];
                $row['parent'] = $parent;
                $row['sku'] = $pm->sku;
                $row['report_range'] = $campaign->report_range;
                $row['campaign_id'] = $campaign->campaign_id ?? '';
                $row['campaignName'] = $campaign->campaign_name ?? '';
                $row['campaignBudgetAmount'] = $campaign->campaignBudgetAmount ?? 0;
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;

                $adFees = (float) str_replace('USD ', '', $campaign->cpc_ad_fees_payout_currency ?? 0);
                $sales  = (float) str_replace('USD ', '', $campaign->cpc_sale_amount_payout_currency ?? 0);
                $row['l7_spend'] = (float) str_replace('USD ', '', $campaign->report_range == 'L7' ? $campaign->cpc_ad_fees_payout_currency ?? 0 : 0);
                $row['l7_cpc'] = (float) str_replace('USD ', '', $campaign->report_range == 'L7' ? $campaign->cost_per_click ?? 0 : 0);
                $row['l1_spend'] = (float) str_replace('USD ', '', $campaign->report_range == 'L1' ? $campaign->cpc_ad_fees_payout_currency ?? 0 : 0);
                $row['l1_cpc'] = (float) str_replace('USD ', '', $campaign->report_range == 'L1' ? $campaign->cost_per_click ?? 0 : 0);

                $acos = $sales > 0 ? ($adFees / $sales) * 100 : 0;
                if ($adFees > 0 && $sales == 0) {
                    $row['acos'] = 100;
                } else {
                    $row['acos'] = round($acos, 2);
                }

                $row['adFees'] = $adFees;
                $row['sales'] = $sales;
                $row['NR'] = $nrValue;

                if ($row['NR'] != 'NRA') {
                    $result[] = (object) $row;
                }
            }
        }

        return response()->json([
            'message' => 'fetched successfully',
            'data' => $result,
            'status' => 200,
        ]);
    }
}
