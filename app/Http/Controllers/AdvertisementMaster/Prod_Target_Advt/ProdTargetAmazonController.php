<?php

namespace App\Http\Controllers\AdvertisementMaster\Prod_Target_Advt;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\PtAmazon;
use App\Models\JungleScoutProductData;
use App\Models\AmazonDatasheet; // Add this at the top with other use statements
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProdTargetAmazonController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function Amazon(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        $apiController = new ApiController();
        $sheetResponse = $apiController->fetchDataFromKwAmazonGoogleSheet();

        $sheetData = [];
        if ($sheetResponse->getStatusCode() === 200) {
            $sheetData = $sheetResponse->getData(true)['data'] ?? [];
        }

        $skuFlags = PtAmazon::select('sku', 'ra', 'nra', 'running', 'to_pause', 'paused')
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

        return view('advertisement-master.prod-target-advt-amazon', [
            'title' => 'Amazon Analysis',
            'subtitle' => 'Amazon',
            'pagination_title' => 'Amazon Analysis',
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

    public function getViewAmazonData(Request $request)
    {
        $response = $this->apiController->fetchDataFromAmazonGoogleSheet();

        if ($response->getStatusCode() !== 200) {
            return response()->json([
                'message' => 'Failed to fetch data from Google Sheet',
                'status' => $response->getStatusCode()
            ], $response->getStatusCode());
        }

        $data = $response->getData();
        $amazonDataArr = $data->data ?? [];

        // Filter and collect all needed ASINs and SKUs in one pass
        $asins = [];
        $skus = [];
        foreach ($amazonDataArr as $item) {
            if (!empty($item->ASIN)) {
                $asins[] = $item->ASIN;
            }
            // Trim only the first and last spaces, not the middle spaces
            $childSku = isset($item->{'(Child) sku'}) ? trim($item->{'(Child) sku'}) : '';
            if (!empty($childSku) && stripos($childSku, 'PARENT') === false) {
                $skus[] = $childSku;
            }
            // Update the item itself for later use
            $item->{'(Child) sku'} = $childSku;
        }
        $asins = array_unique($asins);
        $skus = array_unique($skus);

        // Fetch all needed data in bulk
        $amazonDatasheets = AmazonDatasheet::whereIn('asin', $asins)->get()->keyBy('asin');
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            return MarketplacePercentage::where('marketplace', 'Amazon')->value('percentage') ?? 100;
        });
        $percentage = $percentage / 100;

        // Product Master Data
        // Normalize SKU case for all lookups

        // Build ProductMaster rows with uppercase keys
        $productMasterRows = ProductMaster::whereIn('sku', $skus)->get()->keyBy(function($item) {
            return strtoupper($item->sku);
        });

        // Build AmazonDatasheet rows with uppercase SKU keys for fast lookup
        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function($item) {
            return strtoupper($item->sku);
        });

        // JungleScout Data
        $jungleScoutData = JungleScoutProductData::whereIn('parent', array_map('strtoupper', array_unique(array_column($amazonDataArr, 'Parent'))))
            ->get()
            ->groupBy(function ($item) {
                return strtoupper(trim($item->parent));
            })
            ->map(function ($group) {
                $validPrices = $group->filter(function ($item) {
                    $data = is_array($item->data) ? $item->data : [];
                    $price = $data['price'] ?? null;
                    return is_numeric($price) && $price > 0;
                })->pluck('data.price');

                return [
                    'scout_parent' => $group->first()->parent,
                    'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                    'product_count' => $group->count(),
                    'all_data' => $group->map(function ($item) {
                        $data = is_array($item->data) ? $item->data : [];
                        if (isset($data['price'])) {
                            $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                        }
                        return $data;
                    })->toArray()
                ];
            });

        // Filter and process data
        $filteredData = array_filter($amazonDataArr, function ($item) {
            $parent = $item->Parent ?? '';
            $childSku = $item->{'(Child) sku'} ?? '';
            return !(empty(trim($parent)) && empty(trim($childSku)));
        });

        // Use array_map for processing
        $processedData = array_map(function ($item) use ($shopifyData, $jungleScoutData, $amazonDatasheets, $amazonDatasheetsBySku, $percentage, $productMasterRows) {
            // Normalize childSku to uppercase for lookup
            $childSku = isset($item->{'(Child) sku'}) ? strtoupper(trim($item->{'(Child) sku'})) : '';
            $parentAsin = strtoupper(trim($item->Parent ?? ''));
            $asin = $item->ASIN ?? null;

            // Match by SKU (new logic)
            if ($childSku && $amazonDatasheetsBySku->has($childSku)) {
                $sheet = $amazonDatasheetsBySku[$childSku];
                // Only overwrite if not already set by ASIN
                if (!isset($item->{'A_L30'})) $item->{'A_L30'} = $sheet->units_ordered_l30;
                if (!isset($item->{'Sess30'})) $item->{'Sess30'} = $sheet->sessions_l30;
                if (!isset($item->{'price'})) $item->{'price'} = $sheet->price;
                if (!isset($item->{'sessions_l60'})) $item->{'sessions_l60'} = $sheet->sessions_l60;
                if (!isset($item->{'units_ordered_l60'})) $item->{'units_ordered_l60'} = $sheet->units_ordered_l60;
            }

            $price = isset($item->{'price'}) ? floatval($item->{'price'}) : 0;
            $units_ordered_l30 = isset($item->{'A_L30'}) ? floatval($item->{'A_L30'}) : 0;

            // Match LP and Ship using SKU (Child) sku, get from ProductMaster only
            $lp = 0;
            $ship = 0;
            if ($childSku && isset($productMasterRows[$childSku])) {
                $values = $productMasterRows[$childSku]->Values ?: [];
                // Case-insensitive check for 'lp' in ProductMaster->Values only
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($productMasterRows[$childSku]->lp)) {
                    $lp = floatval($productMasterRows[$childSku]->lp);
                }
                // Ship: prefer from Values, fallback to direct property in ProductMaster
                $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($productMasterRows[$childSku]->ship) ? floatval($productMasterRows[$childSku]->ship) : 0);
            }

            $total_pft = (($price * $percentage) - $lp - $ship) * $units_ordered_l30;
            $t_sale_l30 = $price * $units_ordered_l30;
            $total_cogs = $lp * $units_ordered_l30;
            $pft_percentage = $price > 0 ? (($price * $percentage) - $lp - $ship) / $price : 0;
            $roi_percentage = $lp > 0 ? (($price * $percentage) - $lp - $ship) / $lp : 0;

            $item->Total_pft = round($total_pft, 2);
            $item->T_Sale_l30 = round($t_sale_l30, 2);
            $item->PFT_percentage = round($pft_percentage * 100, 2);
            $item->ROI_percentage = round($roi_percentage * 100, 2);
            $item->T_COGS = round($total_cogs, 2);

            if (!empty($parentAsin) && $jungleScoutData->has($parentAsin)) {
                $item->scout_data = $jungleScoutData[$parentAsin];
            }

            if (!empty($childSku) && stripos($childSku, 'PARENT') === false) {
                $item->INV = $shopifyData[$childSku]->inv ?? 0;
                $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            }

            $item->percentage = $percentage;
            $item->LP_productmaster = $lp;
            $item->Ship_productmaster = $ship;

            return $item;
        }, $filteredData);

        $processedData = array_values($processedData);

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $processedData,
            'status' => 200,
            'debug' => [
                'jungle_scout_parents' => $jungleScoutData->keys()->take(5),
                'matched_parents' => collect($processedData)
                    ->filter(fn($item) => isset($item->scout_data))
                    ->pluck('Parent')
                    ->unique()
                    ->values()
            ]
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

    public function updateCheckbox(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'field' => 'required|string',
            'value' => 'required|boolean',
        ]);

        $sku = $request->sku;
        $field = $request->field;
        $value = $request->value;

        $item = PtAmazon::firstOrNew(['sku' => $sku]);
        $item->$field = $value;
        $item->save();

        return response()->json(['success' => true]);
    }
}