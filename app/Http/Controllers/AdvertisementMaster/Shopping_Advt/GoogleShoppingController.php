<?php

namespace App\Http\Controllers\AdvertisementMaster\Shopping_Advt;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\AmazonDatasheet; // Add this at the top with other use statements
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GoogleShoppingController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function GoogleShopping(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('advertisement-master.google-shopping-advt', [
            'title' => 'Google Shopping Analysis',
            'subtitle' => 'Google Shopping',
            'pagination_title' => 'Google Shopping Analysis',
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

    public function getAllData()
    {
        $amazonDatas = $this->apiController->fetchExternalData2();
        return response()->json($amazonDatas);
    }

    public function getViewGoogleShoppingData(Request $request)
    {
        $responseEbay = $this->apiController->fetchDataFromGoogleShoppingGoogleSheet();

        if ($responseEbay->getStatusCode() !== 200) {
            return response()->json([
                'message' => 'Failed to fetch data from Google Shopping Sheet',
                'status' => 500,
            ], 500);
        }

        $responseData = $responseEbay->getData()->data ?? [];

        $ebayDataArr = $responseData->G_Shopping_L30 ?? [];
        $avgCpcArr = $responseData->G_Shopping_L1 ?? [];
        $GAnalyticsL30 = $responseData->G_Analytics_L30 ?? [];

        // Build case-insensitive Campaign Name => Avg CPC map
        $avgCpcMap = [];
        foreach ($avgCpcArr as $row) {
            if (!empty($row->{'Campaign name'})) {
                $campaignKey = strtolower(trim($row->{'Campaign name'}));
                $avgCpcMap[$campaignKey] = isset($row->{'Average cost per click'}) ? (float) $row->{'Average cost per click'} : 0;
            }
        }

        // Build case-insensitive Campaign Name => Revenue & Events map
        $avgTotalRevenue = [];
        $avgKeyEvents = [];

        foreach ($GAnalyticsL30 as $row) {
            if (!empty($row->{'Campaign name'})) {
                $campaignKey = strtolower(trim($row->{'Campaign name'}));
                $avgTotalRevenue[$campaignKey] = isset($row->{'Total revenue'}) ? (float) $row->{'Total revenue'} : 0;
                $avgKeyEvents[$campaignKey] = isset($row->{'Key events'}) ? (float) $row->{'Key events'} : 0;
            }
        }

        $productMasters = ProductMaster::select('sku', 'parent')->get();
        $shopifySkus = ShopifySku::select('sku', 'inv', 'quantity')->get();

        $productSkuMap = $productMasters->keyBy(fn($item) => trim($item->sku));
        $shopifySkuMap = $shopifySkus->keyBy(fn($item) => trim($item->sku));

        $mergedData = [];

        foreach ($ebayDataArr as $row) {
            $row = is_object($row) ? (array) $row : $row;

            $campaignName = isset($row['Campaign']) ? trim($row['Campaign']) : null;
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

            // ✅ Always include the row — even if nothing matches
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
                'Impressions' => isset($row['Impr.']) ? (float) $row['Impr.'] : 0,
                'Clicks' => isset($row['Clicks']) ? (float) $row['Clicks'] : 0,
                'Ad fees' => isset($row['Cost']) ? (float) $row['Cost'] :
                    (isset($row['TSpnd30']) ? (float) $row['TSpnd30'] : 0),
                'Sales' => $avgTotalRevenue[$campaignKey] ?? 0,
                'Sold quantity' => $avgKeyEvents[$campaignKey] ?? 0,
                'Daily budget' => isset($row['Budget']) ? (float) $row['Budget'] : 0,
                'Average cost per click' => $avgCpcMap[$campaignKey] ?? 0,
            ];
        }
        // ✅ Sort the mergedData array by Parent DESC
        usort($mergedData, function ($b, $a) {
            return strcmp($b['Parent'], $a['Parent']); // DESC
            // return strcmp($a['Parent'], $b['Parent']); // ASC
        });

        return response()->json([
            'message' => 'All Google Shopping data fetched successfully (with unmatched rows included)',
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
}