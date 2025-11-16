<?php

namespace App\Http\Controllers\AdvertisementMaster\Kw_Advt;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\KwEbay;
use App\Models\JungleScoutProductData;
use App\Models\AmazonDatasheet; // Add this at the top with other use statements
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\AmazonDataView;

class KwEbayController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function Ebay(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        $apiController = new ApiController();
        $sheetResponse = $apiController->fetchDataFromKwEbayGoogleSheet();

        $sheetData = [];
        if ($sheetResponse->getStatusCode() === 200) {
            $sheetData = $sheetResponse->getData(true)['data'] ?? [];
        }

        $skuFlags = KwEbay::select('sku', 'ra', 'nra', 'running', 'to_pause', 'paused')
        ->get()
        ->mapWithKeys(function ($item) {
            return [strtolower(trim($item->sku)) => [
                'ra' => $item->ra,
                'nra' => $item->nra,
                'running' => $item->running,
                'to_pause' => $item->to_pause,
                'paused' => $item->paused,
            ]];
        })->toArray();

        return view('advertisement-master.kwebay', [
            'title' => 'Ebay Analysis',
            'subtitle' => 'Ebay',
            'pagination_title' => 'Ebay Analysis',
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'sheetData' => $sheetData,
            'skuFlags' => $skuFlags
        ]);
    }


public function getViewKwEbayData(Request $request)
{
     // Fetch all products sorted by ID first
        $allProducts = ProductMaster::orderBy('id', 'asc')->get();

        // Group products by parent while maintaining original order for non-parent items
        $sortedProducts = [];
        $processedParents = [];

        foreach ($allProducts as $product) {
            // If this product has a parent and we haven't processed this parent group yet
            if ($product->parent && !in_array($product->parent, $processedParents)) {
                // Get all products with this parent
                $grouped = $allProducts->where('parent', $product->parent);
                foreach ($grouped as $item) {
                    $sortedProducts[] = (array) $item; // Convert to array
                }
                $processedParents[] = $product->parent;
            }
            // If this product doesn't have a parent and hasn't been added yet
            elseif (!$product->parent && !in_array($product->id, array_column($sortedProducts, 'id'))) {
                $sortedProducts[] = (array) $product; // Convert to array
            }
        }

        // Calculate statistics
        $totalSKUs = count($sortedProducts);
        $parentCount = collect($sortedProducts)->filter(function ($product) {
            return strpos($product['sku'], 'PARENT') !== false;
        })->count();
        // Calculate total LP and CP
        $totalLP = collect($sortedProducts)->sum('lp');
        $totalCP = collect($sortedProducts)->sum('cp');

        return view([
            'products' => $sortedProducts,
            'totalSKUs' => $totalSKUs,
            'parentCount' => $parentCount,
            'totalLP' => number_format($totalLP, 2),
            'totalCP' => number_format($totalCP, 2),
        ]);
}






    public function getAllData()
    {
        $amazonDatas = $this->apiController->fetchExternalData2();
        return response()->json($amazonDatas);
    }



    public function updateAllAmazonSkus(Request $request)
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
                ['marketplace' => 'Amazon'],
                ['percentage' => $percent]
            );

            // Store in cache
            Cache::put('amazon_marketplace_percentage', $percent, now()->addDays(30));

            return response()->json([
                'status' => 200,
                'message' => 'Percentage updated successfully',
                'data' => [
                    'marketplace' => 'Amazon',
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


    public function updateCheckboxes(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'field' => 'required|string',
            'value' => 'required|boolean',
        ]);

        $sku = $request->sku;
        $field = $request->field;
        $value = $request->value;

        $item = KwEbay::firstOrNew(['sku' => $sku]);
        $item->$field = $value;
        $item->save();

        return response()->json(['success' => true]);
    }


}