<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbayGeneralReport;
use App\Models\EbayMetric;
use App\Models\EbayPriorityReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;

class EbayViewsController extends Controller
{
    public function index()
    {
        return view('market-places.ebay_one_views');
    }

    public function getEbayViewsData()
    {
        $normalizeSku = fn($sku) => strtoupper(trim($sku));

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
        $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));
        $nrValues = EbayDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $ebayCampaignReportsL30 = EbayPriorityReport::where('report_range', 'L30')
            ->where(function ($q) use ($skus) {
                foreach ($skus as $sku) {
                    $q->orWhere('campaign_name', 'LIKE', '%' . $sku . '%');
                }
            })
            ->get();

        $itemIds = $ebayMetricData->pluck('item_id')->toArray();
        
        $ebayGeneralReportsL30 = EbayGeneralReport::where('report_range', 'L30')
            ->whereIn('listing_id', $itemIds)
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$sku] ?? null;
            $ebay = $ebayMetricData[$sku] ?? null;

            $matchedCampaignL30 = $ebayCampaignReportsL30->first(function ($item) use ($sku) {
                return strtoupper(trim($item->campaign_name)) === strtoupper(trim($sku));
            });
            
            $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebay) {
                if (!$ebay || empty($ebay->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
            });

            $row = [];

            $row['parent'] = $parent;
            $row['sku'] = $pm->sku;
            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;
            $row['e_l30'] = $ebay->ebay_l30 ?? 0;

            $row['kw_clicks_L30'] = (int) ($matchedCampaignL30?->cpc_clicks ?? 0);
            $row['pmt_clicks_L30'] = (int) ($matchedGeneralL30->clicks ?? 0);
            $row['org_clicks_L30'] = (int) ($ebay->organic_clicks ?? 0);

            $row['total_clicks'] = $row['kw_clicks_L30'] + $row['pmt_clicks_L30'] + $row['org_clicks_L30'];
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

            if($row['NR'] !== 'NRA'){
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
