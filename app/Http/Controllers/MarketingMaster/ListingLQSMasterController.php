<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\AmazonDatasheet; // Add this at the top with other use statements
use App\Models\MarketplacePercentage;
use App\Models\AmazonDataView;
use App\Models\ListingLqs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ListingLQSMasterController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function listingLQSMaster(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        return view('marketing-masters.listingLQS-master', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage
        ]);
    }

        public function getViewListingData(Request $request)
        {
            // 1. Fetch all ProductMaster rows (base)
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            // 2. Fetch AmazonDatasheet and ShopifySku for those SKUs
            $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });
            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            // 3. Fetch API data (Google Sheet)
            $response = $this->apiController->fetchDataFromAmazonGoogleSheet();
            $apiDataArr = ($response->getStatusCode() === 200) ? ($response->getData()->data ?? []) : [];
            // Index API data by SKU (case-insensitive)
            $apiDataBySku = [];
            foreach ($apiDataArr as $item) {
                $sku = isset($item->{'(Child) sku'}) ? strtoupper(trim($item->{'(Child) sku'})) : null;
                if ($sku)
                    $apiDataBySku[$sku] = $item;
            }

            // 4. JungleScout Data (by parent)
            $parents = $productMasters->pluck('parent')->filter()->unique()->map('strtoupper')->values()->all();
            // JungleScout Data
            $jungleScoutData = JungleScoutProductData::whereIn('parent', $parents)
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

            // 5. NR values
            $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            // 6. Marketplace percentage
            $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
                return MarketplacePercentage::where('marketplace', 'Amazon')->value('percentage') ?? 100;
            });
            $percentage = $percentage / 100;

            // 7. Fetch all listing_lqs actions by SKU
            $lqsActions = ListingLqs::all()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

            // 8. Build final data
            $result = [];
            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku);
                $parent = $pm->parent;
                $apiItem = $apiDataBySku[$sku] ?? null;
                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$pm->sku] ?? null;

                // Merge API data into base row if exists
                $row = [];
                $row['Parent'] = $parent;
                $row['(Child) sku'] = $pm->sku;

                // Merge API fields if available
                if ($apiItem) {
                    foreach ($apiItem as $k => $v) {
                        $row[$k] = $v;
                    }
                }

                // Add AmazonDatasheet fields if available
                if ($amazonSheet) {
                    $row['A_L30'] = $row['A_L30'] ?? $amazonSheet->units_ordered_l30;
                    $row['Sess30'] = $row['Sess30'] ?? $amazonSheet->sessions_l30;
                    $row['price'] = $row['price'] ?? $amazonSheet->price;
                    $row['sessions_l60'] = $row['sessions_l60'] ?? $amazonSheet->sessions_l60;
                    $row['units_ordered_l60'] = $row['units_ordered_l60'] ?? $amazonSheet->units_ordered_l60;
                }

                // Add Shopify fields if available
                $row['INV'] = $shopify->inv ?? 0;
                $row['L30'] = $shopify->quantity ?? 0;

                // LP and Ship from ProductMaster
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $lp = 0;
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

                // Formulas
                $price = isset($row['price']) ? floatval($row['price']) : 0;
                $units_ordered_l30 = isset($row['A_L30']) ? floatval($row['A_L30']) : 0;
                $row['Total_pft'] = round((($price * $percentage) - $lp - $ship) * $units_ordered_l30, 2);
                $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);
                $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
                $row['ROI_percentage'] = round($lp > 0 ? ((($price * $percentage) - $lp - $ship) / $lp) * 100 : 0, 2);
                $row['T_COGS'] = round($lp * $units_ordered_l30, 2);

                // JungleScout
                $parentKey = strtoupper($parent);
                if (!empty($parentKey) && $jungleScoutData->has($parentKey)) {
                    $row['scout_data'] = $jungleScoutData[$parentKey];
                }

                    // // Add lqs_jungle if available
                    // $lqsFromScout = $jungleScoutData[$parentKey]['all_data'][0]['listing_quality_score'] ?? null;
                    // $row['lqs_jungle'] = is_numeric($lqsFromScout) ? floatval($lqsFromScout) : null;
                // } else {
                //     $row['lqs_jungle'] = null;
                // }

                // $skuUpper = strtoupper($pm->sku);
                $jungleSkuData = JungleScoutProductData::where('sku', $pm->sku)->latest()->first();

                if ($jungleSkuData && isset($jungleSkuData->data['listing_quality_score'])) {
                    $row['lqs_jungle'] = floatval($jungleSkuData->data['listing_quality_score']);
                } else {
                    $row['lqs_jungle'] = null;
                }


                // Percentage, LP, Ship
                $row['percentage'] = $percentage;
                $row['LP_productmaster'] = $lp;
                $row['Ship_productmaster'] = $ship;

                // NR value
                $row['NR'] = false;
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (is_array($raw)) {
                        $row['NR'] = filter_var($raw['NR'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $decoded = json_decode($raw, true);
                        $row['NR'] = filter_var($decoded['NR'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    }
                }

                // Image path (from Shopify or ProductMaster)
                $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

                // Add action from listing_lqs if exists
                $row['action'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['action'])) {
                    $row['action'] = $lqsActions[$sku]->value['action'];
                }

                $row['listing_quality_score'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['listing_quality_score'])) {
                    $row['listing_quality_score'] = $lqsActions[$sku]->value['listing_quality_score'];
                }

                $row['listing_quality_score_c'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['listing_quality_score_c'])) {
                    $row['listing_quality_score_c'] = $lqsActions[$sku]->value['listing_quality_score_c'];
                }

                $row['link'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['link'])) {
                    $row['link'] = $lqsActions[$sku]->value['link'];
                }

                $row['issue'] = '';
                if (isset($lqsActions[$sku]) && isset($lqsActions[$sku]->value['issue'])) {
                    $row['issue'] = $lqsActions[$sku]->value['issue'];
                }

                $result[] = (object) $row;
            }

            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => $result,
                'status' => 200,
            ]);
        }

    public function saveAction(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'action' => 'nullable|string',
            'listing_quality_score' => 'nullable|numeric',
            'listing_quality_score_c' => 'nullable|numeric',
            'link' => 'nullable|string',
            'issue' => 'nullable|string',
        ]);

        $sku = $request->input('sku');
        $action = $request->input('action');
        $lqs = $request->input('listing_quality_score');
        $lqsc = $request->input('listing_quality_score_c');
        $link = $request->input('link');
        $issue = $request->input('issue');

        // Find or create the record
        $record = ListingLqs::firstOrNew(['sku' => $sku]);
        $value = $record->value ?? [];
        $value['action'] = $action;
        $value['listing_quality_score'] = $lqs;
        $value['listing_quality_score_c'] = $lqsc;
        $value['link'] = $link;
        $value['issue'] = $issue;
        $record->value = $value;
        $record->save();

        return response()->json(['success' => true, 'data' => $record]);
    }


    public function getLqsFromGoogleSheet()
    {
        $response = $this->apiController->fetchDataFromLqsGoogleSheet();

        if ($response->getStatusCode() === 200) {
            $data = $response->getData();

            // Example: Filter to keep only rows with non-empty SKU and LQS
            $filteredData = array_filter($data->data, function ($item) {
                return !empty(trim($item->{'(Child) sku'} ?? '')) && isset($item->LQS);
            });

            // Example: Map to array of ['sku' => ..., 'lqs' => ...]
            $lqsList = array_map(function ($item) {
                return [
                    'sku' => trim($item->{'(Child) sku'}),
                    'lqs' => $item->LQS,
                ];
            }, $filteredData);

            return response()->json([
                'status' => 200,
                'lqs_data' => $lqsList
            ]);
        }

        return response()->json([
            'status' => 500,
            'message' => 'Failed to load LQS data'
        ]);
    }

}