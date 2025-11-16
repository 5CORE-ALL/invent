<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Neweegb2cDataView;
use Illuminate\Support\Facades\Log;

class ListingMasterController extends Controller
{
    protected $apiController;

    public function __construct()
    {
        // Assuming you have an API controller class that handles these methods
        $this->apiController = app(\App\Http\Controllers\ApiController::class); // Adjust the namespace as needed
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('marketing-masters.listingMaster');
    }

    // fetch data for listing audit master
    public function getListingMasterData(Request $request)
    {
        $marketplaces = MarketplacePercentage::pluck('marketplace')->toArray();

        $data = array_map(function ($marketplace) {
            // Normalize marketplace name for controller naming and URL convention
            $normalizedName = $marketplace;
            $controllerClass = "\\App\\Http\\Controllers\\MarketPlace\\ListingMarketPlace\\Listing{$normalizedName}Controller";

            // Default values
            $counts = [
                'REQ' => 0,
                'Listed' => 0,
                'Pending' => 0
            ];

            // Build URL slug based on marketplace name
            $urlSlug = 'listing-' . strtolower($marketplace);

            // Try to get counts from the corresponding controller if it exists
            if (class_exists($controllerClass)) {
                try {
                    $marketplaceController = app($controllerClass);
                    if (method_exists($marketplaceController, 'getNrReqCount')) {
                        $response = $marketplaceController->getNrReqCount();
                        // If response is a JsonResponse, decode it
                        if ($response instanceof \Illuminate\Http\JsonResponse) {
                            $counts = $response->getData(true); // true returns associative array
                        } else {
                            $counts = $response;
                        }
                    } else {
                        // \Log::error("Method getNrReqCount not found in controller for {$marketplace} ({$controllerClass})");
                    }
                } catch (\Exception $e) {
                    // \Log::error("Error loading counts for {$marketplace} ({$controllerClass}): " . $e->getMessage());
                }
                // Only log if counts are not fetched (all zero)
                if (($counts['REQ'] ?? 0) === 0 && ($counts['Listed'] ?? 0) === 0 && ($counts['Pending'] ?? 0) === 0) {
                    // \Log::error("Counts not fetched for marketplace: {$marketplace}");
                }
                return [
                    'Channel' => $marketplace,
                    'REQ' => $counts['REQ'] ?? 0,
                    'Listed' => $counts['Listed'] ?? 0,
                    'Pending' => $counts['Pending'] ?? 0,
                    'channel_url' => url($urlSlug),
                ];
            } else {
                // \Log::error("Controller class not found for marketplace: {$marketplace} ({$controllerClass})");
            }
            return [
                'Channel' => $marketplace,
                'REQ' => 0,
                'Listed' => 0,
                'Pending' => 0,
                'channel_url' => null,
            ];
        }, $marketplaces);

        return response()->json([
            'data' => $data,
            'status' => 200
        ]);
    }




    public function getListingMasterCountsViews(Request $request)
    {
        $marketplacesData = $this->getMarketplacesData($request)->getData(true);
        return view('marketing-masters.listingMasterCountsView', [
            'sessions_l30' => $marketplacesData['sessions_l30'],
            'data' => $marketplacesData['data']
        ]);
    }


    public function getMarketplacesData(Request $request)
    {
        // 1. Fetch all ProductMaster SKUs
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();
        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // 2. Fetch Shopify data
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // 3. Fetch Newegg B2C data views for additional fields
        $neweggDataViews = Neweegb2cDataView::whereIn('sku', $skus)->pluck('value', 'sku');

        // 4. Marketplace API methods and their corresponding view URLs
        $marketplacesApi = [
            'Amazon' => [
                'method' => 'fetchDataFromAmazonGoogleSheet',
                'viewUrl' => 'amazon-low-visibility-view'
            ],
            'eBay' => [
                'method' => 'fetchEbayListingData',
                'viewUrl' => 'ebay-low-visibility-view'
            ],
            'NeweggB2C' => [
                'method' => 'fetchDataFromNeweggB2CMasterGoogleSheet',
                'viewUrl' => 'Neweggb2c-low-visibility-view'
            ],
        ];

        $totalSkuCount = 0;
        $finalData = [];

        foreach ($marketplacesApi as $marketplace => $config) {
            $skuList = [];
            $skuCount = 0;

            try {
                $response = $this->apiController->{$config['method']}();
                if ($response->getStatusCode() === 200) {
                    $apiDataArr = $response->getData()->data ?? [];

                    // Index API data by SKU (case-insensitive)
                    $apiDataBySku = [];
                    foreach ($apiDataArr as $item) {
                        $skuKey = strtoupper(trim($item->{'(Child) sku'} ?? ''));
                        if ($skuKey) $apiDataBySku[$skuKey] = $item;
                    }

                    // Build SKU list by matching ProductMaster SKUs
                    foreach ($productMasters as $pm) {
                        $skuKey = strtoupper($pm->sku);
                        if (!isset($apiDataBySku[$skuKey])) continue;

                        $apiItem = $apiDataBySku[$skuKey];

                        // Marketplace-specific low-visibility filter
                        $visible = false;
                        if ($marketplace === 'Amazon') {
                            $sess30 = intval($apiItem->Sess30 ?? 0);
                            $visible = $sess30 >= 1 && $sess30 <= 100;
                        } elseif ($marketplace === 'eBay') {
                            $ovClicks = intval($apiItem->{'OV CLICKS L30'} ?? 0);
                            $visible = $ovClicks >= 1 && $ovClicks <= 100;
                        } elseif ($marketplace === 'NeweggB2C') {
                            $sessL30 = intval($apiItem->{'SESS L30'} ?? 0);
                            $visible = $sessL30 >= 1 && $sessL30 <= 100;
                        }

                        if (!$visible) continue;

                        $shopify = $shopifyData[$pm->sku] ?? null;
                        $skuData = [
                            'sku' => $pm->sku,
                            'parent' => $pm->parent,
                            'sessions' => $apiItem->Sess30 ?? $apiItem->{'SESS L30'} ?? $apiItem->{'OV CLICKS L30'} ?? 0,
                            'inventory' => $shopify->inv ?? 0,
                            'quantity' => $shopify->quantity ?? 0,
                            'image' => $shopify->image_src ?? null,
                            'api_data' => $apiItem
                        ];

                        // Add Newegg-specific fields if it's Newegg marketplace
                        if ($marketplace === 'NeweggB2C' && isset($neweggDataViews[$pm->sku])) {
                            $neweggValue = $neweggDataViews[$pm->sku];
                            $neweggData = is_array($neweggValue) ? $neweggValue : json_decode($neweggValue, true);
                            
                            $skuData['NR'] = $neweggData['NR'] ?? false;
                            $skuData['A_Z_Reason'] = $neweggData['A_Z_Reason'] ?? null;
                            $skuData['A_Z_ActionRequired'] = $neweggData['A_Z_ActionRequired'] ?? null;
                            $skuData['A_Z_ActionTaken'] = $neweggData['A_Z_ActionTaken'] ?? null;
                        }

                        $skuList[] = $skuData;
                    }

                    $skuCount = count($skuList);
                    $totalSkuCount += $skuCount;
                }
            } catch (\Exception $e) {
                Log::error("Error fetching {$marketplace} data: " . $e->getMessage());
            }

            $finalData[] = [
                'Channel' => $marketplace,
                'sessions_l30' => $skuCount,
                'channel_url' => url($config['viewUrl']), // Using the new view URL
                'sku_details' => $skuList
            ];
        }

        return response()->json([
            'data' => array_values($finalData),
            'sessions_l30' => $totalSkuCount,
            'status' => 200,
            'debug_info' => [
                'total_skus' => $totalSkuCount,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    public function destroy($marketplace)
    {
        try {
            $marketplace = MarketplacePercentage::where('marketplace', $marketplace)->firstOrFail();
            $marketplace->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Marketplace deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to delete marketplace',
                'error' => $e->getMessage()
            ]);
        }
    }

}
