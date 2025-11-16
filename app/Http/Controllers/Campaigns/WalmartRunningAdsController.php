<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\WalmartCampaignReport;
use App\Models\WalmartDataView;
use App\Models\ADVMastersData;
use App\Models\WalmartProductSheet;
use Illuminate\Http\Request;

class WalmartRunningAdsController extends Controller
{
    public function index()
    {
        return view('campaign.walmart-running-ads');
    }

    public function getAdvWalmartRunningSaveData(Request $request)
    {
        return ADVMastersData::getAdvWalmartRunningSaveDataProceed($request);
    }

    public function getWalmartRunningAdsData()
    {
        $normalizeSku = fn($sku) => strtoupper(trim($sku));

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        $walmartProductSheet = WalmartProductSheet::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
        $nrValues = WalmartDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $walmartCampaignReportsAll = WalmartCampaignReport::whereIn('campaignName', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->campaignName));

        $walmartCampaignReportsL30 = WalmartCampaignReport::where('report_range', 'L30')->whereIn('campaignName', $skus)->get();
        $walmartCampaignReportsL7  = WalmartCampaignReport::where('report_range', 'L7')->whereIn('campaignName', $skus)->get();

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

            $row = [];
            $row['parent'] = $parent;
            $row['sku']    = $pm->sku;
            $row['INV']    = $shopify->inv ?? 0;
            $row['L30']    = $shopify->quantity ?? 0;
            $row['WA_L30'] = $amazonSheet->l30 ?? 0;

            $row['campaignName'] = $matchedCampaign->campaignName ?? '';
            $row['campaignBudgetAmount'] = $matchedCampaign->budget ?? '';
            $row['campaignStatus'] = $matchedCampaign->status ?? '';

            //kw
            $row['kw_spend_L30'] = $matchedCampaignL30->spend ?? 0;
            $row['kw_spend_L7'] = $matchedCampaignL7->spend ?? 0;
            $row['kw_sales_L30'] = $matchedCampaignL30->sales ?? 0;
            $row['kw_sales_L7'] = $matchedCampaignL7->sales ?? 0;
            $row['kw_sold_L30'] = (int) ($matchedCampaignL30->sold ?? 0);
            $row['kw_sold_L7'] = (int) ($matchedCampaignL7->sold ?? 0);
            $row['kw_clicks_L30'] = (int) ($matchedCampaignL30?->clicks ?? 0);
            $row['kw_clicks_L7'] = (int) ($matchedCampaignL7?->clicks ?? 0);
            $row['kw_impr_L30'] = (int) ($matchedCampaignL30?->impression ?? 0);
            $row['kw_impr_L7'] = (int) ($matchedCampaignL7?->impression ?? 0);

            $row['SPEND_L30'] = $row['kw_spend_L30'];
            $row['SPEND_L7'] = $row['kw_spend_L7'];
            $row['SALES_L30'] = $row['kw_sales_L30'];
            $row['SALES_L7'] = $row['kw_sales_L7'];
            $row['SOLD_L30'] = $row['kw_sold_L30'];
            $row['SOLD_L7'] = $row['kw_sold_L7'];
            $row['CLICKS_L30'] = $row['kw_clicks_L30'];
            $row['CLICKS_L7'] = $row['kw_clicks_L7'];
            $row['IMP_L30'] = $row['kw_impr_L30'];
            $row['IMP_L7'] = $row['kw_impr_L7'];

            $row['NRA'] = '';
            if (isset($nrValues[$pm->sku])) {
                $raw = $nrValues[$pm->sku];
                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $row['NRA'] = $raw['NRA'] ?? '';
                }
            }

            if($row['campaignName'] !== ''){
                $result[] = $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }
}
