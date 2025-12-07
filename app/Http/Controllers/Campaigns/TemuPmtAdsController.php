<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\TemuMetric;
use App\Models\TemuDataView;
use App\Models\TemuListingStatus;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemuPmtAdsController extends Controller
{
    public function index()
    {
        $marketplaceData = MarketplacePercentage::where("marketplace", "Temu")->first();
        $temuPercentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $temuAdPercentage = $marketplaceData ? $marketplaceData->ad_updates : 100;

        // Get chart data for last 30 days
        $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);

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
            
            // Placeholder values - can be populated with actual Temu metrics
            $clicks[] = 0;
            $spend[] = 0;
            $adSales[] = 0;
            $adSold[] = 0;
            $acos[] = 0;
            $cvr[] = 0;
        }

        return view('campaign.temu.pmt-ads', compact('temuPercentage', 'temuAdPercentage', 'dates', 'clicks', 'spend', 'adSales', 'adSold', 'acos', 'cvr'));
    }

    public function getTemuPmtAdsData()
    {
        $productMasters = ProductMaster::orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        $skus = $productMasters->pluck("sku")->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::whereIn("sku", $skus)->get()->keyBy("sku");
        $temuMetrics = TemuMetric::whereIn("sku", $skus)->get();
        
        // Normalize SKUs by replacing non-breaking spaces with regular spaces for matching
        $temuMetricsNormalized = $temuMetrics->mapWithKeys(function($item) {
            $normalizedSku = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $item->sku);
            return [$normalizedSku => $item];
        });
        
        $matchedSkus = $temuMetricsNormalized->keys()->all();
        $productMasters = $productMasters->whereIn('sku', $matchedSkus)->values();

        $nrValues = TemuListingStatus::whereIn("sku", $matchedSkus)->pluck("value", "sku");

        $adMetricsBySku = [];

        foreach ($temuMetricsNormalized as $sku => $metric) {
            // Process L30 metrics
            $adMetricsBySku[$sku]['L30']['Imp'] = (int) ($metric->product_impressions_l30 ?? 0);
            $adMetricsBySku[$sku]['L30']['Clk'] = (int) ($metric->product_clicks_l30 ?? 0);
            $adMetricsBySku[$sku]['L30']['QuantityPurchased'] = (int) ($metric->quantity_purchased_l30 ?? 0);
            
            // Process L60 metrics
            $adMetricsBySku[$sku]['L60']['Imp'] = (int) ($metric->product_impressions_l60 ?? 0);
            $adMetricsBySku[$sku]['L60']['Clk'] = (int) ($metric->product_clicks_l60 ?? 0);
            $adMetricsBySku[$sku]['L60']['QuantityPurchased'] = (int) ($metric->quantity_purchased_l60 ?? 0);
        }

        $data = [];

        foreach ($productMasters as $product) {
            $sku = $product->sku;
            $shopify = $shopifyData->get($sku);
            $metric = $temuMetricsNormalized->get($sku);

            $row = [
                'sku' => $sku,
                'parent' => $product->parent,
                'title' => $shopify ? $shopify->title : '',
                'image' => $shopify ? $shopify->image : '',
                'goods_id' => $metric ? $metric->goods_id : '',
                'base_price' => $metric ? $metric->base_price : '',
                'sheet_price' => $metric ? $metric->temu_sheet_price : '',
                'nr_value' => $nrValues->get($sku, ''),
            ];

            // Add L30 metrics
            $l30 = $adMetricsBySku[$sku]['L30'] ?? [];
            $row['l30_impressions'] = $l30['Imp'] ?? 0;
            $row['l30_clicks'] = $l30['Clk'] ?? 0;
            $row['l30_quantity'] = $l30['QuantityPurchased'] ?? 0;
            $row['l30_ctr'] = $row['l30_impressions'] > 0 
                ? round(($row['l30_clicks'] / $row['l30_impressions']) * 100, 2) 
                : 0;
            $row['l30_cvr'] = $row['l30_clicks'] > 0 
                ? round(($row['l30_quantity'] / $row['l30_clicks']) * 100, 2) 
                : 0;

            // Add L60 metrics
            $l60 = $adMetricsBySku[$sku]['L60'] ?? [];
            $row['l60_impressions'] = $l60['Imp'] ?? 0;
            $row['l60_clicks'] = $l60['Clk'] ?? 0;
            $row['l60_quantity'] = $l60['QuantityPurchased'] ?? 0;
            $row['l60_ctr'] = $row['l60_impressions'] > 0 
                ? round(($row['l60_clicks'] / $row['l60_impressions']) * 100, 2) 
                : 0;
            $row['l60_cvr'] = $row['l60_clicks'] > 0 
                ? round(($row['l60_quantity'] / $row['l60_clicks']) * 100, 2) 
                : 0;

            // Add L7 metrics (calculated from L30)
            $row['l7_impressions'] = 0;
            $row['l7_clicks'] = 0;
            $row['l7_quantity'] = 0;
            $row['l7_ctr'] = 0;
            $row['l7_cvr'] = 0;

            $data[] = $row;
        }

        return response()->json(['data' => $data]);
    }

    public function updateTemuPmtAds(Request $request)
    {
        $marketplaceData = MarketplacePercentage::where("marketplace", "Temu")->first();
        $temuAdPercentage = $marketplaceData ? $marketplaceData->ad_updates : 100;

        $skus = $request->input('skus', []);
        $updates = [];

        foreach ($skus as $sku) {
            // Add update logic here based on business requirements
            // This is a placeholder for the update functionality
            $updates[] = [
                'sku' => $sku,
                'status' => 'updated'
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Temu PMT Ads updated successfully',
            'updates' => $updates
        ]);
    }

    private function extractNumber($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        
        $cleaned = preg_replace('/[^0-9.-]/', '', str_replace(',', '', $value));
        return $cleaned !== '' ? (float) $cleaned : 0;
    }
}
