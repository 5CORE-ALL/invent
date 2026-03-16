<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Ebay2GeneralReport;
use App\Models\EbayTwoDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ADVMastersData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Ebay2RunningAdsController extends Controller
{
    public function index()
    {
        return view('campaign.ebay-two.running-ads');
    }

    public function getEbay2AdvRunningAdDataSave(Request $request)
    {
        return ADVMastersData::getEbay2AdvRunningAdDataSaveProceed($request);
    }

    public function getEbay2RunningAdsData()
    {
        $normalizeSku = fn($sku) => strtoupper(trim($sku));

        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->map($normalizeSku)->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy(fn($item) => $normalizeSku($item->sku));

        $ebayMetricData = DB::connection('apicentral')->table('ebay2_metrics')
            ->select('sku', 'ebay_price', 'item_id')
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(fn($item) => $normalizeSku($item->sku));

        $nrValues = EbayTwoDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        $itemIds = $ebayMetricData->pluck('item_id')->toArray();
        
        $ebayGeneralReportsL30 = Ebay2GeneralReport::where('report_range', 'L30')
            ->whereIn('listing_id', $itemIds)
            ->get();

        $ebayGeneralReportsL7 = Ebay2GeneralReport::where('report_range', 'L7')
            ->whereIn('listing_id', $itemIds)
            ->get();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $parent = $pm->parent;

            $shopify = $shopifyData[$sku] ?? null;
            $ebay = $ebayMetricData[$sku] ?? null;
            
            $matchedGeneralL30 = $ebayGeneralReportsL30->first(function ($item) use ($ebay) {
                if (!$ebay || empty($ebay->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
            });

            $matchedGeneralL7 = $ebayGeneralReportsL7->first(function ($item) use ($ebay) {
                if (!$ebay || empty($ebay->item_id)) return false;
                return trim((string)$item->listing_id) == trim((string)$ebay->item_id);
            });

            if (!$matchedGeneralL30 && !$matchedGeneralL7) {
                continue;
            }

            $row = [];

            $row['parent'] = $parent;
            $row['sku'] = $pm->sku;
            $row['INV'] = $shopify->inv ?? 0;
            $row['L30'] = $shopify->quantity ?? 0;
            $row['e_l30'] = $ebay->ebay_l30 ?? 0;

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

            $row['SPEND_L30'] = $row['pmt_spend_L30'];
            $row['SPEND_L7'] = $row['pmt_spend_L7'];
            $row['SALES_L30'] = $row['pmt_sales_L30'];
            $row['SALES_L7'] = $row['pmt_sales_L7'];
            $row['SOLD_L30'] = $row['pmt_sold_L30'];
            $row['SOLD_L7'] = $row['pmt_sold_L7'];
            $row['CLICKS_L30'] = $row['pmt_clicks_L30'];
            $row['CLICKS_L7'] = $row['pmt_clicks_L7'];
            $row['IMP_L30'] = $row['pmt_impr_L30'];
            $row['IMP_L7'] = $row['pmt_impr_L7'];

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

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }
}
