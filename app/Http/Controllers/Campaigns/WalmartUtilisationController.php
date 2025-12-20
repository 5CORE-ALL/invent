<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\WalmartCampaignReport;
use App\Models\WalmartDataView;
use App\Models\WalmartProductSheet;

class WalmartUtilisationController extends Controller
{
    public function index(){
        return view('campaign.walmart-utilized-kw-ads');
    }

    public function bgtUtilisedView(){
        return view('campaign.walmart-bgt-util');
    }

    public function overUtilisedView(){
        return view('campaign.walmart-over-utili');
    }

    public function underUtilisedView(){
        return view('campaign.walmart-under-utili');
    }

    public function correctlyUtilisedView(){
        return view('campaign.walmart-correctly-utili');
    }

    public function getWalmartAdsData()
    {
        $normalizeSku = fn($sku) => strtoupper(trim(preg_replace('/\s+/', ' ', str_replace("\xc2\xa0", ' ', $sku))));

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')
            ->filter()
            ->unique()
            ->map(fn($sku) => $normalizeSku($sku))
            ->values()
            ->all();

        $walmartProductSheet = WalmartProductSheet::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        $nrValues = WalmartDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $walmartCampaignReportsAll = WalmartCampaignReport::whereIn('campaignName', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->campaignName));

        $walmartCampaignReportsL30 = WalmartCampaignReport::where('report_range', 'L30')->whereIn('campaignName', $skus)->get();
        $walmartCampaignReportsL7  = WalmartCampaignReport::where('report_range', 'L7')->whereIn('campaignName', $skus)->get();
        $walmartCampaignReportsL1  = WalmartCampaignReport::where('report_range', 'L1')->whereIn('campaignName', $skus)->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = $normalizeSku($pm->sku);
            $parent = $pm->parent;

            $amazonSheet = $walmartProductSheet[$sku] ?? null;
            $shopify = $shopifyData[$sku] ?? null;

            // Campaign name & budget without report_range
            $matchedCampaign = $walmartCampaignReportsAll[$sku] ?? null;

            if (!$matchedCampaign) {
                continue;
            }

            // Metrics by report_range
            $matchedCampaignL30 = $walmartCampaignReportsL30->first(fn($item) => $normalizeSku($item->campaignName) === $sku);
            $matchedCampaignL7  = $walmartCampaignReportsL7->first(fn($item) => $normalizeSku($item->campaignName) === $sku);
            $matchedCampaignL1  = $walmartCampaignReportsL1->first(fn($item) => $normalizeSku($item->campaignName) === $sku);

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['WA_L30'] = $amazonSheet->l30 ?? 0;

            // Campaign info (all SKUs)
            $row['campaignName'] = $matchedCampaign->campaignName ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaign->budget ?? '';
            $row['campaignStatus'] = $matchedCampaign->status ?? '';

            // Metrics
            $row['clicks_l30'] = $matchedCampaignL30->clicks ?? 0;
            $row['spend_l7']   = $matchedCampaignL7->spend ?? 0;
            $row['spend_l1']   = $matchedCampaignL1->spend ?? 0;
            $row['cpc_l7']     = $matchedCampaignL7->cpc ?? 0;
            $row['cpc_l1']     = $matchedCampaignL1->cpc ?? 0;

            // NR
            $row['NRA']  = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) {
                    $row['NRA'] = $raw['NR'] ?? null;
                }
            }

            $result[] = (object) $row;
        }

        $uniqueResult = collect($result)->unique('sku')->values()->all();

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $uniqueResult,
            'status'  => 200,
        ]);
    }


}
