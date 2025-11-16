<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\AmazonSpCampaignReport;
use App\Models\Ebay2GeneralReport;
use App\Models\Ebay2Metric;
use App\Models\Ebay3GeneralReport;
use App\Models\Ebay3Metric;
use App\Models\EbayGeneralReport;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuMetric;
use Illuminate\Http\Request;

class TrafficMasterController extends Controller
{
    public function index(){
        return view('channels.traffic-master');
    }

    public function fetchTraficReport()
    {
        $productMasterRows = ProductMaster::all()->keyBy('sku');
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Shopify data
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Amazon Campaign data
        $amazonData = AmazonSpCampaignReport::where(function ($query) use ($skus) {
            foreach ($skus as $sku) {
                $query->orWhere('campaignName', 'LIKE', "%{$sku}%");
            }
        })->get();

        $amazonGrouped = [];
        foreach ($amazonData as $row) {
            foreach ($skus as $sku) {
                if (stripos($row->campaignName, $sku) !== false) {
                    if (!isset($amazonGrouped[$sku])) {
                        $amazonGrouped[$sku] = ['impressions' => 0, 'clicks' => 0];
                    }
                    $amazonGrouped[$sku]['impressions'] += $row->impressions ?? 0;
                    $amazonGrouped[$sku]['clicks'] += $row->clicks ?? 0;
                }
            }
        }

        // Temu data
        $temuData = TemuMetric::whereIn('sku', $skus)->get()->keyBy('sku');

        // ===== eBay 1 =====
        $ebayMetricData = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayListingIds = $ebayMetricData->pluck('item_id')->toArray();
        $ebayGeneralData = EbayGeneralReport::whereIn('listing_id', $ebayListingIds)
            ->where('report_range', 'L30')
            ->get()
            ->keyBy('listing_id');

        // ===== eBay 2 =====
        $ebay2MetricData = Ebay2Metric::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebay2ListingIds = $ebay2MetricData->pluck('item_id')->toArray();
        $ebay2GeneralData = Ebay2GeneralReport::whereIn('listing_id', $ebay2ListingIds)
            ->where('report_range', 'L30')
            ->get()
            ->keyBy('listing_id');

        // ===== eBay 3 =====
        $ebay3MetricData = Ebay3Metric::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebay3ListingIds = $ebay3MetricData->pluck('item_id')->toArray();
        $ebay3GeneralData = Ebay3GeneralReport::whereIn('listing_id', $ebay3ListingIds)
            ->where('report_range', 'L30')
            ->get()
            ->keyBy('listing_id');

        // Process Data
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Amazon metrics
            $amazonImpressions = $amazonGrouped[$sku]['impressions'] ?? 0;
            $amazonClicks = $amazonGrouped[$sku]['clicks'] ?? 0;

            // Temu metrics
            $temuImpressions = $temuData[$sku]->product_impressions_l30 ?? 0;
            $temuClicks = $temuData[$sku]->product_clicks_l30 ?? 0;

            // eBay 1 metrics
            $ebayImpressions = 0;
            $ebayClicks = 0;
            if (isset($ebayMetricData[$sku])) {
                $itemId = $ebayMetricData[$sku]->item_id;
                if (isset($ebayGeneralData[$itemId])) {
                    $ebayImpressions = $ebayGeneralData[$itemId]->impressions ?? 0;
                    $ebayClicks = $ebayGeneralData[$itemId]->clicks ?? 0;
                }
            }

            // eBay 2 metrics
            $ebay2Impressions = 0;
            $ebay2Clicks = 0;
            if (isset($ebay2MetricData[$sku])) {
                $itemId = $ebay2MetricData[$sku]->item_id;
                if (isset($ebay2GeneralData[$itemId])) {
                    $ebay2Impressions = $ebay2GeneralData[$itemId]->impressions ?? 0;
                    $ebay2Clicks = $ebay2GeneralData[$itemId]->clicks ?? 0;
                }
            }

            // eBay 3 metrics
            $ebay3Impressions = 0;
            $ebay3Clicks = 0;
            if (isset($ebay3MetricData[$sku])) {
                $itemId = $ebay3MetricData[$sku]->item_id;
                if (isset($ebay3GeneralData[$itemId])) {
                    $ebay3Impressions = $ebay3GeneralData[$itemId]->impressions ?? 0;
                    $ebay3Clicks = $ebay3GeneralData[$itemId]->clicks ?? 0;
                }
            }

            // Sum all sources
            $totalImpressions = $amazonImpressions + $temuImpressions + $ebayImpressions + $ebay2Impressions + $ebay3Impressions;
            $totalClicks = $amazonClicks + $temuClicks + $ebayClicks + $ebay2Clicks + $ebay3Clicks;

            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'is_parent' => $isParent,
                'INV' => $shopifyData[$sku]->inv ?? 0,
                'L30' => $shopifyData[$sku]->quantity ?? 0,
                'Impressions' => $totalImpressions,
                'Clicks' => $totalClicks,
            ];
            $processedItem['details'] = [
                'Amazon' => [
                    'impressions' => $amazonImpressions,
                    'clicks' => $amazonClicks
                ],
                'Temu' => [
                    'impressions' => $temuImpressions,
                    'clicks' => $temuClicks
                ],
                'Ebay1' => [
                    'impressions' => $ebayImpressions,
                    'clicks' => $ebayClicks
                ],
                'Ebay2' => [
                    'impressions' => $ebay2Impressions,
                    'clicks' => $ebay2Clicks
                ],
                'Ebay3' => [
                    'impressions' => $ebay3Impressions,
                    'clicks' => $ebay3Clicks
                ],
            ];

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200
        ]);
    }



}
