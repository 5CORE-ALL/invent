<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\MacyDataView;
use App\Models\MacyProduct;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class MacyLowVisibilityController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
    public function macyLowVisibilityView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('macys_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.macysLowVisibilityView', [
            'mode' => $mode,
            'demo' => $demo,
            'macysPercentage' => $percentage
        ]);
    }
    public function getViewMacyLowVisibilityData(Request $request)
    {
        // Fetch all ProductMaster records
        $productMasters = ProductMaster::all();
        $skus = $productMasters->pluck('sku')->toArray();

        // Fetch ShopifySku and MacyProduct records for those SKUs
        $shopifySkus = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $macyProducts = MacyProduct::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch MacyDataView NR values for those SKUs
        $macyDataViews = MacyDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch data from the Google Sheet using the ApiController method
        $sheetResponse = $this->apiController->fetchMacyListingData();
        $sheetData = [];
        if ($sheetResponse->getStatusCode() === 200) {
            $sheetRaw = $sheetResponse->getData();
            $sheetData = $sheetRaw->data ?? [];
        }

        // Map ProductMaster SKUs to sheet data (by (Child) sku)
        $sheetSkuMap = collect($sheetData)
            ->filter(function ($item) {
                return !empty($item->{'(Child) sku'} ?? null);
            })
            ->keyBy(function ($item) {
                return $item->{'(Child) sku'};
            });

        // Attach matching ShopifySku, MacyProduct, Sheet data, and NR to each ProductMaster record
        $processedData = $productMasters->map(function ($product) use ($shopifySkus, $macyProducts, $sheetSkuMap, $macyDataViews) {
            $sku = $product->sku;
            $product->INV = $shopifySkus->has($sku) ? $shopifySkus[$sku]->inv : 0;
            $product->L30 = $shopifySkus->has($sku) ? $shopifySkus[$sku]->quantity : 0;
            $product->m_l30 = $macyProducts->has($sku) ? $macyProducts[$sku]->m_l30 : null;
            $product->m_l60 = $macyProducts->has($sku) ? $macyProducts[$sku]->m_l60 : null;
            $product->price = $macyProducts->has($sku) ? $macyProducts[$sku]->price : null;
            $product->sheet_data = $sheetSkuMap->has($sku) ? $sheetSkuMap[$sku] : null;

            // Fetch NR and A_Z fields from MacyDataView
            $nrValue = null;
            $a_z_reason = null;
            $a_z_action_required = null;
            $a_z_action_taken = null;
            if ($macyDataViews->has($sku)) {
                $value = $macyDataViews[$sku]->value;
                if (is_array($value)) {
                    $nrValue = $value['NR'] ?? null;
                    $a_z_reason = $value['A_Z_Reason'] ?? null;
                    $a_z_action_required = $value['A_Z_ActionRequired'] ?? null;
                    $a_z_action_taken = $value['A_Z_ActionTaken'] ?? null;
                } else {
                    $decoded = json_decode($value, true);
                    $nrValue = $decoded['NR'] ?? null;
                    $a_z_reason = $decoded['A_Z_Reason'] ?? null;
                    $a_z_action_required = $decoded['A_Z_ActionRequired'] ?? null;
                    $a_z_action_taken = $decoded['A_Z_ActionTaken'] ?? null;
                }
            }
            $product->NR = $nrValue;
            $product->A_Z_Reason = $a_z_reason;
            $product->A_Z_ActionRequired = $a_z_action_required;
            $product->A_Z_ActionTaken = $a_z_action_taken;
            return $product;
        })
        // --- Apply filter: only rows where VIEWS == 1 to 100 ---
        ->filter(function ($product) {
            $views = 0;
            if ($product->sheet_data && isset($product->sheet_data->VIEWS)) {
                $views = (int)$product->sheet_data->VIEWS;
            }
            return $views >= 1 && $views <= 100;
        })
        ->values();

        return response()->json([
            'message' => 'Data fetched successfully',
            'product_master_data' => $processedData,
            'sheet_data' => $sheetData,
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

        $row = MacyDataView::firstOrCreate(['sku' => $sku]);
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