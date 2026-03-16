<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\Neweegb2cDataView;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class Neweggb2cController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
    public function neweggB2CView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('neweggb2c_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Neweggb2c')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('market-places.neweggb2c', [
            'mode' => $mode,
            'demo' => $demo,
            'neweggb2cPercentage' => $percentage

        ]);
    }

    public function getViewNeweggB2CData(Request $request)
    {
        // Fetch data from the Google Sheet using the ApiController method
        $response = $this->apiController->fetchDataFromNeweggB2CMasterGoogleSheet();

        // Check if the response is successful
        if ($response->getStatusCode() === 200) {
            $data = $response->getData(); // Get the JSON data from the response

            // Get all non-PARENT SKUs from the data to fetch from ShopifySku model
            $skus = collect($data->data)
                ->filter(function ($item) {
                    $childSku = $item->{'(Child) sku'} ?? '';
                    return !empty($childSku) && stripos($childSku, 'PARENT') === false;
                })
                ->pluck('(Child) sku')
                ->unique()
                ->toArray();

            // Fetch Shopify inventory data for non-PARENT SKUs
            $shopifyData = ShopifySku::whereIn('sku', $skus)
                ->get()
                ->keyBy('sku');
            
            // Fetch NR values before processing data
            $nrValues = Neweegb2cDataView::pluck('value', 'sku');


            // Filter out rows where both Parent and (Child) sku are empty and process data
            $filteredData = array_filter($data->data, function ($item) {
                $parent = $item->Parent ?? '';
                $childSku = $item->{'(Child) sku'} ?? '';

                // Keep the row if either Parent or (Child) sku is not empty
                return !(empty(trim($parent)) && empty(trim($childSku)));
            });

            // Process the data to include Shopify inventory values
            $processedData = array_map(function ($item) use ($shopifyData, $nrValues) {
                $childSku = $item->{'(Child) sku'} ?? '';

                // Only update INV and L30 if this is not a PARENT SKU
                if (!empty($childSku) && stripos($childSku, 'PARENT') === false) {
                    if ($shopifyData->has($childSku)) {
                        $item->INV = $shopifyData[$childSku]->inv;
                        $item->L30 = $shopifyData[$childSku]->quantity;
                    } else {
                        // Default to 0 if SKU not found in Shopify
                        $item->INV = 0;
                        $item->L30 = 0;
                    }

                    // NR value
                    $item->NR = 'false';
                    if ($childSku && isset($nrValues[$childSku])) {
                        $val = $nrValues[$childSku];
                        if (is_array($val)) {
                            $item->NR = $val['NR'] ?? 'false';
                        } else {
                            $decoded = json_decode($val, true);
                            $item->NR = $decoded['NR'] ?? 'false';
                        }
                    }
                }
                // For PARENT SKUs or when childSku is empty, keep original values

                return $item;
            }, $filteredData);

            // Re-index the array after filtering
            $processedData = array_values($processedData);

            // Return the filtered data
            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $processedData,
                'status' => 200
            ]);
        } else {
            // Handle the error if the request failed
            return response()->json([
                'message' => 'Failed to fetch data from Google Sheet',
                'status' => $response->getStatusCode()
            ], $response->getStatusCode());
        }
    }

    public function updateAllNeweggB2CSkus(Request $request)
    {
        try {
            $percent = $request->input('percent');

            if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Invalid percentage value. Must be between 0 and 100.'
                ], 400);
            }

            // Update database
            MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'Neweggb2c'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('neweggb2c_marketplace_percentage', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'Neweggb2c',
                    'percentage' => $percent
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating percentage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nr = $request->input('nr');

        if (!$sku || $nr === null) {
            return response()->json(['error' => 'SKU and nr are required.'], 400);
        }

        $dataView = Neweegb2cDataView::firstOrNew(['sku' => $sku]);
        $value = is_array($dataView->value) ? $dataView->value : (json_decode($dataView->value, true) ?: []);
        $value['NR'] = $nr;
        $dataView->value = $value;
        $dataView->save();

        return response()->json(['success' => true, 'data' => $dataView]);
    }
}