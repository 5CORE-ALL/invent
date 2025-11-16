<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\ShopifySku;
use App\Models\ProductMaster; // Add this at the top with other use statements
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OverallAmazonFbaController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
    public function overallAmazonFBA(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // 5. Success Response
        return view('market-places.overallAmazonFba', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }



    public function getViewAmazonfbaData(Request $request)
    {
        // Fetch data from the Google Sheet using the ApiController method
        $response = $this->apiController->fetchDataFromAmazonFBAGoogleSheet();

        // Check if the response is successful
        if ($response->getStatusCode() === 200) {
            $data = $response->getData();

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

            $amazonDataViews = AmazonDataView::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

            // Filter out rows where both Parent and (Child) sku are empty and process data
            $filteredData = array_filter($data->data, function ($item) {
                $parent = $item->Parent ?? '';
                $childSku = $item->{'(Child) sku'} ?? '';
                return !(empty(trim($parent)) && empty(trim($childSku)));
            });

            // Process the data to include Shopify inventory values and image path
            $processedData = array_map(function ($item) use ($shopifyData, $amazonDataViews) {
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

                    // Fetch image path from product_master table
                    $product = ProductMaster::where('sku', $childSku)->first();
                    $imageSrc = $shopifyData[$childSku]->image_src ?? null;
                    $imagePath = $product->image_path ?? null;
                    $item->image_path = $imageSrc ?? $imagePath;

                    $dataView = $amazonDataViews[strtoupper(trim($childSku))] ?? null;
                    $value = $dataView ? $dataView->value : [];

                    // ✅ Key renamed here
                    $item->NRL_REQ_FBA = $value['NRL_REQ_FBA'] ?? 'REQ FBA';

                    if ($item->NRL_REQ_FBA !== 'REQ FBA' && $item->NRL_REQ_FBA !== 'NRL FBA') {
                        $item->NRL_REQ_FBA = 'REQ FBA';
                    }
                } else {
                    // For PARENT SKUs or when childSku is empty, keep original values
                    $item->image_path = null;
                }

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
            Log::error('Failed to fetch data from Google Sheet', ['status' => $response->getStatusCode()]);
            return response()->json([
                'message' => 'Failed to fetch data from Google Sheet',
                'status' => $response->getStatusCode()
            ], $response->getStatusCode());
        }
    }

    public function updateAllAmazonfbaSkus(Request $request)
    {
        try {
            // Validate CSRF token
            if (!hash_equals($request->session()->token(), $request->input('_token'))) {
                return response()->json([
                    'message' => 'CSRF token mismatch',
                    'status' => 419
                ], 419);
            }

            // 1. Fetch data from Shopify
            $shopifySkus = ShopifySku::all()->keyBy('sku');

            // 2. Fetch current data from Amazon Google Sheet
            $response = $this->apiController->fetchDataFromAmazonFBAGoogleSheet();

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to fetch data from Google Sheet');
            }

            $sheetData = $response->getData()->data;

            // 3. Prepare updates - only for non-PARENT SKUs
            $updates = [];
            foreach ($sheetData as $item) {
                $childSku = $item->{'(Child) sku'} ?? '';

                // Skip if this is a PARENT SKU or empty SKU
                if (empty($childSku) || stripos($childSku, 'PARENT') !== false) {
                    continue;
                }

                // Check if SKU exists in Shopify
                if ($shopifySkus->has($childSku)) {
                    $inv = $shopifySkus[$childSku]->inv;
                    $l30 = $shopifySkus[$childSku]->quantity;
                } else {
                    // Set to 0 if SKU not found
                    $inv = 0;
                    $l30 = 0;
                }

                $updates[] = [
                    'sku' => $childSku,
                    'INV' => $inv,
                    'L30' => $l30
                ];
            }

            // 4. Send updates to Google Sheet in batches (to avoid timeout)
            $batchSize = 100;
            $totalUpdated = 0;
            $batches = array_chunk($updates, $batchSize);

            foreach ($batches as $batch) {
                $postData = [
                    'action' => 'update_inv_l30',
                    'updates' => $batch
                ];

                $url = 'https://script.google.com/macros/s/AKfycbzWwqRpTmb8eq0Vp05kP63r02smPIWGsTdcNozqIH0kERoLWuhtTcrsSv4KEub8oeoLNw/exec';
                $response = Http::timeout(120)->post($url, $postData);

                if (!$response->successful()) {
                    throw new \Exception('Failed to update batch: ' . $response->body());
                }

                $totalUpdated += count($batch);
            }

            return response()->json([
                'message' => 'Successfully updated ' . $totalUpdated . ' SKUs',
                'status' => 200,
                'total_updated' => $totalUpdated
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating all Amazon SKUs: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating SKUs: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}
