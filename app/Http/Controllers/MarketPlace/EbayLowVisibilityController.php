<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EbayLowVisibilityController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function ebayLowVisibility(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('ebay_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'eBay')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.ebayLowVisibilityView', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

    public function getVieweBayLowVisibilityData(Request $request)
    {
        // 1. Fetch all ProductMaster rows (base)
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch data from the Google Sheet using the ApiController method
        // Prepare SKU list for related models
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        // Fetch related data
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $ebayMetrics = EbayMetric::whereIn('sku', $skus)->get()->keyBy('sku');
        // Fetch all EbayDataView rows for these SKUs
        $ebayDataViews = EbayDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // Build the result using ProductMaster as the base
        $processedData = [];
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $parentSku = $pm->parent;
            $imagePath = null;

            // Try to get image from Shopify first
            $shopify = $shopifyData[$sku] ?? null;
            if ($shopify && !empty($shopify->image_src)) {
                $imagePath = $shopify->image_src;
            } else {
                // Try to get image_path from ProductMaster->Values (if it's a JSON array)
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (isset($values['image_path'])) {
                    $imagePath = $values['image_path'];
                } elseif (isset($pm->image_path)) {
                    $imagePath = $pm->image_path;
                }
            }
            $buyerLink = $item->{'B Link'} ?? null;
            $sellerLink = $item->{'S Link'} ?? null;
            // Create base item object
            $item = (object) [
                'Parent' => $parentSku,
                '(Child) sku' => $sku,
                'B Link' => null,
                'S Link' => null,
                'INV' => $shopify ? $shopify->inv : 0,
                'L30' => $shopify ? $shopify->quantity : 0,
                'E L30' => 0,
                'eBay L60' => 0,
                'eBay Price' => 0,
                'OV CLICKS L30' => 0,
                'image' => $imagePath,
                'A_Z_Reason' => '',
                'A_Z_ActionRequired' => '',
                'A_Z_ActionTaken' => '',
                'NR' => 'REQ',
                'B Link' => (filter_var($buyerLink, FILTER_VALIDATE_URL)) ? $buyerLink : null,
                'S Link' => (filter_var($sellerLink, FILTER_VALIDATE_URL)) ? $sellerLink : null,
            ];

            // eBay metrics
            if ($ebayMetrics->has($sku)) {
                $ebayMetric = $ebayMetrics[$sku];
                $item->{'E L30'} = $ebayMetric->ebay_l30;
                $item->{'eBay L60'} = $ebayMetric->ebay_l60;
                $item->{'eBay Price'} = $ebayMetric->ebay_price;
                $inv = $shopify->inv ?? 0;
                $eBayL30 = $item->{'E L30'} ?? 0;

                $item->{'E Dil%'} = ($inv > 0) ? round($eBayL30 / $inv, 2) : 0;
            }

            // EbayDataView
            $dataView = $ebayDataViews->get($sku);
            $value = $dataView ? $dataView->value : [];
            $item->{'A_Z_Reason'} = $value['A_Z_Reason'] ?? '';
            $item->{'A_Z_ActionRequired'} = $value['A_Z_ActionRequired'] ?? '';
            $item->{'A_Z_ActionTaken'} = $value['A_Z_ActionTaken'] ?? '';
            $item->{'NR'} = $value['NR'] ?? 'REQ';

            $processedData[] = $item;
        }

        // Apply additional filters
        $filteredResults = array_filter($processedData, function ($item) {
            $childSku = $item->{'(Child) sku'} ?? '';
            $inv = $item->INV ?? 0;
            $ovClicksL30 = $item->{'OV CLICKS L30'} ?? 1;

            return stripos($childSku, 'PARENT') === false && $inv > 0 && $ovClicksL30 == 0;
        });

        // Return the processed data
        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => array_values($filteredResults),
            'status' => 200
        ]);
    }

    public function updateReasonAction(Request $request)
    {
        $sku = $request->input('sku');
        $reason = $request->input('reason');
        $actionRequired = $request->input('action_required');
        $actionTaken = $request->input('action_taken');

        if (!$sku) {
            return response()->json([
                'status' => 400,
                'message' => 'SKU is required.'
            ], 400);
        }

        $row = EbayDataView::firstOrCreate(['sku' => $sku]);
        $value = $row->value ?? [];
        $value['A_Z_Reason'] = $reason;
        $value['A_Z_ActionRequired'] = $actionRequired;
        $value['A_Z_ActionTaken'] = $actionTaken;
        $row->value = $value;
        $row->save();

        return response()->json([
            'status' => 200,
            'message' => 'Reason and actions updated successfully.'
        ]);
    }
}