<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\Neweegb2cDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class Neweggb2cZeroController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
    public function neweggB2CZeroView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('neweggb2c_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Neweggb2c')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.neweggb2cZeroView', [
            'mode' => $mode,
            'demo' => $demo,
            'neweggb2cPercentage' => $percentage

        ]);
    }

    public function getViewNeweggB2CZeroData(Request $request)
    {
        // 1. Fetch base product master data (Parent & SKU)
        $productMaster = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // 2. Get all SKUs from product master
        $allSkus = $productMaster->pluck('sku')->unique()->toArray();

        // 3. Fetch data from the Google Sheet using the ApiController method
        $response = $this->apiController->fetchDataFromNeweggB2CMasterGoogleSheet();

        // 4. Prepare Google Sheet data indexed by SKU for fast lookup
        $sheetData = [];
        if ($response->getStatusCode() === 200) {
            $data = $response->getData();
            foreach ($data->data as $item) {
                $childSku = $item->{'(Child) sku'} ?? '';
                if ($childSku) {
                    $sheetData[$childSku] = $item;
                }
            }
        }

        // 5. Fetch ShopifySku data for all SKUs
        $shopifyData = ShopifySku::whereIn('sku', $allSkus)->get()->keyBy('sku');

        // 6. Fetch Neweegb2cDataView NR and A_Z fields for all SKUs
        $neweggDataViews = Neweegb2cDataView::whereIn('sku', $allSkus)->pluck('value', 'sku');

        // 7. Build the final data array
        $finalData = [];
        foreach ($productMaster as $row) {
            $sku = $row->sku;
            $parent = $row->Parent;

            // Start with product master base
            $item = [
                'Parent' => $parent,
                'sku' => $sku,
            ];

            // Merge Google Sheet data if exists
            if (isset($sheetData[$sku])) {
                foreach ((array)$sheetData[$sku] as $key => $val) {
                    $item[$key] = $val;
                }
            }

            // Merge ShopifySku data
            $item['INV'] = $shopifyData->has($sku) ? $shopifyData[$sku]->inv : 0;
            $item['L30'] = $shopifyData->has($sku) ? $shopifyData[$sku]->quantity : 0;

            // Merge NR and A_Z fields from Neweegb2cDataView
            $item['NR'] = false;
            $item['A_Z_Reason'] = null;
            $item['A_Z_ActionRequired'] = null;
            $item['A_Z_ActionTaken'] = null;
            if (isset($neweggDataViews[$sku])) {
                $val = $neweggDataViews[$sku];
                if (is_array($val)) {
                    $item['NR'] = $val['NR'] ?? false;
                    $item['A_Z_Reason'] = $val['A_Z_Reason'] ?? null;
                    $item['A_Z_ActionRequired'] = $val['A_Z_ActionRequired'] ?? null;
                    $item['A_Z_ActionTaken'] = $val['A_Z_ActionTaken'] ?? null;
                } else {
                    $decoded = json_decode($val, true);
                    $item['NR'] = $decoded['NR'] ?? false;
                    $item['A_Z_Reason'] = $decoded['A_Z_Reason'] ?? null;
                    $item['A_Z_ActionRequired'] = $decoded['A_Z_ActionRequired'] ?? null;
                    $item['A_Z_ActionTaken'] = $decoded['A_Z_ActionTaken'] ?? null;
                }
            }

            // Only include if INV > 0 and SESS L30 == 0
            $inv = (int)($item['INV'] ?? 0);
            $sessL30 = isset($item['SESS L30']) ? (int)$item['SESS L30'] : 0;
            if ($inv > 0 && $sessL30 == 0) {
                $finalData[] = (object)$item;
            }
        }

        // 8. Return the final data as JSON
        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $finalData,
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

        $row = Neweegb2cDataView::firstOrCreate(['sku' => $sku]);
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

    public function getZeroViewCount()
    {
        // 1. Fetch base product master data (Parent & SKU)
        $productMaster = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // 2. Get all SKUs from product master
        $allSkus = $productMaster->pluck('sku')->unique()->toArray();

        // 3. Fetch data from the Google Sheet using the ApiController method
        $response = $this->apiController->fetchDataFromNeweggB2CMasterGoogleSheet();

        // 4. Prepare Google Sheet data indexed by SKU for fast lookup
        $sheetData = [];
        if ($response->getStatusCode() === 200) {
            $data = $response->getData();
            foreach ($data->data as $item) {
                $childSku = $item->{'(Child) sku'} ?? '';
                if ($childSku) {
                    $sheetData[$childSku] = $item;
                }
            }
        }

        // 5. Fetch ShopifySku data for all SKUs
        $shopifyData = ShopifySku::whereIn('sku', $allSkus)->get()->keyBy('sku');

        // 6. Only include if INV > 0 and SESS L30 == 0
        $zeroViewCount = 0;
        foreach ($productMaster as $row) {
            $sku = $row->sku;
            $inv = $shopifyData->has($sku) ? $shopifyData[$sku]->inv : 0;
            $sessL30 = isset($sheetData[$sku]) ? (int)($sheetData[$sku]->{'SESS L30'} ?? 0) : 0;
            if ($inv > 0 && $sessL30 == 0) {
                $zeroViewCount++;
            }
        }

        return $zeroViewCount;
    }
}