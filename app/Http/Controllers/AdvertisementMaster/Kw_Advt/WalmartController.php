<?php

namespace App\Http\Controllers\AdvertisementMaster\Kw_Advt;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\KwWalmart;
use App\Models\JungleScoutProductData;
use App\Models\AmazonDatasheet; // Add this at the top with other use statements
use App\Models\MarketplacePercentage;
use App\Models\WalmartDataView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WalmartController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function Walmart(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });


        $apiController = new ApiController();
        $sheetResponse = $apiController->fetchDataFromKwWalmartGoogleSheet();

        $sheetData = [];
        if ($sheetResponse->getStatusCode() === 200) {
            $sheetData = $sheetResponse->getData(true)['data'] ?? [];
        }

        $skuFlags = KwWalmart::select('sku', 'ra', 'nra', 'running', 'to_pause', 'paused')
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

        return view('advertisement-master.kw-advt-walmart', [
            'title' => 'Walmart Analysis',
            'subtitle' => 'Walmart',
            'pagination_title' => 'Walmart Analysis',
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'sheetData' => $sheetData,
            'skuFlags' => $skuFlags
        ]);
    }

    public function getAllData()
    {
        $amazonDatas = $this->apiController->fetchExternalData2();
        return response()->json($amazonDatas);
    }

    public function getViewKwWalmartData(Request $request)
    {
        $responseWalmart = $this->apiController->fetchDataFromKwWalmartGoogleSheet();

        if ($responseWalmart->getStatusCode() !== 200) {
            return response()->json([
                'message' => 'Failed to fetch data from Walmart Google Sheet',
                'status' => 500,
            ], 500);
        }

        $responseData = $responseWalmart->getData()->data ?? [];

        $walmartDataArr = $responseData->walmart_kw_l30 ?? [];
        $avgCpcArr = $responseData->walmart_kw_l2 ?? [];

        // Build case-insensitive Campaign Name => Avg CPC map
        $avgCpcMap = [];
        foreach ($avgCpcArr as $row) {
            if (!empty($row->{'Campaign name'})) {
                $campaignKey = strtolower(trim($row->{'Campaign name'}));
                $avgCpcMap[$campaignKey] = isset($row->{'Average CPC'}) ? (float) $row->{'Average CPC'} : 0;
            }
        }

        $productMasters = ProductMaster::select('sku', 'parent')->get();
        $shopifySkus = ShopifySku::select('sku', 'inv', 'quantity')->get();

        $productSkuMap = $productMasters->keyBy(fn($item) => trim($item->sku));
        $shopifySkuMap = $shopifySkus->keyBy(fn($item) => trim($item->sku));

        $mergedData = [];

        foreach ($walmartDataArr as $row) {
            $row = is_object($row) ? (array) $row : $row;

            $campaignName = isset($row['Campaign name']) ? trim($row['Campaign name']) : null;
            $campaignKey = strtolower($campaignName ?? '');

            $sku = $campaignName;
            $parent = null;
            $inv = null;
            $l30 = null;

            // Match in ProductMaster
            if ($sku && $productSkuMap->has($sku)) {
                $parent = $productSkuMap[$sku]->parent ?? null;
            }

            // Match in ShopifySku
            if ($sku && $shopifySkuMap->has($sku)) {
                $inv = $shopifySkuMap[$sku]->inv ?? null;
                $l30 = $shopifySkuMap[$sku]->quantity ?? null;

                if (!$parent && $productSkuMap->has($sku)) {
                    $parent = $productSkuMap[$sku]->parent ?? null;
                }
            }

            $OVDIL = ($inv && $l30) ? ($l30 / $inv) * 100 : null;

            $mergedData[] = [
                'SL No.' => $row['SL No.'] ?? null,
                'Parent' => $parent,
                '(Child) sku' => $sku,
                'R&A' => $row['R&A'] ?? null,
                'INV' => $inv,
                'L30' => $l30,
                'OVDil' => $OVDIL,
                'is_parent' => $row['is_parent'] ?? false,
                'AMZ LINK BL' => $row['AMZ LINK BL'] ?? '',
                'AMZ LINK SL' => $row['AMZ LINK SL'] ?? '',
                'Impressions' => isset($row['Impressions']) ? (float) $row['Impressions'] : 0,
                'Clicks' => isset($row['Clicks']) ? (float) $row['Clicks'] : 0,
                'Ad fees' => isset($row['Ad Spend']) ? (float) $row['Ad Spend'] : 0,
                'Sales' => isset($row['Total Attributed Sales']) ? (float) $row['Total Attributed Sales'] : 0,
                'Sold quantity' => isset($row['Units Sold']) ? (float) $row['Units Sold'] : 0,
                'Daily budget' => isset($row['Daily budget']) ? (float) $row['Daily budget'] : 0,
                'Average cost per click' => $avgCpcMap[$campaignKey] ?? 0,
            ];
        }
        // ✅ Sort the mergedData array by Parent DESC
        usort($mergedData, function ($b, $a) {
            return strcmp($b['Parent'], $a['Parent']); // DESC
            // return strcmp($a['Parent'], $b['Parent']); // ASC
        });

        return response()->json([
            'message' => 'All Walmart data fetched with matches and blanks where unmatched',
            'data' => $mergedData,
            'status' => 200,
        ]);
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

        $item = KwWalmart::firstOrNew(['sku' => $sku]);
        $item->$field = $value;
        $item->save();

        return response()->json(['success' => true]);
    }

    

}