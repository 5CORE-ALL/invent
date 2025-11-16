<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ReverbProduct;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\ReverbViewData;
use Illuminate\Support\Facades\Cache;

class ReverbLowVisibilityController extends Controller
{
    public function reverbLowVisibilityview(Request $request)
    {
        $mode = $request->input('mode', '');
        $demo = $request->input('demo', '');

        return view('market-places.reverbLowVisbilityView', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }
        
    public function getViewReverbLowVisibilityData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('reverb_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Reverb')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all product master records
        $productMasterRows = ProductMaster::all()->keyBy('sku');

        // Get all unique SKUs from product master
        $skus = $productMasterRows->pluck('sku')->toArray();

        // Fetch shopify data for these SKUs
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch reverb data for these SKUs
        $reverbData = ReverbProduct::whereIn('sku', $skus)->get()->keyBy('sku');

        // Fetch bump, S bump, s price from reverb_view_data
        $reverbViewData = ReverbViewData::whereIn('sku', $skus)->get()->keyBy('sku');

        // Process data from product master and shopify tables
        $processedData = [];
        $slNo = 1;

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $isParent = stripos($sku, 'PARENT') !== false;

            // Initialize the data structure
            $processedItem = [
                'SL No.' => $slNo++,
                'Parent' => $productMaster->parent ?? null,
                'Sku' => $sku,
                'R&A' => false, // Default value, can be updated as needed
                'is_parent' => $isParent,
                'raw_data' => [
                    'parent' => $productMaster->parent,
                    'sku' => $sku,
                    'Values' => $productMaster->Values
                ]
            ];

            // Add values from product_master
            $values = $productMaster->Values ?: [];
            $processedItem['LP'] = $values['lp'] ?? 0;
            $processedItem['Ship'] = $values['ship'] ?? 0;
            $processedItem['COGS'] = $values['cogs'] ?? 0;

            // Add data from shopify_skus if available
            if (isset($shopifyData[$sku])) {
                $shopifyItem = $shopifyData[$sku];
                $processedItem['INV'] = $shopifyItem->inv ?? 0;
                $processedItem['L30'] = $shopifyItem->quantity ?? 0;
            } else {
                $processedItem['INV'] = 0;
                $processedItem['L30'] = 0;
            }

            // Add data from reverb_products if available
            if (isset($reverbData[$sku])) {
                $reverbItem = $reverbData[$sku];
                $reverbPrice = $reverbItem->price ?? 0;
                $ship = $values['ship'] ?? 0;

                $processedItem['price'] = ($reverbPrice > 0) ? ($reverbPrice + $ship) : 0;
                $processedItem['price_wo_ship'] = $reverbPrice;
                $processedItem['views'] = $reverbItem->views ?? 0;
                $processedItem['r_l30'] = $reverbItem->r_l30 ?? 0;
                $processedItem['r_l60'] = $reverbItem->r_l60 ?? 0;
            } else {
                $processedItem['price'] = 0;
                $processedItem['price_wo_ship'] = 0;
                $processedItem['views'] = 0;
                $processedItem['r_l30'] = 0;
                $processedItem['r_l60'] = 0;
            }

            // Add bump, S bump, s price from reverb_view_data if available
            if (isset($reverbViewData[$sku])) {
                $viewData = $reverbViewData[$sku];
                $valuesArr = $viewData->values ?: [];

                // Use consistent field names (all lowercase or all uppercase)
                $processedItem['Bump'] = $valuesArr['bump'] ?? null;
                $processedItem['s bump'] = $valuesArr['s_bump'] ?? null;
                $processedItem['S_price'] = $valuesArr['s_price'] ?? null;
                $processedItem['R&A'] = $valuesArr['R&A'] ?? false;

                // Fetch Reason/Action columns from ReverbViewData
                $processedItem['A_Z_Reason'] = $valuesArr['A_Z_Reason'] ?? '';
                $processedItem['A_Z_ActionRequired'] = $valuesArr['A_Z_ActionRequired'] ?? '';
                $processedItem['A_Z_ActionTaken'] = $valuesArr['A_Z_ActionTaken'] ?? '';
            } else {
                $processedItem['Bump'] = null;
                $processedItem['s bump'] = null;
                $processedItem['S_price'] = null;
                $processedItem['R&A'] = false;
                // Default Reason/Action columns
                $processedItem['A_Z_Reason'] = '';
                $processedItem['A_Z_ActionRequired'] = '';
                $processedItem['A_Z_ActionTaken'] = '';
            }

            // Default values for other fields
            $processedItem['A L30'] = 0;
            $processedItem['Sess30'] = 0;
            $processedItem['TOTAL PFT'] = 0;
            $processedItem['T Sales L30'] = 0;
            $processedItem['percentage'] = $percentageValue;

            $price = floatval($processedItem['price']);
            $percentage = floatval($processedItem['percentage']);
            $lp = floatval($processedItem['LP']);
            $ship = floatval($processedItem['Ship']);

            if ($price > 0) {
                $pft_percentage = (($price * $percentage) - $lp - $ship) / $price * 100;
                $processedItem['PFT_percentage'] = round($pft_percentage, 2);
            } else {
                $processedItem['PFT_percentage'] = 0;
            }

            if ($lp > 0) {
                $roi_percentage = (($price * $percentage) - $lp - $ship) / $lp * 100;
                $processedItem['ROI_percentage'] = round($roi_percentage, 2);
            } else {
                $processedItem['ROI_percentage'] = 0;
            }

            // Add image like Amazon (from ShopifySku or ProductMaster->Values)
            $processedItem['image_path'] = $shopifyData[$sku]->image_src ?? ($values['image_path'] ?? null);

            // Filter for views between 1 and 100
            if ($processedItem['views'] >= 1 && $processedItem['views'] <= 100) {
                $processedData[] = $processedItem;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
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

        $row = ReverbViewData::firstOrCreate(['sku' => $sku]);
        $values = $row->values ?? [];
        $values['A_Z_Reason'] = $reason;
        $values['A_Z_ActionRequired'] = $actionRequired;
        $values['A_Z_ActionTaken'] = $actionTaken;
        $row->values = $values; // <-- use 'values' not 'value'
        $row->save();

        return response()->json([
            'status' => 200,
            'message' => 'Reason and actions updated successfully.'
        ]);
    }
}
