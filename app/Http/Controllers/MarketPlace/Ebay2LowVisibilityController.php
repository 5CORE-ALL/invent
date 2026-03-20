<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\EbayTwoDataView;
use App\Models\Ebay2Metric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class Ebay2LowVisibilityController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function ebay2LowVisibility(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('ebay_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'eBay')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.ebay2LowVisibilityView', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

    public function getVieweBay2LowVisibilityData(Request $request)
    {
        // 1. Fetch all ProductMaster rows (base)
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch data from the Google Sheet using the ApiController method
        $response = $this->apiController->fetchEbayListingData();

        if ($response->getStatusCode() === 200) {
            $sheetData = $response->getData();

            // Index sheet data by SKU for fast lookup
            $sheetSkuMap = [];
            foreach ($sheetData->data as $item) {
                $sku = $item->{'(Child) sku'} ?? '';
                if (!empty($sku)) {
                    $sheetSkuMap[$sku] = $item;
                }
            }

            // Prepare SKU list for related models
            $skus = $productMasters->pluck('sku')->unique()->toArray();

            // Fetch related data
            $shopifyData = ShopifySku::whereIn('sku', $skus)->where('inv', '>', 0)->get()->keyBy('sku');
            $ebayMetrics = Ebay2Metric::whereIn('sku', $skus)->get()->keyBy('sku');
            // Fetch all EbayTwoDataView rows for these SKUs
            $ebayDataViews = EbayTwoDataView::whereIn('sku', $skus)->get()->keyBy('sku');

            // Build the result using ProductMaster as the base
            $processedData = [];
            foreach ($productMasters as $pm) {
                $sku = $pm->sku;
                $parentSku = $pm->parent;
                $imagePath = null;

                // Try to get image from Shopify first
                $shopify = $shopifyData[$sku] ?? null;

                if (!$shopify || $shopify->inv <= 0) {
                    continue;
                }
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

                $item = $sheetSkuMap[$sku] ?? null;

                // If not found in sheet, create a blank object
                if (!$item) {
                    $item = (object) [
                        'Parent' => $parentSku,
                        '(Child) sku' => $sku,
                    ];
                }

                // --- eBay Buyer Link & Seller Link Validation ---
                $buyerLink = $item->{'B Link'} ?? null;
                $sellerLink = $item->{'S Link'} ?? null;
                $item->{'B Link'} = (filter_var($buyerLink, FILTER_VALIDATE_URL)) ? $buyerLink : null;
                $item->{'S Link'} = (filter_var($sellerLink, FILTER_VALIDATE_URL)) ? $sellerLink : null;

                // Shopify data
                $item->INV = $shopifyData->has($sku) ? $shopifyData[$sku]->inv : 0;
                $item->L30 = $shopifyData->has($sku) ? $shopifyData[$sku]->quantity : 0;

                // eBay metrics
                if ($ebayMetrics->has($sku)) {
                    $ebayMetric = $ebayMetrics[$sku];
                    $item->{'eBay L30'} = $ebayMetric->ebay_l30;
                    $item->{'eBay L60'} = $ebayMetric->ebay_l60;
                    $item->{'eBay Price'} = $ebayMetric->ebay_price;
                } else {
                    $item->{'eBay L30'} = 0;
                    $item->{'eBay L60'} = 0;
                    $item->{'eBay Price'} = 0;
                }

                // Ensure OV CLICKS L30 exists (comes from sheet)
                if (!isset($item->{'OV CLICKS L30'})) {
                    $item->{'OV CLICKS L30'} = 0;
                }

                // Add image path
                $item->image = $imagePath;

                // --- Add Reason, ActionRequired, ActionTaken from EbayTwoDataView ---
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
                $ovClicksL30 = $item->{'OV CLICKS L30'} ?? 0; // Default to 0 if not set

                return
                    stripos($childSku, 'PARENT') === false &&
                    $ovClicksL30 >= 1 &&
                    $ovClicksL30 <= 100;
            });

            // Return the processed data with filters applied
            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => array_values($filteredResults),
                'status' => 200
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to fetch data from Google Sheet',
                'status' => $response->getStatusCode()
            ], $response->getStatusCode());
        }
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

        $row = EbayTwoDataView::firstOrCreate(['sku' => $sku]);
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