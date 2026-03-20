<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ShopifySku;
use Illuminate\Http\Request;

class ReturnAnalysisController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function getViewReturnAnalysisData(Request $request)
    {
        $response = $this->apiController->fetchDataFromProductMasterGoogleSheet();

        // Check if the response is successful
        if ($response->getStatusCode() === 200) {
            $data = $response->getData(); // Get the JSON data from the response
        
            // Get all non-PARENT SKUs from the data to fetch from ShopifySku model
            $skus = collect($data->data)
                ->filter(function ($item) {
                    $childSku = $item->{'SKU'} ?? '';
                    return !empty($childSku) && stripos($childSku, 'PARENT') === false;
                })
                ->pluck('SKU')
                ->unique()
                ->toArray();
        
            // Fetch Shopify inventory data for non-PARENT SKUs
            $shopifyData = ShopifySku::whereIn('sku', $skus)
                ->get()
                ->keyBy('sku');
        
            // Filter out rows where both Parent and (Child) sku are empty and process data
            $filteredData = array_filter($data->data, function ($item) {
                $parent = $item->Parent ?? '';
                $childSku = $item->{'SKU'} ?? '';
        
                // Keep the row if either Parent or (Child) sku is not empty
                return !(empty(trim($parent)) && empty(trim($childSku)));
            });
        
            // Process the data to include Shopify inventory values
            $processedData = array_map(function ($item) use ($shopifyData) {
                $childSku = $item->{'SKU'} ?? '';
        
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

    /**
     * Handle dynamic route parameters and return a view.
     */

    public function returnAnalysis(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        return view('product-master.returnAnalysis', [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

   

}
