<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\ShopifySku;
use App\Models\Shopifyb2cDataView;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use App\Models\AmazonDataView;
use App\Models\AmazonBidCap;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\AmazonSpApiService;
use App\Models\MarketplacePercentage;
use Illuminate\Support\Facades\Cache;
use App\Models\JungleScoutProductData;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\MarketPlace\CvrMasterController;
use App\Jobs\UpdateAmazonSPriceJob;
use App\Models\AmazonDatasheet;
use App\Models\ADVMastersData;
use App\Models\ChannelMaster;
use Illuminate\Support\Facades\DB;
use App\Services\AmazonDataService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\AmazonFbmManual;
use App\Models\AmazonListingStatus;
use App\Models\AmazonChannelSummary;
use App\Models\AmazonSeoAuditHistory;
use App\Models\AmazonSkuCompetitor;
use App\Models\FbaPrice;
use App\Models\FbaTable;
use App\Services\FbaInventoryService;

class OverallAmazonController extends Controller
{
    protected $apiController;
    protected $amazonDataService;

    public function __construct(ApiController $apiController, AmazonDataService $amazonDataService)
    {
        $this->apiController = $apiController;
        $this->amazonDataService = $amazonDataService;
    }

    public function updatePrice(Request $request)
    {
        $sku = $request["sku"];
        $price = $request["price"];

        $price = app(AmazonSpApiService::class)->updateAmazonPriceUS($sku, $price);

        return response()->json(['status' => 200, 'data' => $price]);
    }

    public function overallAmazon(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view('market-places.overallAmazon', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }

    public function getAmazonTotalSalesSaveData(Request $request)
    {
        return ADVMastersData::getAmazonTotalSalesSaveDataProceed($request);
    }

    public function amazonPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get fresh data from database instead of cache for immediate updates
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;


        return view('market-places.amazon_pricing_cvr', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }


    public function updateFbaStatus(Request $request)
    {
        $sku = $request->input('shopify_id');
        $fbaStatus = $request->input('fba');

        if (!$sku || !is_numeric($fbaStatus)) {
            return response()->json(['error' => 'SKU and FBA status are required.'], 400);
        }
        $amazonData = DB::table('amazon_data_view')
            ->where('sku', $sku)
            ->first();

        if (!$amazonData) {
            return response()->json(['error' => 'SKU not found.'], 404);
        }
        DB::table('amazon_data_view')
            ->where('sku', $sku)
            ->update(['fba' => $fbaStatus]);
        $updatedData = DB::table('amazon_data_view')
            ->where('sku', $sku)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'FBA status updated successfully.',
            'data' => $updatedData
        ]);
    }


    public function getViewAmazonData(Request $request)
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Build SKU variants for amazon_datsheets lookup: as-is, nbsp→space, and space-removed (so "PS 01 WH" and "PS01WH" both match)
        $cleanedSkus = array_map(function ($s) {
            return str_replace("\xC2\xA0", ' ', trim($s));
        }, $skus);
        $noSpaceSkus = array_values(array_unique(array_filter(array_map(function ($s) {
            return str_replace(' ', '', strtoupper(str_replace("\xC2\xA0", ' ', trim($s))));
        }, $skus))));
        $allSkusForLookup = array_values(array_unique(array_filter(array_merge($skus, $cleanedSkus, $noSpaceSkus))));

        // Key every datasheet row by the shared Amazon normalization (spaces removed +
        // PCS/PC piece-count fold) so product "SP 12120 8OHM GTR" matches "SP12120 8OHM GTR"
        // and "MS 080 WH 2 PCS" matches the datasheet's "MS 080 WH 2PC". Only ~1k rows.
        $amazonDatasheetsBySku = AmazonDatasheet::query()->get()->keyBy(function ($item) {
            return AmazonDatasheet::normalizeSkuForLookup($item->sku ?? '');
        });

        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // Last Shopify B2C price push status (CVR / UpdatePriceApiController persist into shopifyb2c_data_view.value)
        $shopifyB2cDataBySku = [];
        foreach (Shopifyb2cDataView::whereIn('sku', $skus)->get() as $b2cRow) {
            $k = strtoupper(str_replace("\xC2\xA0", ' ', trim((string) ($b2cRow->sku ?? ''))));
            $shopifyB2cDataBySku[$k] = $b2cRow;
        }

        // FBA price by SKU (seller_sku like "ABC FBA" -> key "ABC")
        $fbaPriceBySku = FbaPrice::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->get()
            ->keyBy(function ($item) {
                $base = preg_replace('/\s*FBA\s*/i', '', $item->seller_sku ?? '');
                return strtoupper(str_replace(' ', '', trim($base)));
            });

        // FBA INV: shared resolver (same rules as FbaInventoryService / FBA Analytics base key).
        $fbaTableFbaRows = FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")->get();
        $fbaInventoryService = FbaInventoryService::fromFbaRows($fbaTableFbaRows);

        Log::debug('Amazon tabulator getViewAmazonData: FBA inventory map loaded', [
            'fba_table_rows' => $fbaTableFbaRows->count(),
            'fba_analytics_key_count' => $fbaInventoryService->distinctAnalyticsKeyCount(),
            'product_master_skus' => count($skus),
        ]);

        if (filter_var(env('FBA_INVENTORY_DEBUG', false), FILTER_VALIDATE_BOOLEAN)) {
            foreach (['CAPO AL BLK', 'CAPO AL BLK FBA', 'ET 10FT BLU FBA', 'ET 6FT BLU fba'] as $probeSku) {
                $hit = $fbaInventoryService->resolve($probeSku);
                Log::channel('amazon_debug')->info('Amazon FBM FBA inventory probe', [
                    'product_sku' => $probeSku,
                    'matched_seller_sku' => $hit?->seller_sku,
                    'fba_quantity' => $hit ? (int) ($hit->quantity_available ?? 0) : null,
                ]);
            }
        }

        // Get Amazon inventory from product_stock_mappings table
        $stockMappings = ProductStockMapping::whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        $ratings = AmazonFbmManual::whereIn('sku', $skus)->pluck('data', 'sku')->toArray();

        // Load SEO Audit History from dedicated table with user names
        $seoAuditHistory = AmazonSeoAuditHistory::whereIn('sku', $skus)
            ->leftJoin('users', 'amazon_seo_audit_history.user_id', '=', 'users.id')
            ->select('amazon_seo_audit_history.*', 'users.name as user_name')
            ->orderBy('amazon_seo_audit_history.created_at', 'desc')
            ->get()
            ->groupBy('sku')
            ->map(function($entries) {
                return $entries->map(function($entry) {
                    return [
                        'id' => $entry->id,
                        'text' => $entry->checklist_text,
                        'user_name' => $entry->user_name ?? 'Guest',
                        'timestamp' => $entry->created_at ? $entry->created_at->format('Y-m-d H:i:s') : 'N/A'
                    ];
                })->values()->toArray();
            });
        
        Log::info('SEO Audit History loaded', ['count' => $seoAuditHistory->count()]);

        // Get all JungleScout data - group by SKU first, then also by ASIN for fallback
        $allJungleScoutData = JungleScoutProductData::all();
        
        // Group by SKU (case-insensitive) - for products with SKU in Jungle Scout
        $jungleScoutBySku = $allJungleScoutData
            ->filter(function ($item) {
                return !empty($item->sku);
            })
            ->groupBy(function ($item) {
                return strtoupper(trim($item->sku));
            })
            ->map(function ($group) {
                $validPrices = $group->filter(function ($item) {
                    $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                    $price = $data['price'] ?? null;
                    return is_numeric($price) && $price > 0;
                })->map(function ($item) {
                    $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                    return $data['price'];
                });

                return [
                    'scout_parent' => $group->first()->parent,
                    'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                    'product_count' => $group->count(),
                    'all_data' => $group->map(function ($item) {
                        $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                        if (isset($data['price'])) {
                            $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                        }
                        return $data;
                    })->toArray()
                ];
            });
        
        // Group by ASIN (from data->id field) - for fallback when SKU doesn't match
        $jungleScoutByAsin = $allJungleScoutData
            ->filter(function ($item) {
                $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                return isset($data['id']) && !empty($data['id']);
            })
            ->groupBy(function ($item) {
                $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                $id = $data['id'] ?? '';
                $asin = str_replace('us/', '', $id);
                return strtoupper(trim($asin));
            })
            ->map(function ($group) {
                $validPrices = $group->filter(function ($item) {
                    $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                    $price = $data['price'] ?? null;
                    return is_numeric($price) && $price > 0;
                })->map(function ($item) {
                    $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                    return $data['price'];
                });

                return [
                    'scout_parent' => $group->first()->parent,
                    'min_price' => $validPrices->isNotEmpty() ? $validPrices->min() : null,
                    'product_count' => $group->count(),
                    'all_data' => $group->map(function ($item) {
                        $data = is_array($item->data) ? $item->data : json_decode($item->data, true);
                        if (isset($data['price'])) {
                            $data['price'] = is_numeric($data['price']) ? (float) $data['price'] : null;
                        }
                        return $data;
                    })->toArray()
                ];
            });

        // Load NR values from AmazonListingStatus model instead of AmazonDataView
        $nrListingStatuses = AmazonListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
        
        // Load AmazonDataView by exact SKU, then by normalized SKU (trim + collapse spaces) so NRL/NRA show when stored under a variant SKU
        $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku')->all();
        $normalizeSku = function ($s) {
            if ($s === null || $s === '') return '';
            $s = preg_replace('/\s+/', ' ', trim((string) $s));
            $s = preg_replace('/\s+2\s+PCS\b/i', ' 2PCS', $s);
            return $s;
        };
        $normalizedSkusSet = array_flip(array_unique(array_filter(array_map($normalizeSku, $skus))));
        foreach (AmazonDataView::select('sku', 'value')->get() as $r) {
            $n = $normalizeSku($r->sku);
            if ($n !== '' && isset($normalizedSkusSet[$n])) {
                if (!isset($nrValues[$r->sku])) {
                    $nrValues[$r->sku] = $r->value;
                }
                $nrValues[$n] = $r->value;
            }
        }

        // Load Bid Caps from dedicated table
        $bidCaps = AmazonBidCap::whereIn('sku', $skus)
            ->with('user')
            ->get()
            ->keyBy('sku');

        // Fetch LMP data from amazon_sku_competitors table
        $lmpLowestLookup = collect();
        $lmpDetailsLookup = collect();
        try {
            $lmpLookups = AmazonSkuCompetitor::buildGroupedLookup('amazon');
            $lmpDetailsLookup = $lmpLookups['details'];
            $lmpLowestLookup = $lmpLookups['lowest'];
        } catch (\Exception $e) {
            Log::warning('Could not fetch LMP data from amazon_sku_competitors: ' . $e->getMessage());
        }

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();

        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        $parentSkuCounts = $productMasters
            ->filter(fn($pm) => $pm->parent && !str_starts_with(strtoupper($pm->sku), 'PARENT'))
            ->groupBy('parent')
            ->map->count();

        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $skuClean = strtoupper(str_replace("\xC2\xA0", ' ', trim($pm->sku)));
            $skuLookupKey = str_replace(' ', '', $skuClean);

            if (str_starts_with($sku, 'PARENT ')) {
                continue;
            }

            // Normalize parent: trim, collapse multiple spaces, so "10  FR" and "10 FR" group together and get one parent summary row
            $parent = preg_replace('/\s+/', ' ', trim((string) ($pm->parent ?? '')));
            // Amazon datasheet match uses the shared normalization (spaces removed + PCS/PC fold).
            $amazonSheetKey = AmazonDatasheet::normalizeSkuForLookup($pm->sku);
            $amazonSheet = $amazonDatasheetsBySku[$amazonSheetKey] ?? ($amazonDatasheetsBySku[$skuLookupKey] ?? ($amazonDatasheetsBySku[$skuClean] ?? ($amazonDatasheetsBySku[$sku] ?? null)));
            $shopify = $shopifyData[$pm->sku] ?? null;

            $row = [];
            // Get rating and reviews from Jungle Scout - Try SKU first, fallback to ASIN
            $jsData = $jungleScoutBySku->get($sku);
            
            // If no match by SKU, try ASIN fallback
            if (!$jsData && $amazonSheet && $amazonSheet->asin) {
                $asinKey = strtoupper(trim($amazonSheet->asin));
                $jsData = $jungleScoutByAsin->get($asinKey);
            }
            
            $jsAllData = $jsData['all_data'] ?? [];
            
            // Extract rating and reviews from jungle scout data (first entry with valid data)
            $rating = null;
            $reviews = null;
            foreach ($jsAllData as $jsEntry) {
                if (isset($jsEntry['rating']) && $jsEntry['rating'] > 0) {
                    $rating = $jsEntry['rating'];
                    $reviews = $jsEntry['reviews'] ?? null;
                    break;
                }
            }
            
            $row['rating'] = $rating;
            $row['reviews'] = $reviews;
            $row['Parent'] = $parent;
            $row['parent'] = $parent; // lowercase for any consumers expecting it
            $row['(Child) sku'] = $pm->sku;

            if ($amazonSheet) {
                $row['asin'] = $amazonSheet->asin ?? null;
                $row['A_L30'] = $amazonSheet->units_ordered_l30 ?? 0;
                $row['A_L15'] = $amazonSheet->units_ordered_l15 ?? 0;
                $row['A_L7'] = $amazonSheet->units_ordered_l7 ?? 0;
                $row['Sess30'] = $amazonSheet->sessions_l30 ?? 0;
                $row['Sess7'] = $amazonSheet->sessions_l7 ?? 0;
                $row['price'] = $amazonSheet->price;
                $row['price_lmpa'] = $amazonSheet->price_lmpa;
                $row['sessions_l60'] = $amazonSheet->sessions_l60 ?? 0;
                $row['units_ordered_l60'] = $amazonSheet->units_ordered_l60 ?? 0;
            } else {
                $row['asin'] = null;
                $row['A_L30'] = 0;
                $row['A_L15'] = 0;
                $row['A_L7'] = 0;
                $row['Sess30'] = 0;
                $row['Sess7'] = 0;
                $row['price'] = 0;
                $row['price_lmpa'] = 0;
                $row['sessions_l60'] = 0;
                $row['units_ordered_l60'] = 0;
            }

            // FBA price for same SKU (from FbaPrice table)
            $fbaPriceRecord = $fbaPriceBySku->get($skuLookupKey) ?? $fbaPriceBySku->get(str_replace(' ', '', $skuClean)) ?? $fbaPriceBySku->get($sku);
            $row['fba_price'] = $fbaPriceRecord ? round(floatval($fbaPriceRecord->price ?? 0), 2) : null;

            $fbaInvRecord = $fbaInventoryService->resolve((string) $pm->sku);
            $row['FBA_Quantity'] = $fbaInvRecord ? (int) ($fbaInvRecord->quantity_available ?? 0) : 0;
            $row['FBA_SKU'] = $fbaInvRecord ? ($fbaInvRecord->seller_sku ?? null) : null;

            $row['INV'] = $shopify->inv ?? 0;
            
            // Get Amazon inventory from stock mappings (null-safe, handle string values)
            $stockMapping = $stockMappings->get($pm->sku);
            $inventoryAmazon = $stockMapping ? ($stockMapping->inventory_amazon ?? 0) : 0;
            
            // Convert to numeric if possible, otherwise 0 (handle "Not Listed", "NRL", etc.)
            if (is_numeric($inventoryAmazon)) {
                $row['INV_AMZ'] = (int)$inventoryAmazon;
            } else {
                $row['INV_AMZ'] = 0; // Set to 0 for non-numeric values
            }
            
            // Check if SKU exists in amazon_datsheets (indicates it's listed on Amazon)
            // If it doesn't exist, mark as missing
            $row['is_missing_amazon'] = $amazonSheet ? false : true;
            
            $row['L30'] = $shopify->quantity ?? 0;
            $row['fba'] = $pm->fba;


            // LP & ship cost
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

            $price = isset($row['price']) ? floatval($row['price']) : 0;
            $units_ordered_l30 = isset($row['A_L30']) ? floatval($row['A_L30']) : 0;

            $row['Total_pft'] = round((($price * $percentage) - $lp - $ship) * $units_ordered_l30, 2);
            $row['T_Sale_l30'] = round($price * $units_ordered_l30, 2);
            $row['PFT_percentage'] = round($price > 0 ? ((($price * $percentage) - $lp - $ship) / $price) * 100 : 0, 2);
            $row['ROI_percentage'] = round($lp > 0 ? ((($price * $percentage) - $lp - $ship) / $lp) * 100 : 0, 2);
            $row['T_COGS'] = round($lp * $units_ordered_l30, 2);
            $row['ad_updates'] = $adUpdates;

            $parentKey = strtoupper($parent);
            if (!empty($parentKey) && $jungleScoutBySku->has($parentKey)) {
                $row['scout_data'] = $jungleScoutBySku[$parentKey];
            } elseif (!empty($parentKey) && $jungleScoutByAsin->has($parentKey)) {
                $row['scout_data'] = $jungleScoutByAsin[$parentKey];
            }

            $row['percentage'] = $percentage;
            $row['LP_productmaster'] = $lp;
            $row['Ship_productmaster'] = $ship;

            // Default values
            $row['NRL'] = '';
            $row['NRA'] = '';
            $row['FBA'] = null;
            $row['SPRICE'] = null;
            $row['Spft'] = null;
            $row['SROI'] = null;
            $row['ad_spend'] = null;
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;
            $row['js_comp_manual_api_link'] = null;
            $row['js_comp_manual_link'] = null;
            $row['bid_cap'] = null;

            // LMP data - lowest entry plus all competitors
            $skuLookupKey = AmazonSkuCompetitor::normalizeSkuKey($pm->sku);
            $lmpEntries = $lmpDetailsLookup->get($skuLookupKey);
            if (!$lmpEntries instanceof \Illuminate\Support\Collection) {
                $lmpEntries = collect();
            }

            $lowestLmp = $lmpLowestLookup->get($skuLookupKey);
            $row['lmp_price'] = ($lowestLmp && isset($lowestLmp->price))
                ? (is_numeric($lowestLmp->price) ? floatval($lowestLmp->price) : null)
                : null;
            $row['lmp_link'] = $lowestLmp->product_link ?? null;
            $row['lmp_asin'] = $lowestLmp->asin ?? null;
            $row['lmp_title'] = $lowestLmp->product_title ?? null;
            $row['lmp_delivery'] = $lowestLmp ? $this->normalizeDeliveryText($lowestLmp->delivery ?? null) : null;
            
            // Competitor sales data from JungleScout
            $row['lmp_monthly_revenue'] = ($lowestLmp && isset($lowestLmp->monthly_revenue))
                ? (is_numeric($lowestLmp->monthly_revenue) ? floatval($lowestLmp->monthly_revenue) : null)
                : null;
            $row['lmp_monthly_units'] = ($lowestLmp && isset($lowestLmp->monthly_units_sold))
                ? intval($lowestLmp->monthly_units_sold)
                : null;
            $row['lmp_buy_box_owner'] = $lowestLmp->buy_box_owner ?? null;
            $row['lmp_seller_type'] = $lowestLmp->seller_type_js ?? null;
            
            $row['lmp_entries'] = $lmpEntries
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'asin' => $entry->asin ?? null,
                        'price' => is_numeric($entry->price) ? floatval($entry->price) : null,
                        'delivery' => $this->normalizeDeliveryText($entry->delivery ?? null),
                        'link' => $entry->product_link ?? null,
                        'product_link' => $entry->product_link ?? null,
                        'title' => $entry->product_title ?? null,
                        'product_title' => $entry->product_title ?? null,
                        'image' => $entry->image ?? null,
                        'seller_name' => $entry->seller_name ?? null,
                        'marketplace' => $entry->marketplace ?? 'US',
                        'rating' => $entry->rating ?? null,
                        'reviews' => $entry->reviews ?? null,
                        'monthly_revenue' => ($entry->monthly_revenue && is_numeric($entry->monthly_revenue)) 
                            ? floatval($entry->monthly_revenue) 
                            : null,
                        'monthly_units_sold' => ($entry->monthly_units_sold) 
                            ? intval($entry->monthly_units_sold) 
                            : null,
                        'buy_box_owner' => $entry->buy_box_owner ?? null,
                        'seller_type' => $entry->seller_type_js ?? null,
                        'sales_data_updated_at' => $entry->sales_data_updated_at ?? null,
                    ];
                })
                ->toArray();
            $row['lmp_entries_total'] = $lmpEntries->count();

            // GPFT% Formula = ((price × 0.80 - ship - lp) / price) × 100
            $row['GPFT%'] = $price > 0
                ? round((($price * 0.80 - $ship - $lp) / $price) * 100, 2)
                : 0;

            // GROI% (Gross ROI) = ((price × 0.80 - ship - lp) / lp) × 100
            // Same numerator as GPFT, divided by LP instead of Price
            $row['GROI%'] = $lp > 0
                ? round((($price * 0.80 - $ship - $lp) / $lp) * 100, 2)
                : 0;

            // Load NR field from AmazonDataView (exact SKU or normalized SKU so spacing variants match)
            $nrRaw = $nrValues[$pm->sku] ?? $nrValues[$normalizeSku($pm->sku)] ?? null;
            if ($nrRaw !== null) {
                $raw = is_array($nrRaw) ? $nrRaw : (is_string($nrRaw) ? json_decode($nrRaw, true) : null);

                if (is_array($raw)) {
                    // Read NRL field from amazon_data_view - "REQ" means RL, "NRL" means NRL
                    $nrlValue = $raw['NRL'] ?? null;
                    $row['NRL'] = $nrlValue; // So frontend shows correct NRL icon (REQ=green, NRL=red)
                    if ($nrlValue === 'NRL') {
                        $row['NR'] = 'NR';
                    } else if ($nrlValue === 'REQ') {
                        $row['NR'] = 'REQ';
                    } else {
                        $row['NR'] = null;
                    }
                    
                    // Load buyer and seller links
                    $row['buyer_link'] = $raw['buyer_link'] ?? null;
                    $row['seller_link'] = $raw['seller_link'] ?? null;
                    
                    $row['NRA'] = $raw['NRA'] ?? null;
                    $row['FBA'] = $raw['FBA'] ?? null;
                    $row['shopify_id'] = $shopify->id ?? null;
                    $row['SPRICE'] = $raw['SPRICE'] ?? null;
                    $row['Spft%'] = $raw['SPFT'] ?? null;
                    $row['SROI'] = $raw['SROI'] ?? null;
                    $row['SGPFT'] = $raw['SGPFT'] ?? null;
                    $row['ad_spend'] = $raw['Spend_L30'] ?? null;
                    $row['SPRICE_STATUS'] = $raw['SPRICE_STATUS'] ?? null; // Status: 'pushed', 'applied', 'error'
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['variation_display'] = (isset($raw['variation']) && in_array($raw['variation'], ['red', 'green'], true)) ? $raw['variation'] : 'red';
                }
            }
            if (!array_key_exists('variation_display', $row)) {
                $row['variation_display'] = 'red';
            }

            // Load Bid Cap from dedicated table
            if (isset($bidCaps[$pm->sku])) {
                $bidCapRecord = $bidCaps[$pm->sku];
                $row['bid_cap'] = $bidCapRecord->bid_cap;
                $row['bid_cap_user'] = $bidCapRecord->user ? $bidCapRecord->user->name : null;
                $row['bid_cap_updated_at'] = $bidCapRecord->last_updated_at ? $bidCapRecord->last_updated_at->format('Y-m-d H:i:s') : null;
            } else {
                $row['bid_cap'] = null;
                $row['bid_cap_user'] = null;
                $row['bid_cap_updated_at'] = null;
            }

            // Continue with other fields (APlus, checklist, etc.) – use same exact or normalized lookup
            if ($nrRaw !== null) {
                $raw = is_array($nrRaw) ? $nrRaw : (is_string($nrRaw) ? json_decode($nrRaw, true) : null);
                if (is_array($raw)) {
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['js_comp_manual_api_link'] = $raw['js_comp_manual_api_link'] ?? '';
                    $row['js_comp_manual_link'] = $raw['js_comp_manual_link'] ?? '';
                    $row['checklist'] = $raw['checklist'] ?? '';
                }
            }
            
            // Load SEO audit history from dedicated table
            $historyForSku = $seoAuditHistory->get($pm->sku) ?? [];
            $row['seo_audit_history'] = $historyForSku;
            
            // Debug log for first few SKUs with history
            if (!empty($historyForSku) && rand(1, 100) <= 5) {
                Log::info('SEO History for SKU', [
                    'sku' => $pm->sku,
                    'history_count' => count($historyForSku),
                    'history' => $historyForSku
                ]);
            }
            
            // Fallback to AmazonListingStatus if NR not set from AmazonDataView
            if (!isset($row['NR']) || $row['NR'] === null) {
                $listingStatus = $nrListingStatuses->get($pm->sku);
                if ($listingStatus && $listingStatus->value) {
                    $listingValue = is_array($listingStatus->value) ? $listingStatus->value : [];
                    $row['NR'] = $listingValue['nr_req'] ?? null;
                    // Only set links from listing status if not already set from AmazonDataView
                    if (!isset($row['buyer_link'])) {
                        $row['buyer_link'] = $listingValue['buyer_link'] ?? null;
                    }
                    if (!isset($row['seller_link'])) {
                        $row['seller_link'] = $listingValue['seller_link'] ?? null;
                    }
                }
            }
            

            // If SPRICE is null or empty, use price as default and calculate SGPFT/SPFT/SROI
            if (empty($row['SPRICE']) && $price > 0) {
                $row['SPRICE'] = $price;
                $row['has_custom_sprice'] = false; // Flag to indicate using default price
                
                // Calculate SGPFT based on default price (using 0.80 for Amazon)
                $sgpft = round(
                    $price > 0 ? (($price * 0.80 - $ship - $lp) / $price) * 100 : 0,
                    2
                );
                $row['SGPFT'] = $sgpft;
            } else {
                $row['has_custom_sprice'] = true; // Flag to indicate custom SPRICE
                
                // Calculate SGPFT using custom SPRICE if not already set (using 0.80 for Amazon)
                if (empty($row['SGPFT'])) {
                    $sprice = floatval($row['SPRICE']);
                    $sgpft = round(
                        $sprice > 0 ? (($sprice * 0.80 - $ship - $lp) / $sprice) * 100 : 0,
                        2
                    );
                    $row['SGPFT'] = $sgpft;
                }
            }

            $row['image_path'] = $shopify->image_src ?? ($values['image_path'] ?? null);

            // Shopify B2C push status for tabulator "S st" column (pushed / error; null = never pushed / unknown)
            $b2cDv = $shopifyB2cDataBySku[$skuClean] ?? $shopifyB2cDataBySku[$sku] ?? null;
            $row['S_STATUS'] = null;
            if ($b2cDv && is_array($b2cDv->value)) {
                $b2cSt = $b2cDv->value['SPRICE_STATUS'] ?? null;
                if (in_array($b2cSt, ['pushed', 'error'], true)) {
                    $row['S_STATUS'] = $b2cSt;
                }
            }

            $this->applyAmazonTabulatorFbaSuffixZeroFbaInvAdPauseOverlay($row);

            $result[] = (object) $row;
        }

        $fbaInvPositiveCount = collect($result)->filter(function ($r) {
            return (int) ($r->FBA_Quantity ?? 0) > 0;
        })->count();
        Log::debug('Amazon tabulator getViewAmazonData: FBA_Quantity snapshot (product rows, before parent summaries)', [
            'rows_with_fba_inv_gt_0' => $fbaInvPositiveCount,
            'product_rows' => count($result),
        ]);

        // Parent-wise grouping
        $groupedByParent = collect($result)->groupBy('Parent');
        $parentSkus = $groupedByParent->keys()->filter(function ($p) { return $p !== '' && $p !== null; })->values()->toArray();
        $parentVariations = [];
        $parentNrData = []; // NRL/NRA from amazon_data_view for parent rows (sku = "PARENT {parent}")
        if (!empty($parentSkus)) {
            $parentViewRows = AmazonDataView::whereIn('sku', $parentSkus)->get();
            foreach ($parentViewRows as $r) {
                $val = is_array($r->value) ? $r->value : (is_string($r->value) ? json_decode($r->value, true) : []);
                $parentVariations[$r->sku] = (isset($val['variation']) && in_array($val['variation'], ['red', 'green'], true)) ? $val['variation'] : 'red';
            }
            // Load NRL/NRA for parent rows (stored under sku "PARENT {parent}")
            $parentRowSkus = array_map(function ($p) { return 'PARENT ' . $p; }, $parentSkus);
            $parentDataViewRows = AmazonDataView::whereIn('sku', $parentRowSkus)->get();
            foreach ($parentDataViewRows as $r) {
                $val = is_array($r->value) ? $r->value : (is_string($r->value) ? json_decode($r->value, true) : []);
                if (is_array($val)) {
                    $parentNrData[$r->sku] = [
                        'NRL' => trim((string) ($val['NRL'] ?? '')),
                        'NRA' => trim((string) ($val['NRA'] ?? '')),
                    ];
                }
            }
        }
        $finalResult = [];

        foreach ($groupedByParent as $parent => $rows) {
            foreach ($rows as $row) {
                $finalResult[] = $row;
            }

            if (empty($parent)) {
                continue;
            }

            $sumRow = [
                '(Child) sku' => 'PARENT ' . $parent,
                'Parent' => $parent,
                'parent' => $parent,
                'INV' => $rows->sum('INV'),
                'INV_AMZ' => $rows->sum(function($row) {
                    $val = $row->INV_AMZ ?? 0;
                    return is_numeric($val) ? (int)$val : 0;
                }),
                'FBA_Quantity' => $rows->sum(function ($row) {
                    $v = $row->FBA_Quantity ?? 0;
                    return is_numeric($v) ? (int) $v : 0;
                }),
                'FBA_SKU' => (function () use ($rows) {
                    foreach ($rows as $row) {
                        $childSku = strtoupper((string) ($row->{'(Child) sku'} ?? ''));
                        $fbaSku = strtoupper((string) ($row->FBA_SKU ?? ''));
                        if (str_contains($childSku, 'FBA') || str_contains($fbaSku, 'FBA')) {
                            $seller = (string) ($row->FBA_SKU ?? '');
                            return $seller !== '' ? $seller : 'FBA';
                        }
                    }
                    return '';
                })(),
                'is_missing_amazon' => false, // Parent rows are never missing
                'L30' => $rows->sum('L30'),
                'price' => '',
                'price_lmpa' => '',
                'A_L30' => $rows->sum('A_L30'),
                'A_L7' => $rows->sum('A_L7'),
                'units_ordered_l60' => $rows->sum('units_ordered_l60'),
                'Sess30' => $rows->sum('Sess30'),
                'Sess7' => $rows->sum('Sess7'),
                'sessions_l60' => $rows->sum('sessions_l60'),
                'CVR_L30' => '',
                'CVR_L7' => '',
                'CVR_L60' => '',
                'NRL' => '', // Set below from children
                'NRA' => '', // Set below from children
                'FBA' => null,
                'shopify_id' => null,
                'SPRICE' => '',
                'Spft%' => '',
                'SROI' => '',
                'SGPFT' => '',
                'ad_spend' => '',
                'Listed' => null,
                'Live' => null,
                'APlus' => null,
                'js_comp_manual_api_link' => '',
                'js_comp_manual_link' => '',
                'image_path' => '',
                'Total_pft' => round($rows->sum('Total_pft'), 2),
                'T_Sale_l30' => round($rows->sum('T_Sale_l30'), 2),
                'PFT_percentage' => '',
                'ROI_percentage' => '',
                'T_COGS' => round($rows->sum('T_COGS'), 2),
                'scout_data' => null,
                'percentage' => $percentage,
                'LP_productmaster' => '',
                'Ship_productmaster' => '',
                'GPFT%' => $rows->count() > 0 ? round($rows->avg('GPFT%'), 2) : 0,
                'GROI%' => $rows->count() > 0 ? round($rows->avg('GROI%'), 2) : 0,
                'ROI_percentage' => $rows->count() > 0 ? round($rows->avg('ROI_percentage'), 2) : 0,
                'is_parent_summary' => true,
                'ad_updates' => $adUpdates,
                'variation_display' => $parentVariations[$parent] ?? 'red'
            ];
            // Parent NRL/NRA: prefer stored values from amazon_data_view (sku = "PARENT {parent}"); else derive from children
            $parentRowSku = 'PARENT ' . $parent;
            $stored = $parentNrData[$parentRowSku] ?? null;
            if ($stored && ($stored['NRL'] !== '' || $stored['NRA'] !== '')) {
                $sumRow['NRL'] = $stored['NRL'] !== '' ? $stored['NRL'] : 'REQ';
                $sumRow['NRA'] = $stored['NRA'] !== '' ? $stored['NRA'] : 'RA';
                $sumRow['NR'] = ($sumRow['NRL'] === 'NRL') ? 'NR' : 'REQ';
            } else {
                // Fallback: from children - if any child is NRL/NRA, parent is NRL/NRA; else REQ/RA
                $hasNraChild = $rows->contains(function ($r) {
                    $nra = trim((string) (is_array($r) ? ($r['NRA'] ?? '') : ($r->NRA ?? '')));
                    if ($nra !== '') {
                        return $nra === 'NRA';
                    }
                    $nrl = trim((string) (is_array($r) ? ($r['NRL'] ?? 'REQ') : ($r->NRL ?? 'REQ')));
                    return $nrl === 'NRL';
                });
                $sumRow['NRL'] = $hasNraChild ? 'NRL' : 'REQ';
                $sumRow['NRA'] = $hasNraChild ? 'NRA' : 'RA';
                $sumRow['NR'] = $hasNraChild ? 'NR' : 'REQ';
            }

            // Price for parent (average of children with prices)
            $childPrices = $rows->pluck('price')->filter(fn($p) => is_numeric($p) && $p > 0);
            $sumRow['price'] = $childPrices->count() > 0 ? round($childPrices->avg(), 2) : 0;

            $finalResult[] = (object) $sumRow;
        }

        // Save PFT% and ROI_percentage to AmazonDataView value column after processing all rows
        foreach ($result as $row) {
            try {
                if (!isset($row->{'(Child) sku'}) || empty($row->{'(Child) sku'})) {
                    continue;
                }
                
                $sku = $row->{'(Child) sku'};
                
                // Skip parent rows
                if (strpos($sku, 'PARENT ') === 0) {
                    continue;
                }
                
                $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($amazonDataView->value)
                    ? $amazonDataView->value
                    : (json_decode($amazonDataView->value ?? '{}', true) ?? []);
                
                $merged = array_merge($existing, [
                    'PFT' => $row->{'PFT%'} ?? 0,
                    'ROI' => $row->ROI_percentage ?? 0,
                    'GPFT' => $row->{'GPFT%'} ?? 0,
                ]);
                
                $amazonDataView->value = $merged;
                $amazonDataView->save();
            } catch (\Exception $e) {
                // Continue processing other SKUs if one fails
                Log::error('Error saving PFT/ROI for SKU: ' . ($sku ?? 'unknown') . ' - ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $finalResult,
            'status' => 200,
        ]);
    }



    public function updateAllAmazonSkus(Request $request)
    {
        try {
            $type = $request->input('type');
            $value = $request->input('value');

            // Current record fetch
            $marketplace = MarketplacePercentage::where('marketplace', 'Amazon')->first();

            $percent = $marketplace->percentage ?? 0;
            $adUpdates = $marketplace->ad_updates ?? 0;

            // Handle percentage update
            if ($type === 'percentage') {
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return response()->json(['status' => 400, 'message' => 'Invalid percentage value'], 400);
                }
                $percent = $value;
            }

            // Handle ad_updates update
            if ($type === 'ad_updates') {
                if (!is_numeric($value) || $value < 0) {
                    return response()->json(['status' => 400, 'message' => 'Invalid ad_updates value'], 400);
                }
                $adUpdates = $value;
            }

            // Save both fields
            $marketplace = MarketplacePercentage::updateOrCreate(
                ['marketplace' => 'Amazon'],
                [
                    'percentage' => $percent,
                    'ad_updates' => $adUpdates,
                ]
            );

            // Clear the cache
            Cache::forget('amazon_marketplace_percentage');
            Cache::forget('amazon_marketplace_ad_updates');

            return response()->json([
                'status' => 200,
                'message' => ucfirst($type) . ' updated successfully!',
                'data' => [
                    'percentage' => $marketplace->percentage,
                    'ad_updates' => $marketplace->ad_updates
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error updating Amazon marketplace values',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function saveNrToDatabase(Request $request) {
        $sku = $request->input('sku');
        $nr = $request->input('nr');     
        $fbaInput = $request->input('fba');   
        $spend = $request->input('spend');    
        $tpft = $request->input('tpft');      
        $spend_l30 = $request->input('spend_l30');

        if (!$sku) {
            return response()->json(['error' => 'SKU is required.'], 400);
        }

        // Fetch or create the record
        $amazonDataView = \App\Models\AmazonDataView::firstOrNew(['sku' => $sku]);

        // Decode existing value JSON
        $existing = is_array($amazonDataView->value)
            ? $amazonDataView->value
            : (json_decode($amazonDataView->value ?? '{}', true));

        // Handle NR - accept NRL, REQ, or JSON format with NR/REQ (for backward compatibility)
        if ($nr !== null) {
            $nrValue = $nr;
            
            // Handle JSON format from frontend: {"NR":"NR"} or {"NR":"REQ"}
            if (is_string($nr) && (strpos($nr, '{') === 0 || strpos($nr, '[') === 0)) {
                $decoded = json_decode($nr, true);
                if (is_array($decoded) && isset($decoded['NR'])) {
                    $nrValue = $decoded['NR']; // Extract 'NR' or 'REQ' from JSON
                }
            }
            
            // Map values: 'NR' -> 'NRL', 'REQ' -> 'REQ', 'NRL' -> 'NRL'
            if ($nrValue === 'NR' || $nrValue === 'NRL') {
                $existing['NRL'] = 'NRL';
                $existing['NRA'] = ''; // Clear NRA
            } elseif ($nrValue === 'REQ') {
                $existing['NRL'] = 'REQ';
                $existing['NRA'] = ''; // Clear NRA
            } elseif ($nrValue === '') {
                // Clear both when empty
                $existing['NRL'] = '';
                $existing['NRA'] = '';
            }
            // Ignore any other values (numbers, etc.)
        }

        // Handle FBA
        if ($fbaInput) {
            $fba = is_array($fbaInput) ? $fbaInput : json_decode($fbaInput, true);
            if (!is_array($fba) || !isset($fba['FBA'])) {
                return response()->json(['error' => 'Invalid FBA format.'], 400);
            }
            $existing['FBA'] = $fba['FBA'];
        }

        // Handle Spend
        if (!is_null($spend)) {
            $existing['Spend'] = $spend;
        }

        // Handle tpft (total profit percentage)
        if (!is_null($tpft)) {
            $existing['TPFT'] = $tpft;
        }

        // Handle spend_l30
        if (!is_null($spend_l30)) {
            $existing['Spend_L30'] = $spend_l30;
        }

        $newValueJson = json_encode($existing);
        if ($amazonDataView->value !== $newValueJson) {
            $amazonDataView->value = $existing;
            $amazonDataView->save();
        }

        // Create a user-friendly message based on what was updated
        $message = "Data updated successfully";
        if ($nr !== null) {
            $message = $nr === 'NRL' ? "NRL updated" : ($nr === 'REQ' ? "REQ updated" : "NR cleared");
        }

        return response()->json(['success' => true, 'data' => $amazonDataView, 'message' => $message]);
    }

    /**
     * Save variation display (red/green) for a SKU to amazon_data_view.
     * Used by the Variation column dot click in the Amazon tabulator.
     */
    public function saveVariationToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $variation = $request->input('variation'); // 'red' or 'green'

        if (!$sku) {
            return response()->json(['success' => false, 'error' => 'SKU is required.'], 400);
        }
        if (!in_array($variation, ['red', 'green'], true)) {
            return response()->json(['success' => false, 'error' => 'Variation must be red or green.'], 400);
        }

        $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($amazonDataView->value)
            ? $amazonDataView->value
            : (json_decode($amazonDataView->value ?? '{}', true) ?? []);
        $existing['variation'] = $variation;
        $amazonDataView->value = $existing;
        $amazonDataView->save();

        return response()->json(['success' => true, 'variation' => $variation, 'message' => 'Variation saved.']);
    }

    public function saveBidCap(Request $request)
    {
        Log::info('saveBidCap called', [
            'all_input' => $request->all(),
            'sku' => $request->input('sku'),
            'bid_cap' => $request->input('bid_cap')
        ]);

        $sku = $request->input('sku') ? strtoupper($request->input('sku')) : null;
        $bidCap = $request->input('bid_cap');
        $userId = auth()->id();

        if (!$sku) {
            Log::warning('saveBidCap: SKU is missing', ['request' => $request->all()]);
            return response()->json(['success' => false, 'message' => 'SKU is required. Received: ' . json_encode($request->all())], 400);
        }

        try {
            // Use dedicated amazon_bid_caps table
            $amazonBidCap = AmazonBidCap::updateOrCreate(
                ['sku' => $sku],
                [
                    'bid_cap' => $bidCap !== null && $bidCap !== '' ? floatval($bidCap) : null,
                    'user_id' => $userId,
                    'last_updated_at' => now()
                ]
            );

            // Get user name for response
            $userName = $userId ? ($amazonBidCap->user->name ?? 'Unknown') : 'Guest';

            Log::info('Bid Cap saved', [
                'sku' => $sku, 
                'bid_cap' => $bidCap,
                'user_id' => $userId,
                'user_name' => $userName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bid Cap saved successfully',
                'bid_cap' => $bidCap,
                'user_name' => $userName,
                'updated_at' => $amazonBidCap->last_updated_at->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving Bid Cap', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving Bid Cap: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveSpriceToDatabase(Request $request)
    {
        Log::info('Saving Amazon pricing data', $request->all());
        $sku = strtoupper($request->input('sku'));
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        // Get current marketplace percentage
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 1;
        Log::info('Using percentage', ['percentage' => $percentage]);

        // Get ProductMaster for lp and ship
        $pm = ProductMaster::where('sku', $sku)->first();
        if (!$pm) {
            return response()->json(['error' => 'SKU not found in product master.'], 404);
        }

        // Extract lp and ship
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

        $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
        Log::info('LP and Ship', ['lp' => $lp, 'ship' => $ship]);

        // Calculate SGPFT first (using 0.80 for Amazon)
        $spriceFloat = floatval($sprice);
        $sgpft = $spriceFloat > 0 ? round((($spriceFloat * 0.80 - $ship - $lp) / $spriceFloat) * 100, 2) : 0;
        
        // SPFT = SGPFT (without AD% deduction)
        $spft = $sgpft;
        
        // SROI = ((SPRICE * 0.80 - ship - lp) / lp) * 100 (without AD% deduction)
        $sroi = round(
            $lp > 0 ? (($spriceFloat * 0.80 - $ship - $lp) / $lp) * 100 : 0,
            2
        );
        
        Log::info('Calculated values', ['sprice' => $spriceFloat, 'sgpft' => $sgpft, 'spft' => $spft, 'sroi' => $sroi]);

        $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($amazonDataView->value)
            ? $amazonDataView->value
            : (json_decode($amazonDataView->value ?? '{}', true) ?? []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceFloat,
            'SPFT' => $spft,
            'SROI' => $sroi,
            'SGPFT' => $sgpft,
        ]);

        $amazonDataView->value = $merged;
        $amazonDataView->save();
        Log::info('Data saved successfully', ['sku' => $sku]);

        return response()->json([
            'message' => 'Data saved successfully.',
            'data' => $spriceFloat,
            'spft_percent' => $spft,
            'sroi_percent' => $sroi,
            'sgpft_percent' => $sgpft
        ]);
    }

    /**
     * Clear SPRICE for selected SKUs
     */
    public function clearAmazonSprice(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            
            if (empty($updates)) {
                return response()->json(['error' => 'No SKUs provided'], 400);
            }

            $clearedCount = 0;
            
            foreach ($updates as $update) {
                $sku = strtoupper($update['sku'] ?? '');
                
                if (empty($sku)) {
                    continue;
                }
                
                // Find the amazon_data_view record
                $amazonDataView = AmazonDataView::where('sku', $sku)->first();
                
                if ($amazonDataView) {
                    // Decode value column safely
                    $existing = is_array($amazonDataView->value)
                        ? $amazonDataView->value
                        : (json_decode($amazonDataView->value ?? '{}', true) ?? []);
                    
                    // Clear SPRICE related fields
                    $existing['SPRICE'] = null;
                    $existing['SPFT'] = null;
                    $existing['SROI'] = null;
                    $existing['SGPFT'] = null;
                    $existing['SPRICE_STATUS'] = null;
                    
                    $amazonDataView->value = $existing;
                    $amazonDataView->save();
                    
                    $clearedCount++;
                }
            }
            
            Log::info('SPRICE cleared successfully', ['count' => $clearedCount]);
            
            return response()->json([
                'success' => true,
                'message' => "SPRICE cleared for {$clearedCount} SKU(s)",
                'cleared_count' => $clearedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error clearing SPRICE', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to clear SPRICE: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Push SPRICE to Amazon (Listings Items PATCH).
     *
     * Resolves seller MSKU from `amazon_datsheets` when:
     * - Request includes `asin` (e.g. B099LLZP2T), or
     * - `sku` looks like a typical retail ASIN (B + 9 alphanumeric) and matches a row's `asin` column.
     * The Listings API is then called with the datasheet `sku` (seller SKU) when lookup succeeds. If `asin` is
     * sent but the row is missing / has no SKU and a child `sku` is present, the child SKU is used (same as before).
     * SPRICE status is saved on the tabulator child SKU when provided, otherwise on the resolved seller SKU.
     */
    public function applyAmazonPrice(Request $request)
    {
        $rawChildSkuOriginal = trim(str_replace("\xc2\xa0", ' ', (string) $request->input('sku', '')));
        $rowChildSku = strtoupper($rawChildSkuOriginal);
        $asinParam = trim((string) $request->input('asin', ''));
        $price = $request->input('price');

        // Log the exact price received from the request for debugging
        Log::info('applyAmazonPrice: Request received', [
            'sku' => $rowChildSku,
            'asin' => $asinParam,
            'price_raw' => $price,
            'price_type' => gettype($price),
            'request_all' => $request->all()
        ]);

        if ($rowChildSku === '' && $asinParam === '') {
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'SKU or ASIN is required.',
                ]],
            ], 400);
        }

        $skuForAmazon = $rowChildSku !== '' ? $rowChildSku : '';
        $statusSku = $rowChildSku;
        $amazonSkuLockedFromAsin = false;

        if ($asinParam !== '') {
            $resolved = $this->sellerSkuFromAmazonDatasheetByAsin($asinParam);
            if ($resolved !== null && $resolved !== '') {
                // Stale/wrong ASIN on the row must not override Seller Central MSKU for a different listing.
                if ($rawChildSkuOriginal !== '' && ! $this->datasheetMskuMatchesChildSku($resolved, $rawChildSkuOriginal)) {
                    Log::warning('applyAmazonPrice: ignoring request ASIN — datasheet row MSKU does not match this child SKU (fix asin in amazon_datsheets)', [
                        'asin' => $asinParam,
                        'child_sku' => $rawChildSkuOriginal,
                        'asin_row_msku' => $resolved,
                    ]);
                } else {
                    $skuForAmazon = trim($resolved);
                    $amazonSkuLockedFromAsin = true;
                    $statusSku = $rowChildSku !== '' ? $rowChildSku : strtoupper(trim($resolved));
                }
            } elseif ($rowChildSku === '') {
                // ASIN-only request: must resolve from datasheet
                return response()->json([
                    'errors' => [[
                        'code' => 'InvalidInput',
                        'message' => 'ASIN not found in amazon_datsheets or row has no seller SKU.',
                    ]],
                ], 400);
            } else {
                // Child SKU present but datasheet ASIN lookup failed — behave like before (push by child SKU)
                Log::warning('applyAmazonPrice: ASIN not resolved from amazon_datsheets, using child SKU for Amazon API', [
                    'asin' => $asinParam,
                    'child_sku' => $rowChildSku,
                ]);
            }
        } elseif ($rowChildSku !== '' && $this->looksLikeTypicalRetailAsin($rowChildSku)) {
            $resolved = $this->sellerSkuFromAmazonDatasheetByAsin($rowChildSku);
            if ($resolved !== null && $resolved !== '') {
                $skuForAmazon = trim($resolved);
                $amazonSkuLockedFromAsin = true;
                $statusSku = $rowChildSku !== '' ? $rowChildSku : strtoupper(trim($resolved));
            }
        }

        // Product Master / grid SKU often differs from Seller Central MSKU (spaces, casing). Prefer exact `sku` from sync.
        if (! $amazonSkuLockedFromAsin && $rawChildSkuOriginal !== '') {
            $fromSheet = $this->sellerSkuFromAmazonDatasheetByProductKey($rawChildSkuOriginal);
            if ($fromSheet !== null && $fromSheet !== '') {
                $skuForAmazon = $fromSheet;
            }
        }

        if ($skuForAmazon === '') {
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'Could not determine seller SKU for Amazon (missing sku / asin).',
                ]],
            ], 400);
        }

        // Validate price
        if ($price === null || $price === '') {
            $this->saveSpriceStatus($statusSku, 'error');
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'Price is required.'
                ]]
            ], 400);
        }

        // Convert to float and validate
        $priceFloat = is_numeric($price) ? (float) $price : null;
        
        if ($priceFloat === null || $priceFloat <= 0) {
            $this->saveSpriceStatus($statusSku, 'error');
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'Price must be a valid number greater than 0.'
                ]]
            ], 400);
        }

        // Validate price range (reasonable bounds: 0.01 to 999999.99)
        if ($priceFloat < 0.01 || $priceFloat > 999999.99) {
            $this->saveSpriceStatus($statusSku, 'error');
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidInput',
                    'message' => 'Price must be between $0.01 and $999,999.99.'
                ]]
            ], 400);
        }

        // Round price to 2 decimal places
        $priceFloat = round($priceFloat, 2);

        try {
            $service = new AmazonSpApiService();
            $result = $service->updateAmazonPriceUS($skuForAmazon, $priceFloat);

            // Check if the response indicates errors
            if (isset($result['errors']) && !empty($result['errors'])) {
                // Save error status
                $this->saveSpriceStatus($statusSku, 'error');
                
                // Log the error for debugging
                Log::error('Amazon price update failed', [
                    'status_sku' => $statusSku,
                    'amazon_api_sku' => $skuForAmazon,
                    'asin_param' => $asinParam,
                    'price' => $priceFloat,
                    'errors' => $result['errors']
                ]);
                
                return response()->json($result);
            }

            // Check if we should also update Amazon's minimum price constraint
            $updateMinPriceConstraint = $request->input('update_amazon_min_price', false);
            
            Log::info('Checking Amazon min price update parameter', [
                'update_amazon_min_price_param' => $updateMinPriceConstraint,
                'request_all' => $request->all()
            ]);
            
            if ($updateMinPriceConstraint) {
                Log::info('Updating Amazon minimum price constraint', [
                    'sku' => $skuForAmazon,
                    'min_price' => $priceFloat
                ]);
                
                try {
                    $constraintResult = $service->updateCompetitivePriceConstraints($skuForAmazon, $priceFloat);
                    
                    if (isset($constraintResult['errors']) && !empty($constraintResult['errors'])) {
                        Log::warning('Amazon minimum price constraint update failed (non-blocking)', [
                            'sku' => $skuForAmazon,
                            'errors' => $constraintResult['errors']
                        ]);
                        // Don't fail the whole operation, just log the warning
                    } else {
                        Log::info('Amazon minimum price constraint updated successfully', [
                            'sku' => $skuForAmazon,
                            'min_price' => $priceFloat
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Amazon minimum price constraint update exception (non-blocking)', [
                        'sku' => $skuForAmazon,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Check if we should push to Shopify (default true for backward compatibility)
            $pushShopify = $request->input('push_shopify', true);
            
            $responseData = array_merge(is_array($result) ? $result : [], [
                'amazon_api_sku' => $skuForAmazon,
                'asin_used' => $asinParam !== '' ? strtoupper(str_replace([' ', "\xc2\xa0"], '', $asinParam)) : null,
            ]);
            
            if ($pushShopify) {
                // Match Pricing Master CVR Shopify B2C behavior exactly by routing through
                // CvrMasterController::pushPriceToAmazon(marketplace=shopifyb2c).
                Log::info('applyAmazonPrice: About to push to Shopify', [
                    'sku' => $statusSku !== '' ? $statusSku : strtoupper(trim($skuForAmazon)),
                    'price_being_sent' => $priceFloat,
                    'price_original_from_request' => $price
                ]);
                
                $shopifyPush = $this->pushShopifyB2CViaCvrController(
                    $statusSku !== '' ? $statusSku : strtoupper(trim($skuForAmazon)),
                    $priceFloat
                );
                
                // Save status based on Shopify push result - only mark as pushed if Shopify succeeds
                $this->saveSpriceStatus($statusSku, ($shopifyPush['ok'] ?? false) ? 'pushed' : 'error');
                
                // Save Shopify status separately
                $this->saveShopifyStatus($statusSku, ($shopifyPush['ok'] ?? false) ? 'pushed' : 'error');
                
                Log::info('Amazon price update successful with Shopify', [
                    'status_sku' => $statusSku,
                    'amazon_api_sku' => $skuForAmazon,
                    'asin_param' => $asinParam,
                    'price' => $priceFloat,
                    'shopify_push_ok' => $shopifyPush['ok'] ?? false,
                    'shopify_push_message' => $shopifyPush['message'] ?? null,
                ]);
                
                $responseData['shopify_push'] = $shopifyPush;
                $responseData['S_STATUS'] = ($shopifyPush['ok'] ?? false) ? 'pushed' : 'error';
            } else {
                // Only save Amazon status when Shopify is skipped
                $this->saveSpriceStatus($statusSku, 'pushed');
                
                Log::info('Amazon price update successful (Shopify skipped)', [
                    'status_sku' => $statusSku,
                    'amazon_api_sku' => $skuForAmazon,
                    'asin_param' => $asinParam,
                    'price' => $priceFloat,
                ]);
                
                $responseData['SPRICE_STATUS'] = 'pushed';
            }
            
            return response()->json($responseData);
        } catch (\Exception $e) {
            // Save error status
            $this->saveSpriceStatus($statusSku, 'error');
            Log::error('Exception in applyAmazonPrice', [
                'status_sku' => $statusSku,
                'amazon_api_sku' => $skuForAmazon,
                'price' => $priceFloat,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'errors' => [[
                    'code' => 'Exception',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ]]
            ], 500);
        }
    }

    /**
     * Push price to Shopify B2C independently
     */
    public function pushShopifyB2CPrice(Request $request)
    {
        $sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', (string) $request->input('sku', ''))));
        $price = $request->input('price');
        
        // Log request
        Log::info('pushShopifyB2CPrice: Request received', [
            'sku' => $sku,
            'price' => $price
        ]);
        
        if ($sku === '') {
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'SKU is required.']]
            ], 400);
        }
        
        if ($price === null || $price === '') {
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'Price is required.']]
            ], 400);
        }
        
        $priceFloat = is_numeric($price) ? (float) $price : null;
        
        if ($priceFloat === null || $priceFloat <= 0 || $priceFloat < 0.01 || $priceFloat > 999999.99) {
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'Price must be between $0.01 and $999,999.99.']]
            ], 400);
        }
        
        $priceFloat = round($priceFloat, 2);
        
        try {
            $shopifyPush = $this->pushShopifyB2CViaCvrController($sku, $priceFloat);
            
            // Save Shopify status separately
            $this->saveShopifyStatus($sku, ($shopifyPush['ok'] ?? false) ? 'pushed' : 'error');
            
            Log::info('Shopify B2C price push result', [
                'sku' => $sku,
                'price' => $priceFloat,
                'shopify_push_ok' => $shopifyPush['ok'] ?? false,
                'shopify_push_message' => $shopifyPush['message'] ?? null,
            ]);
            
            return response()->json([
                'shopify_push' => $shopifyPush,
                'S_STATUS' => ($shopifyPush['ok'] ?? false) ? 'pushed' : 'error',
            ]);
        } catch (\Exception $e) {
            $this->saveShopifyStatus($sku, 'error');
            Log::error('Exception in pushShopifyB2CPrice', [
                'sku' => $sku,
                'price' => $priceFloat,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'errors' => [['code' => 'Exception', 'message' => 'An error occurred: ' . $e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Save Shopify status to database
     */
    private function saveShopifyStatus($sku, $status)
    {
        try {
            $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);
            $existing = is_array($amazonDataView->value) ? $amazonDataView->value : (json_decode($amazonDataView->value ?? '{}', true) ?? []);
            if (!is_array($existing)) $existing = [];
            $existing['S_STATUS'] = $status;
            $existing['S_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            $amazonDataView->value = $existing;
            $amazonDataView->save();
            Log::info('Shopify status saved', ['sku' => $sku, 'status' => $status]);
        } catch (\Exception $e) {
            Log::error('Failed to save Shopify status', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Push price to ProLightSounds (PLS) Shopify store
     */
    public function pushPlsPrice(Request $request)
    {
        $sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', (string) $request->input('sku', ''))));
        $price = $request->input('price');
        
        Log::info('pushPlsPrice: Request received', [
            'sku' => $sku,
            'price' => $price
        ]);
        
        if ($sku === '') {
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'SKU is required.']]
            ], 400);
        }
        
        if ($price === null || $price === '') {
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'Price is required.']]
            ], 400);
        }
        
        $priceFloat = is_numeric($price) ? (float) $price : null;
        
        if ($priceFloat === null || $priceFloat <= 0 || $priceFloat < 0.01 || $priceFloat > 999999.99) {
            return response()->json([
                'errors' => [['code' => 'InvalidInput', 'message' => 'Price must be between $0.01 and $999,999.99.']]
            ], 400);
        }
        
        $priceFloat = round($priceFloat, 2);
        
        try {
            $plsPush = $this->pushPlsViaCvrController($sku, $priceFloat);
            
            // Save PLS status separately
            $this->savePlsStatus($sku, ($plsPush['ok'] ?? false) ? 'pushed' : 'error');
            
            Log::info('ProLightSounds price push result', [
                'sku' => $sku,
                'price' => $priceFloat,
                'pls_push_ok' => $plsPush['ok'] ?? false,
                'pls_push_message' => $plsPush['message'] ?? null,
            ]);
            
            return response()->json([
                'pls_push' => $plsPush,
                'PLS_STATUS' => ($plsPush['ok'] ?? false) ? 'pushed' : 'error',
            ]);
        } catch (\Exception $e) {
            $this->savePlsStatus($sku, 'error');
            Log::error('Exception in pushPlsPrice', [
                'sku' => $sku,
                'price' => $priceFloat,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'errors' => [['code' => 'Exception', 'message' => 'An error occurred: ' . $e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Push price to ProLightSounds via CVR Master Controller
     */
    private function pushPlsViaCvrController(string $sku, float $price): array
    {
        try {
            $req = Request::create('/cvr-master-push-price', 'POST', [
                'sku' => strtoupper(trim($sku)),
                'price' => round($price, 2),
                'marketplace' => 'pls',
            ]);

            $res = app(CvrMasterController::class)->pushPriceToAmazon($req);
            $http = method_exists($res, 'getStatusCode') ? (int) $res->getStatusCode() : 200;
            $payload = [];
            if (method_exists($res, 'getContent')) {
                $raw = $res->getContent();
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    $payload = is_array($decoded) ? $decoded : [];
                }
            }
            if ($payload === [] && method_exists($res, 'getData')) {
                $d = $res->getData(true);
                $payload = is_array($d) ? $d : (is_object($d) ? json_decode(json_encode($d), true) : []);
            }
            $ok = !empty($payload['success']);
            if ($http >= 400) {
                $ok = false;
            }

            return [
                'ok' => $ok,
                'message' => $payload['message'] ?? ($ok ? 'ProLightSounds push successful.' : 'ProLightSounds push failed.'),
                'status_code' => $http,
                'errors' => $payload['errors'] ?? null,
                'data' => $payload['data'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Amazon tab -> PLS push exception', [
                'sku' => $sku,
                'price' => $price,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'ProLightSounds push exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Save ProLightSounds status to database
     */
    private function savePlsStatus($sku, $status)
    {
        try {
            $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);
            $existing = is_array($amazonDataView->value) ? $amazonDataView->value : (json_decode($amazonDataView->value ?? '{}', true) ?? []);
            if (!is_array($existing)) $existing = [];
            $existing['PLS_STATUS'] = $status;
            $existing['PLS_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            $amazonDataView->value = $existing;
            $amazonDataView->save();
            Log::info('PLS status saved', ['sku' => $sku, 'status' => $status]);
        } catch (\Exception $e) {
            Log::error('Failed to save PLS status', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    /**
     * US retail ASINs are commonly "B" + nine alphanumeric characters (avoids treating arbitrary 10-char SKUs as ASINs).
     */
    private function looksLikeTypicalRetailAsin(string $s): bool
    {
        $n = strtoupper(str_replace([' ', "\xc2\xa0"], '', trim($s)));

        return (bool) preg_match('/^B[0-9A-Z]{9}$/', $n);
    }

    /**
     * Resolve seller SKU from `amazon_datsheets` using the ASIN column (case-insensitive, spaces stripped).
     */
    private function sellerSkuFromAmazonDatasheetByAsin(string $asinLike): ?string
    {
        $n = strtoupper(str_replace([' ', "\xc2\xa0"], '', trim($asinLike)));
        if ($n === '' || strlen($n) !== 10 || ! ctype_alnum($n)) {
            return null;
        }

        $row = AmazonDatasheet::query()
            ->whereRaw('UPPER(REPLACE(TRIM(COALESCE(asin, "")), " ", "")) = ?', [$n])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->first();

        if (! $row) {
            return null;
        }

        $sku = trim((string) ($row->sku ?? ''));

        return $sku !== '' ? $sku : null;
    }

    /**
     * Match Product Master / grid key to `amazon_datsheets.sku` (spaces + case insensitive) and return the stored MSKU string.
     */
    private function sellerSkuFromAmazonDatasheetByProductKey(string $productOrGridSku): ?string
    {
        return AmazonDatasheet::resolveSellerMskuByProductKey($productOrGridSku);
    }

    /**
     * True when MSKU from an ASIN datasheet row is the same listing as the grid child (spaces / case insensitive).
     */
    private function datasheetMskuMatchesChildSku(string $mskuFromAsinRow, string $rawChildSku): bool
    {
        $a = strtoupper(str_replace([' ', "\xc2\xa0"], '', trim($mskuFromAsinRow)));
        $b = strtoupper(str_replace([' ', "\xc2\xa0"], '', trim($rawChildSku)));
        if ($a === $b) {
            return true;
        }
        $spacedEq = strtoupper(preg_replace('/\s+/u', ' ', trim($mskuFromAsinRow)))
            === strtoupper(preg_replace('/\s+/u', ' ', trim($rawChildSku)));
        if ($spacedEq) {
            return true;
        }
        $fromKey = $this->sellerSkuFromAmazonDatasheetByProductKey($rawChildSku);
        if ($fromKey !== null && $fromKey !== '') {
            $k = strtoupper(str_replace([' ', "\xc2\xa0"], '', trim($fromKey)));

            return $k === $a;
        }

        return false;
    }

    /**
     * Use CVR controller's Shopify B2C push flow so Amazon tabulator and pricing-master-cvr stay aligned.
     */
    private function pushShopifyB2CViaCvrController(string $sku, float $price): array
    {
        try {
            $req = Request::create('/cvr-master-push-price', 'POST', [
                'sku' => strtoupper(trim($sku)),
                'price' => round($price, 2),
                'marketplace' => 'shopifyb2c',
            ]);

            $res = app(CvrMasterController::class)->pushPriceToAmazon($req);
            $http = method_exists($res, 'getStatusCode') ? (int) $res->getStatusCode() : 200;
            $payload = [];
            if (method_exists($res, 'getContent')) {
                $raw = $res->getContent();
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    $payload = is_array($decoded) ? $decoded : [];
                }
            }
            if ($payload === [] && method_exists($res, 'getData')) {
                $d = $res->getData(true);
                $payload = is_array($d) ? $d : (is_object($d) ? json_decode(json_encode($d), true) : []);
            }
            $ok = !empty($payload['success']);
            if ($http >= 400) {
                $ok = false;
            }

            return [
                'ok' => $ok,
                'message' => $payload['message'] ?? ($ok ? 'Shopify B2C push successful.' : 'Shopify B2C push failed.'),
                'status_code' => $http,
                'errors' => $payload['errors'] ?? null,
                'data' => $payload['data'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Amazon tab -> CVR Shopify B2C push exception', [
                'sku' => $sku,
                'price' => $price,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Shopify B2C push exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Save SPRICE status to database
     * Status: 'pushed', 'applied', 'error'
     */
    private function saveSpriceStatus($sku, $status)
    {
        try {
            $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);
            
            // Decode value column safely
            $existing = is_array($amazonDataView->value)
                ? $amazonDataView->value
                : (json_decode($amazonDataView->value ?? '{}', true) ?? []);
            
            // Save status
            $existing['SPRICE_STATUS'] = $status;
            $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            
            $amazonDataView->value = $existing;
            $amazonDataView->save();
            
            Log::info('SPRICE status saved', ['sku' => $sku, 'status' => $status]);
        } catch (\Exception $e) {
            Log::error('Failed to save SPRICE status', [
                'sku' => $sku,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update SPRICE status manually
     */
    public function updateSpriceStatus(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'status' => 'required|in:pushed,applied,error'
        ]);

        $sku = strtoupper(trim($request->input('sku')));
        $status = $request->input('status');

        $this->saveSpriceStatus($sku, $status);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'status' => $status
        ]);
    }

    public function amazonPriceIncreaseDecrease(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        $adUpdates = Cache::remember('amazon_marketplace_ad_updates', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->ad_updates : 0; // Default to 0 if not set
        });

        return view('market-places.amazon_pricing_increase_decrease', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }

    public function amazonPriceIncrease(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100; // Default to 100 if not set
        });

        $adUpdates = Cache::remember('amazon_marketplace_ad_updates', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Amazon')->first();
            return $marketplaceData ? $marketplaceData->ad_updates : 0; // Default to 0 if not set
        });

        return view('market-places.amazon_pricing_increase', [
            'mode' => $mode,
            'demo' => $demo,
            'amazonPercentage' => $percentage,
            'amazonAdUpdates' => $adUpdates
        ]);
    }


    public function saveManualLink(Request $request)
    {
        $sku = $request->input('sku');
        $type = $request->input('type');
        $value = $request->input('value');

        if (!$sku || !$type) {
            return response()->json(['error' => 'SKU and type are required.'], 400);
        }

        $amazonDataView = AmazonDataView::firstOrNew(['sku' => $sku]);

        // Decode existing value array
        $existing = is_array($amazonDataView->value)
            ? $amazonDataView->value
            : (json_decode($amazonDataView->value, true) ?: []);

        $existing[$type] = $value;

        $amazonDataView->value = $existing;
        $amazonDataView->save();

        return response()->json(['message' => 'Manual link saved successfully.']);
    }

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'Amazon')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live,APlus',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = AmazonDataView::firstOrCreate(
            ['sku' => $request->sku],
            ['value' => []]
        );

        // Decode current value (ensure it's an array)
        $currentValue = is_array($product->value)
            ? $product->value
            : (json_decode($product->value, true) ?? []);

        // Store as actual boolean
        $currentValue[$request->field] = filter_var($request->value, FILTER_VALIDATE_BOOLEAN);

        // Save back to DB
        $product->value = $currentValue;
        $product->save();

        return response()->json(['success' => true]);
    }

    public function importAmazonAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Clean headers
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            unset($rows[0]);

            $allSkus = [];
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $allSkus[] = $row[0];
                }
            }

            $existingSkus = ProductMaster::whereIn('sku', $allSkus)
                ->pluck('sku')
                ->toArray();

            $existingSkus = array_flip($existingSkus);

            $importCount = 0;
            foreach ($rows as $index => $row) {
                if (empty($row[0])) { // Check if SKU is empty
                    continue;
                }

                // Ensure row has same number of elements as headers
                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = array_combine($headers, $rowData);

                if (!isset($data['sku']) || empty($data['sku'])) {
                    continue;
                }

                // Only import SKUs that exist in product_masters (in-memory check)
                if (!isset($existingSkus[$data['sku']])) {
                    continue;
                }

                // Prepare values array
                $values = [];

                // Handle boolean fields
                if (isset($data['listed'])) {
                    $values['Listed'] = filter_var($data['listed'], FILTER_VALIDATE_BOOLEAN);
                }

                if (isset($data['live'])) {
                    $values['Live'] = filter_var($data['live'], FILTER_VALIDATE_BOOLEAN);
                }

                // Update or create record
                AmazonDataView::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['value' => $values]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportAmazonAnalytics()
    {
        $amazonData = AmazonDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($amazonData as $data) {
            $values = is_array($data->value)
                ? $data->value
                : (json_decode($data->value, true) ?? []);

            $sheet->fromArray([
                $data->sku,
                isset($values['Listed']) ? ($values['Listed'] ? 'TRUE' : 'FALSE') : 'FALSE',
                isset($values['Live']) ? ($values['Live'] ? 'TRUE' : 'FALSE') : 'FALSE',
            ], NULL, 'A' . $rowIndex);

            $rowIndex++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Amazon_Analytics_Export_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function downloadSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data
        $sampleData = [
            ['SKU001', 'TRUE', 'FALSE'],
            ['SKU002', 'FALSE', 'TRUE'],
            ['SKU003', 'TRUE', 'TRUE'],
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Amazon_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function amazonTabulatorView(Request $request)
    {
        return view("market-places.amazon_tabulator_view");
    }

    public function amazonPricingCvrTabular(Request $request)
    {
        return view("market-places.amazonpricing_cvr_tabular");
    }

    public function amazonDataJson(Request $request)
    {
        try {
            $response = $this->getViewAmazonData($request);
            $data = json_decode($response->getContent(), true);

            // Auto-save daily summary in background (non-blocking)
            $this->saveDailySummaryIfNeeded($data['data'] ?? []);

            return response()->json([
                'data' => $data['data'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching Amazon data for Tabulator: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getAmazonColumnVisibility(Request $request)
    {
        $userId = auth()->id() ?? 'guest';
        $key = "amazon_sales_tabulator_column_visibility_{$userId}";
        
        $visibility = Cache::get($key, []);
        
        return response()->json($visibility);
    }

    /**
     * Refresh links for Amazon listings (quick method using existing ASIN data)
     */
    public function refreshAmazonLinks(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $updateAll = $request->input('update_all', false);

            if ($updateAll) {
                // Update all SKUs using ASIN from amazon_datsheets
                $skus = ProductMaster::whereNull('deleted_at')
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->where('sku', 'NOT LIKE', '%PARENT%')
                    ->pluck('sku')
                    ->unique()
                    ->values();

                $updated = 0;
                $skipped = 0;

                foreach ($skus as $currentSku) {
                    $result = $this->updateLinksFromAsin($currentSku);
                    if ($result['success']) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => "Updated {$updated} SKUs, {$skipped} skipped (no ASIN)",
                    'updated' => $updated,
                    'skipped' => $skipped
                ]);
            } else {
                if (!$sku) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'SKU is required'
                    ], 400);
                }

                $result = $this->updateLinksFromAsin($sku);
                
                if ($result['success']) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Links updated successfully',
                        'data' => $result['data']
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => $result['message']
                    ], 400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error refreshing Amazon links', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to refresh links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update links from ASIN in amazon_datsheets (fast method, no API calls)
     */
    private function updateLinksFromAsin($sku)
    {
        try {
            $amazonSheet = AmazonDatasheet::where('sku', $sku)->first();
            
            if (!$amazonSheet || !$amazonSheet->asin) {
                return [
                    'success' => false,
                    'message' => 'ASIN not found for this SKU'
                ];
            }

            $asin = $amazonSheet->asin;
            $buyerLink = "https://www.amazon.com/dp/{$asin}";
            $sellerLink = "https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin={$asin}";

            // Update links in amazon_data_view
            $status = AmazonDataView::where('sku', $sku)->first();
            $existing = $status ? $status->value : [];

            $existing['buyer_link'] = $buyerLink;
            $existing['seller_link'] = $sellerLink;

            AmazonDataView::updateOrCreate(
                ['sku' => $sku],
                ['value' => $existing]
            );

            return [
                'success' => true,
                'data' => [
                    'sku' => $sku,
                    'asin' => $asin,
                    'buyer_link' => $buyerLink,
                    'seller_link' => $sellerLink
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error updating links from ASIN', [
                'sku' => $sku,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function exportAmazonPricingCVR(Request $request)
    {
        return $this->amazonDataService->exportPricingCVRToCSV($request);
    }

    public function exportAmazonSpriceUpload(Request $request)
    {
        return $this->amazonDataService->exportSpriceForUpload($request);
    }

    public function downloadAmazonRatingsSample(Request $request)
    {
        return $this->amazonDataService->downloadRatingsSampleTemplate($request);
    }

    public function importAmazonRatings(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        try {
            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $content = preg_replace('/^\x{FEFF}/u', '', $content); // Remove BOM
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
            $csvData = array_map('str_getcsv', explode("\n", $content));
            $csvData = array_filter($csvData, function($row) {
                return count($row) > 0 && !empty(trim(implode('', $row)));
            });
            $header = array_shift($csvData); // Remove header

            $imported = 0;
            $skipped = 0;

            foreach ($csvData as $row) {
                $row = array_map('trim', $row); // Trim all elements
                if (empty($row) || count($row) < 1 || empty($row[0])) {
                    $skipped++;
                    continue;
                }

                if (count($row) !== count($header)) {
                    $skipped++;
                    continue;
                }

                $sku = strtoupper($row[0]);
                $rowData = array_combine($header, $row);
                unset($rowData['sku']);

                AmazonFbmManual::create([
                    'sku' => $sku,
                    'data' => json_encode($rowData)
                ]);

                $imported++;
            }

            return response()->json(['success' => 'Imported ' . $imported . ' ratings successfully' . ($skipped > 0 ? ', skipped ' . $skipped . ' invalid rows' : '')]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error importing ratings: ' . $e->getMessage()]);
        }
    }

    public function getMetricsHistory(Request $request)
    {
        $days = $request->input('days', 7); // Default to last 7 days
        $sku = $request->input('sku'); // Optional SKU filter
        $skus = $request->input('skus'); // Optional array of SKUs to filter (for INV > 0)
        
        // Ensure minimum 7 days if pulling from today
        $minDays = 7;
        if ($days < $minDays) {
            $days = $minDays;
        }
        
        $startDate = \Carbon\Carbon::today()->subDays($days - 1); // -1 to include today
        $endDate = \Carbon\Carbon::today();
        
        $chartData = [];
        $dataByDate = []; // Store data by date for filling gaps
        
        try {
            // Try to use the new table for JSON format data
            $query = \App\Models\AmazonSkuDailyData::where('record_date', '>=', $startDate)
                ->where('record_date', '<=', $endDate)
                ->orderBy('record_date', 'asc');
            
            // If SKU is provided, return data for specific SKU
            if ($sku) {
                $metricsData = $query->where('sku', strtoupper(trim($sku)))->get();
                
                foreach ($metricsData as $record) {
                    $data = $record->daily_data;
                    $dateKey = \Carbon\Carbon::parse($record->record_date)->format('Y-m-d');
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => \Carbon\Carbon::parse($record->record_date)->format('M d'),
                        'price' => round($data['price'] ?? 0, 2),
                        'views' => $data['views'] ?? 0,
                        'cvr_percent' => round($data['cvr_percent'] ?? 0, 2),
                        'ad_percent' => round($data['ad_percent'] ?? 0, 2),
                        'a_l30' => round($data['a_l30'] ?? 0, 0), // Amazon L30 sold units
                        'inv' => isset($data['inv']) ? (int) $data['inv'] : null,
                        'inv_amz' => isset($data['inv_amz']) ? (int) $data['inv_amz'] : null,
                        'l30' => isset($data['l30']) ? (int) $data['l30'] : null, // OV L30 overall sold
                    ];
                }
            } else {
                // If SKUs array is provided, filter by those SKUs (for INV > 0 filtering)
                if ($skus) {
                    $skuArray = is_string($skus) ? json_decode($skus, true) : $skus;
                    if (is_array($skuArray) && count($skuArray) > 0) {
                        $query->whereIn('sku', array_map(function($sku) {
                            return strtoupper(trim($sku));
                        }, $skuArray));
                    }
                }
                
                // Aggregate data for filtered SKUs
                $metricsData = $query->get()->groupBy('record_date');
                
                foreach ($metricsData as $date => $records) {
                    $dateKey = \Carbon\Carbon::parse($date)->format('Y-m-d');
                    
                    // Calculate weighted average price (same as summary badge: price * a_l30 / sum a_l30)
                    $totalWeightedPrice = 0;
                    $totalL30 = 0;
                    foreach ($records as $record) {
                        $price = floatval($record->daily_data['price'] ?? 0);
                        $aL30 = floatval($record->daily_data['a_l30'] ?? 0);
                        $totalWeightedPrice += $price * $aL30;
                        $totalL30 += $aL30;
                    }
                    $avgPrice = $totalL30 > 0 ? ($totalWeightedPrice / $totalL30) : 0;
                    
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => \Carbon\Carbon::parse($date)->format('M d'),
                        'avg_price' => round($avgPrice, 2),
                        'total_views' => $records->sum(function($r) { return $r->daily_data['views'] ?? 0; }),
                        'avg_cvr_percent' => round($records->avg(function($r) { return $r->daily_data['cvr_percent'] ?? 0; }), 2),
                        'avg_ad_percent' => round($records->avg(function($r) { return $r->daily_data['ad_percent'] ?? 0; }), 2),
                    ];
                }
            }
            
            // If no data found in new table, try fallback
            if (empty($dataByDate)) {
                throw new \Exception('No data in new table, trying fallback');
            }
            
        } catch (\Exception $e) {
            // Fallback: Return empty data and let the frontend handle it
            Log::info('No Amazon daily metrics data available. Historical data will be populated by metrics collection command.');
        }

        // Fill in missing dates with zero values to ensure at least 7 days
        $currentDate = \Carbon\Carbon::parse($startDate);
        $today = \Carbon\Carbon::today();
        
        while ($currentDate->lte($today)) {
            $dateKey = $currentDate->format('Y-m-d');
            
            if (!isset($dataByDate[$dateKey])) {
                // Fill missing date with zero values
                if ($sku) {
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => $currentDate->format('M d'),
                        'price' => 0,
                        'views' => 0,
                        'cvr_percent' => 0,
                        'ad_percent' => 0,
                    ];
                } else {
                    $dataByDate[$dateKey] = [
                        'date' => $dateKey,
                        'date_formatted' => $currentDate->format('M d'),
                        'avg_price' => 0,
                        'total_views' => 0,
                        'avg_cvr_percent' => 0,
                        'avg_ad_percent' => 0,
                    ];
                }
            }
            
            $currentDate->addDay();
        }
        
        // Sort by date and convert to array
        ksort($dataByDate);
        $chartData = array_values($dataByDate);

        return response()->json($chartData);
    }

    public function saveAmazonColumnVisibility(Request $request)
    {
        $userId = auth()->id();
        $key = "amazon_sales_tabulator_column_visibility_{$userId}";
        $visibility = $request->input('visibility', []);
        Cache::put($key, $visibility, now()->addDays(30));
        return response()->json(['success' => true]);
    }

    public function updateAmazonRating(Request $request)
    {
        $sku = strtoupper(trim($request->input('sku')));
        $rating = $request->input('rating');

        // Validate rating
        if (!is_numeric($rating) || $rating < 0 || $rating > 5) {
            return response()->json([
                'success' => false,
                'error' => 'Rating must be a number between 0 and 5'
            ], 400);
        }

        try {
            // Find or create the manual data record
            $manual = AmazonFbmManual::firstOrNew(['sku' => $sku]);
            
            // Decode existing data
            $data = $manual->data ? json_decode($manual->data, true) : [];
            
            // Update rating
            $data['rating'] = floatval($rating);
            
            // Save
            $manual->data = json_encode($data);
            $manual->save();

            return response()->json([
                'success' => true,
                'message' => 'Rating updated successfully',
                'rating' => floatval($rating)
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating Amazon rating: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error updating rating'
            ], 500);
        }
    }

    /**
     * Auto-save daily summary snapshot (only once per day)
     * Uses JSON storage - flexible and matches JavaScript exactly
     */
    private function saveDailySummaryIfNeeded($products)
    {
        try {
            $today = now()->toDateString();
            
            // No cache - always update when page loads
            // Uses updateOrCreate so it updates existing record for today
            
            // Filter: !is_parent_summary && INV > 0 && NR !== 'NR' (EXACT JavaScript logic with RL filter)
            $validProducts = collect($products)->filter(function($p) {
                // Same as JavaScript default filters
                $invCheck = !isset($p['is_parent_summary']) && floatval($p['INV'] ?? 0) > 0;
                $rlCheck = ($p['NR'] ?? '') !== 'NR'; // RL filter: exclude NR items
                
                return $invCheck && $rlCheck;
            });
            
            if ($validProducts->isEmpty()) {
                return; // No valid products
            }
            
            // Initialize counters (EXACT JavaScript variable names)
            $totalSkuCount = 0;
            $totalSoldCount = 0;
            $zeroSoldCount = 0;
            $prcGtLmpCount = 0;
            $totalPftAmt = 0;
            $totalSalesAmt = 0;
            $totalLpAmt = 0;
            $totalAmazonInv = 0;
            $totalAmazonInvAmz = 0;
            $totalAmazonL30 = 0;
            $totalViews = 0;
            $totalWeightedPrice = 0;
            $mapCount = 0;
            $nmapCount = 0;
            $missingAmazonCount = 0;
            
            // Loop through each row (EXACT JavaScript forEach logic)
            foreach ($validProducts as $row) {
                $totalSkuCount++;
                $totalPftAmt += floatval($row['Total_pft'] ?? 0);
                $totalSalesAmt += floatval($row['T_Sale_l30'] ?? 0);
                $totalLpAmt += floatval($row['LP_productmaster'] ?? 0) * floatval($row['A_L30'] ?? 0);
                $totalAmazonInv += floatval($row['INV'] ?? 0);
                
                // Handle INV_AMZ - only sum if numeric
                $invAmz = $row['INV_AMZ'] ?? 0;
                if (is_numeric($invAmz)) {
                    $totalAmazonInvAmz += floatval($invAmz);
                }
                
                $aL30 = floatval($row['A_L30'] ?? 0);
                $totalAmazonL30 += $aL30;
                
                // Count sold and 0-sold (EXACT JavaScript logic)
                if ($aL30 > 0) {
                    $totalSoldCount++;
                } else {
                    $zeroSoldCount++;
                }
                
                // Count Prc > LMP (EXACT JavaScript logic)
                $price = floatval($row['price'] ?? 0);
                $lmpPrice = floatval($row['lmp_price'] ?? 0);
                if ($lmpPrice > 0 && $price > $lmpPrice) {
                    $prcGtLmpCount++;
                }
                
                // Count Missing L, Map, N Map (aligned with amazon_tabulator_view: Map if |INV-INV_AMZ| <= 3 OR <= 3% of INV; exclude FBA from missing + nmap)
                $inv = floatval($row['INV'] ?? 0);
                $nrValue = $row['NR'] ?? '';
                $isMissingAmazon = $row['is_missing_amazon'] ?? false;
                $rowPrice = floatval($row['price'] ?? 0);
                $fbaFlag = $row['fba'] ?? null;
                $childSku = strtoupper((string) ($row['(Child) sku'] ?? ''));
                $parentSku = strtoupper((string) ($row['Parent'] ?? ''));
                $isFbaRow = ($fbaFlag === 1 || $fbaFlag === '1' || $fbaFlag === true)
                    || str_contains($childSku, 'FBA')
                    || str_contains($parentSku, 'FBA');

                if ($inv > 0 && $nrValue === 'REQ') {
                    if (($isMissingAmazon || $rowPrice <= 0) && ! $isFbaRow) {
                        // SKU doesn't exist in amazon_datsheets OR has blank/zero price (non-FBA only; NR rows excluded above)
                        $missingAmazonCount++;
                    } elseif (! $isMissingAmazon && $rowPrice > 0) {
                        // SKU exists with valid price, check inventory sync (within 3% of Shopify INV = Map)
                        $invAmzNum = floatval($row['INV_AMZ'] ?? 0);
                        $invDifference = abs($inv - $invAmzNum);
                        $invMapTolerance = $inv * 0.03;

                        if ($invDifference <= 3 + 1e-9 || $invDifference <= $invMapTolerance + 1e-9) {
                            $mapCount++;
                        } elseif (! $isFbaRow) {
                            $nmapCount++;
                        }
                    }
                }
                
                // Weighted price calculation
                $totalWeightedPrice += $price * floatval($row['A_L30'] ?? 0);
                
                // Views
                $totalViews += floatval($row['Sess30'] ?? 0);
            }
            
            // Calculate averages and percentages (EXACT JavaScript logic)
            $avgPrice = $totalAmazonL30 > 0 ? $totalWeightedPrice / $totalAmazonL30 : 0;
            $avgCVR = $totalViews > 0 ? ($totalAmazonL30 / $totalViews * 100) : 0;
            $groiPercent = $totalLpAmt > 0 ? (($totalPftAmt / $totalLpAmt) * 100) : 0;
            
            // Calculate GPFT % (average gross profit)
            $avgGpftPercent = $totalSalesAmt > 0 ? (($totalSalesAmt - $totalLpAmt) / $totalSalesAmt * 100) : 0;
            
            // Store ALL metrics in JSON (flexible!)
            $summaryData = [
                // Counts
                'total_sku_count' => $totalSkuCount,
                'sold_count' => $totalSoldCount,
                'zero_sold_count' => $zeroSoldCount,
                'prc_gt_lmp_count' => $prcGtLmpCount,
                
                // Map and Missing Counts (NEW)
                'map_count' => $mapCount,
                'nmap_count' => $nmapCount,
                'missing_amazon_count' => $missingAmazonCount,
                
                // Financial Totals
                'total_pft_amt' => round($totalPftAmt, 2),
                'total_sales_amt' => round($totalSalesAmt, 2),
                'total_lp_amt' => round($totalLpAmt, 2),
                
                // Inventory
                'total_amazon_inv' => round($totalAmazonInv, 2),
                'total_amazon_inv_amz' => round($totalAmazonInvAmz, 2),
                'total_amazon_l30' => round($totalAmazonL30, 2),
                'total_views' => $totalViews,
                
                // Calculated Percentages
                'groi_percent' => round($groiPercent, 2),
                'cvr_percent' => round($avgCVR, 2),
                'gpft_percent' => round($avgGpftPercent, 2),
                
                // Averages
                'avg_price' => round($avgPrice, 2),
                
                // Metadata
                'total_products_count' => count($products),
                'calculated_at' => now()->toDateTimeString(),
                
                // Active Filters
                'filters_applied' => [
                    'inventory' => 'more',  // INV > 0
                    'nrl' => 'req',        // RL only (exclude NR)
                ],
            ];
            
            // Save or update as JSON (channel-wise)
            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'amazon',
                    'snapshot_date' => $today
                ],
                [
                    'summary_data' => $summaryData,
                    'notes' => 'Auto-saved daily snapshot (INV > 0, RL only)',
                ]
            );
            
            Log::info("Daily Amazon summary snapshot saved for {$today}", [
                'sku_count' => $totalSkuCount,
                'sold_count' => $totalSoldCount,
            ]);
            
        } catch (\Exception $e) {
            // Don't break the main response if summary save fails
            Log::error('Error saving daily Amazon summary: ' . $e->getMessage());
        }
    }

    /**
     * Save checklist to history table
     */
    public function saveAmazonChecklistToHistory(Request $request)
    {
        try {
            $sku = strtoupper(trim($request->input('sku')));
            $checklistText = $request->input('checklist_text', '');
            
            // Enforce 180 character limit
            if (strlen($checklistText) > 180) {
                $checklistText = substr($checklistText, 0, 180);
            }
            
            if (!$sku) {
                return response()->json(['error' => 'SKU is required'], 400);
            }
            
            if (empty(trim($checklistText))) {
                return response()->json(['error' => 'Checklist text is required'], 400);
            }
            
            // Create new history entry in dedicated table
            AmazonSeoAuditHistory::create([
                'sku' => $sku,
                'checklist_text' => $checklistText,
                'user_id' => auth()->id() ?? null,
            ]);
            
            // Get all history for this SKU (for frontend update)
            $allHistory = AmazonSeoAuditHistory::where('sku', $sku)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($entry) {
                    return [
                        'id' => $entry->id,
                        'text' => $entry->checklist_text,
                        'user_id' => $entry->user_id,
                        'timestamp' => $entry->created_at->format('Y-m-d H:i:s')
                    ];
                })
                ->toArray();
            
            return response()->json([
                'success' => true,
                'message' => 'Added to SEO Audit History',
                'history' => $allHistory
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error saving checklist to history', [
                'sku' => $sku ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to save to history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SEO audit history for a SKU
     */
    public function getAmazonSeoHistory(Request $request)
    {
        try {
            $sku = strtoupper(trim($request->input('sku')));
            
            if (!$sku) {
                return response()->json(['error' => 'SKU is required'], 400);
            }
            
            $history = AmazonSeoAuditHistory::where('amazon_seo_audit_history.sku', $sku)
                ->leftJoin('users', 'amazon_seo_audit_history.user_id', '=', 'users.id')
                ->select('amazon_seo_audit_history.*', 'users.name as user_name')
                ->orderBy('amazon_seo_audit_history.created_at', 'desc')
                ->get()
                ->map(function($entry) {
                    return [
                        'id' => $entry->id,
                        'text' => $entry->checklist_text,
                        'user_name' => $entry->user_name ?? 'Guest',
                        'timestamp' => $entry->created_at ? $entry->created_at->format('Y-m-d H:i:s') : 'N/A'
                    ];
                })
                ->toArray();
            
            return response()->json([
                'success' => true,
                'history' => $history
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching SEO history', [
                'sku' => $sku ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Normalize a competitor delivery value (array | json string | string) into a
     * short display string. Returns null when there is nothing to show.
     */
    private function normalizeDeliveryText($delivery)
    {
        if (is_array($delivery)) {
            return implode(', ', array_slice($delivery, 0, 2));
        }
        if (is_string($delivery) && $delivery !== '') {
            $decoded = json_decode($delivery, true);
            return is_array($decoded) ? implode(', ', array_slice($decoded, 0, 2)) : $delivery;
        }
        return null;
    }

    /**
     * Get all competitors for a SKU (for modal display)
     */
    public function getAmazonCompetitors(Request $request)
    {
        try {
            $sku = trim($request->input('sku'));
            
            if (!$sku) {
                return response()->json(['error' => 'SKU is required'], 400);
            }
            
            // Use 'amazon' to match database marketplace value
            $competitors = AmazonSkuCompetitor::getCompetitorsForSku($sku, 'amazon');
            
            $lowestPrice = $competitors->first();
            
            return response()->json([
                'success' => true,
                'competitors' => $competitors->map(function($comp) {
                    // Use stored image or construct from ASIN (Amazon image URL pattern)
                    $image = $comp->image ?? (
                        $comp->asin
                            ? 'https://m.media-amazon.com/images/P/' . $comp->asin . '._AC_SL160_.jpg'
                            : null
                    );
                    $delivery = $comp->delivery;
                    if (is_array($delivery)) {
                        $delivery = implode(', ', array_slice($delivery, 0, 2));
                    } elseif (is_string($delivery)) {
                        $decoded = json_decode($delivery, true);
                        $delivery = is_array($decoded) ? implode(', ', array_slice($decoded, 0, 2)) : $delivery;
                    } else {
                        $delivery = null;
                    }
                    return [
                        'id' => $comp->id,
                        'sku' => $comp->sku,
                        'asin' => $comp->asin,
                        'marketplace' => $comp->marketplace,
                        'image' => $image,
                        'product_link' => $comp->product_link,
                        'link' => $comp->product_link,
                        'product_title' => $comp->product_title,
                        'title' => $comp->product_title,
                        'seller_name' => $comp->seller_name,
                        'price' => floatval($comp->price),
                        'rating' => $comp->rating !== null ? floatval($comp->rating) : null,
                        'reviews' => $comp->reviews !== null ? (int) $comp->reviews : null,
                        'extracted_old_price' => $comp->extracted_old_price !== null ? floatval($comp->extracted_old_price) : null,
                        'delivery' => $delivery,
                        'monthly_revenue' => $comp->monthly_revenue !== null ? floatval($comp->monthly_revenue) : null,
                        'monthly_units_sold' => $comp->monthly_units_sold !== null ? (int) $comp->monthly_units_sold : null,
                        'buy_box_owner' => $comp->buy_box_owner,
                        'seller_type' => $comp->seller_type_js,
                        'sales_data_updated_at' => $comp->sales_data_updated_at ? $comp->sales_data_updated_at->format('Y-m-d H:i:s') : null,
                        'created_at' => $comp->created_at ? $comp->created_at->format('Y-m-d H:i:s') : null,
                        'updated_at' => $comp->updated_at ? $comp->updated_at->format('Y-m-d H:i:s') : null,
                    ];
                }),
                'lowest_price' => $lowestPrice ? floatval($lowestPrice->price) : null,
                'total_count' => $competitors->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching Amazon competitors', [
                'sku' => $sku ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch competitors: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add LMP from competitor data
     */
    public function addAmazonLmp(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'asin' => 'required|string',
                'price' => 'required|numeric|min:0.01',
                'product_link' => 'nullable|string',
                'product_title' => 'nullable|string',
                'marketplace' => 'nullable|string',
            ]);
            
            $sku = trim($validated['sku']);
            // Normalize marketplace to lowercase to match the getCompetitors filter
            $marketplace = strtolower($validated['marketplace'] ?? 'amazon');
            
            // Check if this exact record already exists
            $existing = AmazonSkuCompetitor::where('sku', $sku)
                ->where('asin', $validated['asin'])
                ->where('marketplace', $marketplace)
                ->first();
            
            if ($existing) {
                return response()->json([
                    'error' => 'This competitor is already saved for this SKU'
                ], 409);
            }
            
            // Create new LMP entry
            DB::beginTransaction();
            
            $lmp = AmazonSkuCompetitor::create([
                'sku' => $sku,
                'asin' => $validated['asin'],
                'price' => $validated['price'],
                'product_link' => $validated['product_link'] ?? null,
                'product_title' => $validated['product_title'] ?? null,
                'marketplace' => $marketplace,
            ]);
            
            DB::commit();
            
            Log::info('LMP added successfully', [
                'sku' => $sku,
                'asin' => $validated['asin'],
                'price' => $validated['price']
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'LMP added successfully',
                'data' => [
                    'id' => $lmp->id,
                    'sku' => $lmp->sku,
                    'asin' => $lmp->asin,
                    'price' => floatval($lmp->price),
                    'product_link' => $lmp->product_link,
                    'product_title' => $lmp->product_title,
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error adding LMP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to add LMP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete LMP entry
     */
    public function deleteAmazonLmp(Request $request)
    {
        try {
            $id = $request->input('id');
            
            Log::info('Delete LMP request received', [
                'id' => $id,
                'all_input' => $request->all()
            ]);
            
            if (!$id || !is_numeric($id)) {
                Log::warning('Invalid ID provided for delete', ['id' => $id]);
                return response()->json([
                    'error' => 'Valid ID is required'
                ], 400);
            }
            
            $lmp = AmazonSkuCompetitor::find($id);
            
            if (!$lmp) {
                Log::warning('LMP entry not found', ['id' => $id]);
                return response()->json([
                    'error' => 'LMP entry not found'
                ], 404);
            }
            
            DB::beginTransaction();
            
            $sku = $lmp->sku;
            $asin = $lmp->asin;
            $price = $lmp->price;
            
            $lmp->delete();
            
            DB::commit();
            
            Log::info('LMP deleted successfully', [
                'id' => $id,
                'sku' => $sku,
                'asin' => $asin,
                'price' => $price
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Competitor deleted successfully',
                'deleted_id' => $id
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting LMP', [
                'id' => $id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to delete competitor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCampaignDataBySku(Request $request)
    {
        $sku = $request->input('sku');
        
        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }

        $cleanSku = strtoupper(trim(rtrim($sku, '.')));
        
        // Get price for the SKU from AmazonDatasheet
        // Match AmazonSpBudgetController logic: for HL campaigns, price is stored with "PARENT" prefix
        // First, determine if this is a parent SKU or child SKU
        $isParentSku = stripos($cleanSku, 'PARENT') !== false;
        $parentSkuForPrice = $cleanSku;
        $parentValue = null;
        
        // If SKU doesn't contain PARENT, find parent SKU from ProductMaster (for HL campaigns)
        if (!$isParentSku) {
            $productMaster = ProductMaster::where('sku', $cleanSku)->first();
            if ($productMaster && $productMaster->parent) {
                $parentValue = strtoupper(trim($productMaster->parent));
                $parentSkuForPrice = 'PARENT ' . $parentValue;
            }
        } else {
            // Extract parent value from "PARENT DS 01" -> "DS 01"
            $parentValue = str_replace('PARENT ', '', $cleanSku);
        }
        
        // Fetch price using parent SKU (matching AmazonSpBudgetController line 2351)
        $amazonSheet = AmazonDatasheet::where('sku', $parentSkuForPrice)->first();
        $price = $amazonSheet ? floatval($amazonSheet->price ?? 0) : 0;
        
        // Fallback: if price is still 0 and we have parent value, calculate average from child SKUs
        if ($price == 0 && $parentValue) {
            // Get all child SKUs with this parent
            $childSkus = ProductMaster::where('parent', $parentValue)
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->pluck('sku')
                ->toArray();
            
            if (!empty($childSkus)) {
                // Get average price from child SKUs
                $childPrices = AmazonDatasheet::whereIn('sku', $childSkus)
                    ->whereNotNull('price')
                    ->where('price', '>', 0)
                    ->pluck('price')
                    ->toArray();
                
                if (!empty($childPrices)) {
                    $price = array_sum($childPrices) / count($childPrices);
                }
            }
        }
        
        // Final fallback: try without PARENT prefix
        if ($price == 0 && $isParentSku) {
            $parentSkuWithoutPrefix = str_replace('PARENT ', '', $cleanSku);
            $parentSheet = AmazonDatasheet::where('sku', $parentSkuWithoutPrefix)->first();
            if ($parentSheet) {
                $price = floatval($parentSheet->price ?? 0);
            }
        }
        
        // Fetch last_sbid from yesterday and day-before-yesterday for KW campaigns.
        // Prefer YESTERDAY so Last SBID shows the previous day's SBID (SBID that was in effect yesterday).
        $dayBeforeYesterday = date('Y-m-d', strtotime('-2 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $lastSbidMapKw = [];
        $lastSbidReportsKw = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where(function($q) use ($dayBeforeYesterday, $yesterday) {
                $q->where('report_date_range', $dayBeforeYesterday)
                  ->orWhere('report_date_range', $yesterday);
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->where('campaignName', 'NOT LIKE', '%PT')
            ->where('campaignName', 'NOT LIKE', '%PT.')
            ->get()
            ->sortBy(function($report) use ($yesterday) {
                // Prioritize yesterday so Last SBID = previous day's SBID
                return $report->report_date_range === $yesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');
        
        foreach ($lastSbidReportsKw as $campaignId => $reports) {
            // Get the first report (they should all have the same last_sbid for the same campaign_id)
            $report = $reports->first();
            // Normalize campaign_id to string for consistent matching
            $campaignIdStr = (string)$campaignId;
            // Allow 0 values, only exclude null and empty string (but allow numeric 0 and string '0')
            // Check: not null, not empty string, but allow 0 (numeric or string)
            // Note: Using isset() to check if property exists, then checking for null/empty
            if (!empty($campaignIdStr) && isset($report->last_sbid) && $report->last_sbid !== null && $report->last_sbid !== '') {
                $lastSbidMapKw[$campaignIdStr] = $report->last_sbid;
            } elseif (!empty($campaignIdStr) && isset($report->last_sbid) && ($report->last_sbid === 0 || $report->last_sbid === '0')) {
                // Explicitly allow 0 values
                $lastSbidMapKw[$campaignIdStr] = $report->last_sbid;
            }
            // Also create a map by campaignName for fallback matching
            $campaignName = $report->campaignName ?? '';
            if (!empty($campaignName) && $report->last_sbid !== null && $report->last_sbid !== '') {
                // Normalize campaign name for matching
                $normalizedName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $campaignName);
                $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
                $normalizedName = strtoupper(trim(rtrim($normalizedName, '.')));
                if (!empty($normalizedName)) {
                    $lastSbidMapKw['name_' . $normalizedName] = $report->last_sbid;
                }
            }
        }
        
        // Get KW campaigns (exact SKU match, excluding PT)
        $kwCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where(function($q) use ($cleanSku) {
                $q->where('report_date_range', 'L30')
                  ->orWhere('report_date_range', 'L7')
                  ->orWhere('report_date_range', 'L1')
                  ->orWhere('report_date_range', 'L2');
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get()
            ->filter(function($item) use ($cleanSku) {
                $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                $campaignName = strtoupper(trim(rtrim($campaignName, '.')));
                // Exclude PT campaigns
                if (stripos($campaignName, ' PT') !== false || stripos($campaignName, 'PT.') !== false) {
                    return false;
                }
                return $campaignName === $cleanSku;
            })
            ->groupBy('campaign_id')
            ->map(function($group) use ($price, $lastSbidMapKw, $dayBeforeYesterday, $yesterday) {
                $l30 = $group->where('report_date_range', 'L30')->first();
                $l7 = $group->where('report_date_range', 'L7')->first();
                $l1 = $group->where('report_date_range', 'L1')->first();
                $l2 = $group->where('report_date_range', 'L2')->first();
                
                $campaign = $l30 ?? $l7 ?? $l1;
                if (!$campaign) return null;
                
                $budget = floatval($campaign->campaignBudgetAmount ?? 0);
                $l7Spend = $l7 ? floatval($l7->spend ?? $l7->cost ?? 0) : 0;
                $l1Spend = $l1 ? floatval($l1->spend ?? $l1->cost ?? 0) : 0;
                $l2Spend = $l2 ? floatval($l2->spend ?? $l2->cost ?? 0) : 0;
                $l7Clicks = $l7 ? floatval($l7->clicks ?? 0) : 0;
                $l1Clicks = $l1 ? floatval($l1->clicks ?? 0) : 0;
                $l30Clicks = $l30 ? floatval($l30->clicks ?? 0) : 0;
                
                // Use costPerClick from records (matching AmazonSpBudgetController)
                $l7Cpc = $l7 ? floatval($l7->costPerClick ?? 0) : 0;
                $l1Cpc = $l1 ? floatval($l1->costPerClick ?? 0) : 0;
                $l2Cpc = $l2 ? floatval($l2->costPerClick ?? 0) : 0;
                
                // Calculate avg_cpc (lifetime average from daily records)
                $campaignId = $campaign->campaign_id ?? null;
                $avgCpc = 0;
                if ($campaignId) {
                    try {
                        $avgCpcRecord = DB::table('amazon_sp_campaign_reports')
                            ->select(DB::raw('AVG(costPerClick) as avg_cpc'))
                            ->where('campaign_id', $campaignId)
                            ->where('ad_type', 'SPONSORED_PRODUCTS')
                            ->where('campaignStatus', '!=', 'ARCHIVED')
                            ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
                            ->where('costPerClick', '>', 0)
                            ->whereNotNull('campaign_id')
                            ->first();
                        
                        if ($avgCpcRecord && $avgCpcRecord->avg_cpc > 0) {
                            $avgCpc = floatval($avgCpcRecord->avg_cpc);
                        }
                    } catch (\Exception $e) {
                        // Continue without avg_cpc if there's an error
                    }
                }
                
                $ub7 = $budget > 0 ? ($l7Spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1Spend / $budget) * 100 : 0;
                $ub2 = $budget > 0 ? ($l2Spend / $budget) * 100 : 0;
                
                $l30Spend = $l30 ? floatval($l30->spend ?? $l30->cost ?? 0) : 0;
                $l30Sales = $l30 ? floatval($l30->sales30d ?? 0) : 0;
                $l30Purchases = $l30 ? floatval($l30->purchases30d ?? 0) : 0;
                $l30UnitsSold = $l30 ? floatval($l30->unitsSoldClicks30d ?? 0) : 0;
                
                $adCvr = $l30Clicks > 0 ? (($l30Purchases / $l30Clicks) * 100) : 0;
                
                // Calculate ACOS for sbgt calculation
                if ($l30Spend > 0 && $l30Sales > 0) {
                    $acos = ($l30Spend / $l30Sales) * 100;
                } elseif ($l30Spend > 0 && $l30Sales == 0) {
                    $acos = 100;
                } else {
                    $acos = 0;
                }
                
                $sbgt = AmazonAcosSbgtRule::sbgtFromAcosL30((float) $acos);
                
                // Get last_sbid from day-before-yesterday's records
                // Normalize campaign_id to string for consistent matching
                $campaignIdStr = $campaignId ? (string)$campaignId : null;
                $lastSbid = '';
                // Try matching by campaign_id first
                if ($campaignIdStr && isset($lastSbidMapKw[$campaignIdStr])) {
                    $lastSbid = $lastSbidMapKw[$campaignIdStr];
                } else {
                    // Fallback: try matching by campaignName
                    $campaignName = $campaign->campaignName ?? '';
                    if (!empty($campaignName)) {
                        $normalizedName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $campaignName);
                        $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
                        $normalizedName = strtoupper(trim(rtrim($normalizedName, '.')));
                        $nameKey = 'name_' . $normalizedName;
                        if (isset($lastSbidMapKw[$nameKey])) {
                            $lastSbid = $lastSbidMapKw[$nameKey];
                        }
                    }
                }
                
                // Final fallback: Direct database query if still not found
                if ($lastSbid === '' && $campaignIdStr) {
                    try {
                        $directLastSbid = DB::table('amazon_sp_campaign_reports')
                            ->where('campaign_id', $campaignIdStr)
                            ->where('ad_type', 'SPONSORED_PRODUCTS')
                            ->where('campaignStatus', '!=', 'ARCHIVED')
                            ->whereIn('report_date_range', [$dayBeforeYesterday, $yesterday])
                            ->where(function($q) {
                                $q->where('campaignName', 'NOT LIKE', '%PT')
                                  ->where('campaignName', 'NOT LIKE', '%PT.');
                            })
                            ->orderByRaw("CASE WHEN report_date_range = ? THEN 0 ELSE 1 END", [$yesterday])
                            ->value('last_sbid');
                        
                        if ($directLastSbid !== null && $directLastSbid !== '') {
                            $lastSbid = $directLastSbid;
                        }
                    } catch (\Exception $e) {
                        // Continue without last_sbid if there's an error
                    }
                }
                
                // KW SBID rules (same as Blade/commands):
                // - 2UB red + 1UB red => L1*1.10, else L2*1.10, else L7*1.10, else 0.60
                // - 2UB pink + 1UB pink => L1*0.90
                $sbid = 0;
                $ub2Red = $ub2 < 66;
                $ub2Pink = $ub2 > 99;
                $ub1Red = $ub1 < 66;
                $ub1Pink = $ub1 > 99;

                if ($ub2Red && $ub1Red) {
                    if ($l1Cpc > 0) {
                        $sbid = floor($l1Cpc * 1.10 * 100) / 100;
                    } elseif ($l1Cpc <= 0 && $l2Cpc > 0) {
                        $sbid = floor($l2Cpc * 1.10 * 100) / 100;
                    } elseif ($l1Cpc <= 0 && $l2Cpc <= 0 && $l7Cpc > 0) {
                        $sbid = floor($l7Cpc * 1.10 * 100) / 100;
                    } else {
                        $sbid = 0.60;
                    }
                } elseif ($ub2Pink && $ub1Pink) {
                    $sbid = floor($l1Cpc * 0.90 * 100) / 100;
                }
                
                return [
                    'campaign_name' => $campaign->campaignName,
                    'bgt' => $budget,
                    'sbgt' => $sbgt,
                    'acos' => round($acos, 2),
                    'clicks' => $l30Clicks,
                    'ad_spend' => $l30Spend,
                    'ad_sales' => $l30Sales,
                    'ad_sold' => $l30UnitsSold,
                    'ad_cvr' => round($adCvr, 2),
                    '7ub' => round($ub7, 2),
                    '1ub' => round($ub1, 2),
                    '2ub' => round($ub2, 2),
                    'avg_cpc' => round($avgCpc, 2),
                    'l7cpc' => round($l7Cpc, 2),
                    'l1cpc' => round($l1Cpc, 2),
                    'l2cpc' => round($l2Cpc, 2),
                    'l_bid' => $lastSbid,
                    'sbid' => $sbid > 0 ? round($sbid, 2) : 0,
                ];
            })
            ->filter()
            ->values();
        
        // Fetch last_sbid from day-before-yesterday's date records for PT campaigns
        // This ensures last_sbid shows the PREVIOUS day's calculated SBID, not the current day's
        // Also try yesterday as fallback if day-before-yesterday doesn't have the record
        $lastSbidMapPt = [];
        $lastSbidReportsPt = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where(function($q) use ($dayBeforeYesterday, $yesterday) {
                $q->where('report_date_range', $dayBeforeYesterday)
                  ->orWhere('report_date_range', $yesterday);
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->where(function($q) {
                $q->where('campaignName', 'LIKE', '% PT')
                  ->orWhere('campaignName', 'LIKE', '% PT.')
                  ->orWhere('campaignName', 'LIKE', '%PT')
                  ->orWhere('campaignName', 'LIKE', '%PT.');
            })
            ->get()
            ->sortBy(function($report) use ($dayBeforeYesterday) {
                // Prioritize day-before-yesterday over yesterday
                return $report->report_date_range === $dayBeforeYesterday ? 0 : 1;
            })
            ->groupBy('campaign_id');
        
        foreach ($lastSbidReportsPt as $campaignId => $reports) {
            // Get the first report (they should all have the same last_sbid for the same campaign_id)
            $report = $reports->first();
            // Normalize campaign_id to string for consistent matching
            $campaignIdStr = (string)$campaignId;
            // Allow 0 values, only exclude null and empty string (but allow numeric 0 and string '0')
            // Check if last_sbid is set (not null) and not empty string, but allow 0
            if (!empty($campaignIdStr) && isset($report->last_sbid) && $report->last_sbid !== '' && $report->last_sbid !== null) {
                $lastSbidMapPt[$campaignIdStr] = $report->last_sbid;
            }
            // Also create a map by campaignName for fallback matching
            $campaignName = $report->campaignName ?? '';
            if (!empty($campaignName) && isset($report->last_sbid) && $report->last_sbid !== null && $report->last_sbid !== '') {
                // Normalize campaign name for matching
                $normalizedName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $campaignName);
                $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
                $normalizedName = strtoupper(trim(rtrim($normalizedName, '.')));
                if (!empty($normalizedName)) {
                    $lastSbidMapPt['name_' . $normalizedName] = $report->last_sbid;
                }
            } elseif (!empty($campaignName) && isset($report->last_sbid) && ($report->last_sbid === 0 || $report->last_sbid === '0')) {
                // Explicitly allow 0 values for campaignName matching too
                $normalizedName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $campaignName);
                $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
                $normalizedName = strtoupper(trim(rtrim($normalizedName, '.')));
                if (!empty($normalizedName)) {
                    $lastSbidMapPt['name_' . $normalizedName] = $report->last_sbid;
                }
            }
        }
        
        // Get PT campaigns (ends with PT or PT.)
        $ptCampaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
            ->where(function($q) use ($cleanSku) {
                $q->where('report_date_range', 'L30')
                  ->orWhere('report_date_range', 'L7')
                  ->orWhere('report_date_range', 'L1');
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get()
            ->filter(function($item) use ($cleanSku) {
                $campaignName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $item->campaignName);
                $campaignName = preg_replace('/\s+/', ' ', $campaignName);
                $cleanName = strtoupper(trim($campaignName));
                return ($cleanName === $cleanSku . ' PT' || $cleanName === $cleanSku . ' PT.' || 
                        $cleanName === $cleanSku . 'PT' || $cleanName === $cleanSku . 'PT.');
            })
            ->groupBy('campaign_id')
            ->map(function($group) use ($price, $lastSbidMapPt) {
                $l30 = $group->where('report_date_range', 'L30')->first();
                $l7 = $group->where('report_date_range', 'L7')->first();
                $l1 = $group->where('report_date_range', 'L1')->first();
                
                $campaign = $l30 ?? $l7 ?? $l1;
                if (!$campaign) return null;
                
                $budget = floatval($campaign->campaignBudgetAmount ?? 0);
                $l7Spend = $l7 ? floatval($l7->spend ?? $l7->cost ?? 0) : 0;
                $l1Spend = $l1 ? floatval($l1->spend ?? $l1->cost ?? 0) : 0;
                $l7Clicks = $l7 ? floatval($l7->clicks ?? 0) : 0;
                $l1Clicks = $l1 ? floatval($l1->clicks ?? 0) : 0;
                $l30Clicks = $l30 ? floatval($l30->clicks ?? 0) : 0;
                
                // Use costPerClick from records (matching AmazonSpBudgetController)
                $l7Cpc = $l7 ? floatval($l7->costPerClick ?? 0) : 0;
                $l1Cpc = $l1 ? floatval($l1->costPerClick ?? 0) : 0;
                
                // Calculate avg_cpc (lifetime average from daily records)
                $campaignId = $campaign->campaign_id ?? null;
                $avgCpc = 0;
                if ($campaignId) {
                    try {
                        $avgCpcRecord = DB::table('amazon_sp_campaign_reports')
                            ->select(DB::raw('AVG(costPerClick) as avg_cpc'))
                            ->where('campaign_id', $campaignId)
                            ->where('ad_type', 'SPONSORED_PRODUCTS')
                            ->where('campaignStatus', '!=', 'ARCHIVED')
                            ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
                            ->where('costPerClick', '>', 0)
                            ->whereNotNull('campaign_id')
                            ->first();
                        
                        if ($avgCpcRecord && $avgCpcRecord->avg_cpc > 0) {
                            $avgCpc = floatval($avgCpcRecord->avg_cpc);
                        }
                    } catch (\Exception $e) {
                        // Continue without avg_cpc if there's an error
                    }
                }
                
                $ub7 = $budget > 0 ? ($l7Spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1Spend / $budget) * 100 : 0;
                
                $l30Spend = $l30 ? floatval($l30->spend ?? $l30->cost ?? 0) : 0;
                $l30Sales = $l30 ? floatval($l30->sales30d ?? 0) : 0;
                $l30Purchases = $l30 ? floatval($l30->purchases30d ?? 0) : 0;
                $l30UnitsSold = $l30 ? floatval($l30->unitsSoldClicks30d ?? 0) : 0;
                
                $adCvr = $l30Clicks > 0 ? (($l30Purchases / $l30Clicks) * 100) : 0;
                
                // Calculate ACOS for sbgt calculation
                if ($l30Spend > 0 && $l30Sales > 0) {
                    $acos = ($l30Spend / $l30Sales) * 100;
                } elseif ($l30Spend > 0 && $l30Sales == 0) {
                    $acos = 100;
                } else {
                    $acos = 0;
                }
                
                $sbgt = AmazonAcosSbgtRule::sbgtFromAcosL30((float) $acos);
                
                // Get last_sbid from day-before-yesterday's records
                // Normalize campaign_id to string for consistent matching
                $campaignIdStr = $campaignId ? (string)$campaignId : null;
                $lastSbid = '';
                // Try matching by campaign_id first
                if ($campaignIdStr && isset($lastSbidMapPt[$campaignIdStr])) {
                    $lastSbid = $lastSbidMapPt[$campaignIdStr];
                } else {
                    // Fallback: try matching by campaignName
                    $campaignName = $campaign->campaignName ?? '';
                    if (!empty($campaignName)) {
                        $normalizedName = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $campaignName);
                        $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
                        $normalizedName = strtoupper(trim(rtrim($normalizedName, '.')));
                        $nameKey = 'name_' . $normalizedName;
                        if (isset($lastSbidMapPt[$nameKey])) {
                            $lastSbid = $lastSbidMapPt[$nameKey];
                        }
                    }
                }
                
                // Calculate SBID dynamically based on utilization type (matching utilized-pt logic)
                $sbid = 0;
                $utilizationType = 'all';
                if ($ub7 > 99 && $ub1 > 99) {
                    $utilizationType = 'over';
                } elseif ($ub7 < 66 && $ub1 < 66) {
                    $utilizationType = 'under';
                } elseif ($ub7 >= 66 && $ub7 <= 99 && $ub1 >= 66 && $ub1 <= 99) {
                    $utilizationType = 'correctly';
                }
                
                // Special case: If UB7 and UB1 = 0%, use price-based default
                $isZeroUtilizationPt = ($ub7 == 0 && $ub1 == 0);
                if ($isZeroUtilizationPt) {
                    if ($price < 50) {
                        $sbid = 0.50;
                    } elseif ($price >= 50 && $price < 100) {
                        $sbid = 1.00;
                    } elseif ($price >= 100 && $price < 200) {
                        $sbid = 1.50;
                    } else {
                        $sbid = 2.00;
                    }
                } elseif ($utilizationType === 'over') {
                    // Over-utilized: Priority L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
                    if ($l1Cpc > 0) {
                        $sbid = floor($l1Cpc * 0.90 * 100) / 100;
                    } elseif ($l7Cpc > 0) {
                        $sbid = floor($l7Cpc * 0.90 * 100) / 100;
                    } elseif ($avgCpc > 0) {
                        $sbid = floor($avgCpc * 0.90 * 100) / 100;
                    } else {
                        $sbid = 1.00;
                    }
                } elseif ($utilizationType === 'under') {
                    // Under-utilized: Priority L1 CPC → L7 CPC → AVG CPC → 1.00, then increase by 10%
                    if ($l1Cpc > 0) {
                        $sbid = floor($l1Cpc * 1.10 * 100) / 100;
                    } elseif ($l7Cpc > 0) {
                        $sbid = floor($l7Cpc * 1.10 * 100) / 100;
                    } elseif ($avgCpc > 0) {
                        $sbid = floor($avgCpc * 1.10 * 100) / 100;
                    } else {
                        $sbid = 1.00;
                    }
                } else {
                    // Correctly-utilized or all: no SBID change needed
                    $sbid = 0;
                }
                
                // Apply price-based caps
                if ($price < 10 && $sbid > 0.10) {
                    $sbid = 0.10;
                } elseif ($price >= 10 && $price < 20 && $sbid > 0.20) {
                    $sbid = 0.20;
                }
                
                return [
                    'campaign_name' => $campaign->campaignName,
                    'bgt' => $budget,
                    'sbgt' => $sbgt,
                    'acos' => round($acos, 2),
                    'clicks' => $l30Clicks,
                    'ad_spend' => $l30Spend,
                    'ad_sales' => $l30Sales,
                    'ad_sold' => $l30UnitsSold,
                    'ad_cvr' => round($adCvr, 2),
                    '7ub' => round($ub7, 2),
                    '1ub' => round($ub1, 2),
                    'avg_cpc' => round($avgCpc, 2),
                    'l7cpc' => round($l7Cpc, 2),
                    'l1cpc' => round($l1Cpc, 2),
                    'l_bid' => $lastSbid,
                    'sbid' => (($utilizationType === 'over' || $utilizationType === 'under' || $isZeroUtilizationPt) && $sbid > 0) ? round($sbid, 2) : 0,
                    'utilization_type' => $utilizationType,
                ];
            })
            ->filter()
            ->values();
        
        // Check for HL campaigns (SPONSORED_BRANDS)
        // HL campaigns match by parent SKU (without "PARENT" prefix) or parent SKU + ' HEAD'
        // Use the price already fetched above (matching AmazonSpBudgetController logic)
        $hlMatchSku = $cleanSku;
        $hlPrice = $price; // Use price already fetched (matches AmazonSpBudgetController line 2351)
        
        // Determine matching SKU for HL campaigns (remove PARENT prefix for matching)
        if (stripos($cleanSku, 'PARENT') !== false) {
            $hlMatchSku = str_replace('PARENT ', '', $cleanSku);
        } else {
            // If SKU doesn't contain PARENT, find parent SKU from ProductMaster
            $productMaster = ProductMaster::where('sku', $cleanSku)->first();
            if ($productMaster && $productMaster->parent) {
                $hlMatchSku = strtoupper(trim($productMaster->parent));
            }
        }
        
        $hlCampaigns = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
            ->where(function($q) {
                $q->where('report_date_range', 'L30')
                  ->orWhere('report_date_range', 'L7')
                  ->orWhere('report_date_range', 'L1');
            })
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->get()
            ->filter(function($item) use ($hlMatchSku, $cleanSku) {
                $campaignName = strtoupper(trim($item->campaignName));
                // Check both with and without PARENT prefix
                // Some HL campaigns might be named "PARENT DS 01" or "DS 01"
                $expected1 = $hlMatchSku;
                $expected2 = $hlMatchSku . ' HEAD';
                $expected3 = $cleanSku; // Original SKU with PARENT prefix
                $expected4 = $cleanSku . ' HEAD';
                return ($campaignName === $expected1 || $campaignName === $expected2 || 
                        $campaignName === $expected3 || $campaignName === $expected4);
            })
            ->groupBy('campaign_id')
            ->map(function($group) use ($hlPrice, $dayBeforeYesterday, $yesterday) {
                $l30 = $group->where('report_date_range', 'L30')->first();
                $l7 = $group->where('report_date_range', 'L7')->first();
                $l1 = $group->where('report_date_range', 'L1')->first();
                
                $campaign = $l30 ?? $l7 ?? $l1;
                if (!$campaign) return null;
                
                // Get L7 and L1 from separate queries if not in group
                if (!$l7) {
                    $l7 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                        ->where('report_date_range', 'L7')
                        ->where('campaign_id', $campaign->campaign_id)
                        ->where('campaignStatus', '!=', 'ARCHIVED')
                        ->first();
                }
                if (!$l1) {
                    $l1 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                        ->where('report_date_range', 'L1')
                        ->where('campaign_id', $campaign->campaign_id)
                        ->where('campaignStatus', '!=', 'ARCHIVED')
                        ->first();
                }
                
                $budget = floatval($campaign->campaignBudgetAmount ?? 0);
                $l7Spend = $l7 ? floatval($l7->cost ?? $l7->spend ?? 0) : 0;
                $l1Spend = $l1 ? floatval($l1->cost ?? $l1->spend ?? 0) : 0;
                $l7Clicks = $l7 ? floatval($l7->clicks ?? 0) : 0;
                $l1Clicks = $l1 ? floatval($l1->clicks ?? 0) : 0;
                $l30Clicks = $l30 ? floatval($l30->clicks ?? 0) : 0;
                
                // For HL campaigns, calculate CPC as cost / clicks (not using costPerClick directly)
                $l7Cpc = 0;
                if ($l7 && $l7Clicks > 0) {
                    $l7Cpc = $l7Spend / $l7Clicks;
                }
                
                $l1Cpc = 0;
                if ($l1 && $l1Clicks > 0) {
                    $l1Cpc = $l1Spend / $l1Clicks;
                }
                
                $campaignId = $campaign->campaign_id ?? null;
                $avgCpc = 0;
                if ($campaignId) {
                    try {
                        // For HL campaigns, calculate avg_cpc as AVG(cost / clicks) from daily records
                        $avgCpcRecord = DB::table('amazon_sb_campaign_reports')
                            ->select(DB::raw('AVG(CASE WHEN clicks > 0 THEN cost / clicks ELSE 0 END) as avg_cpc'))
                            ->where('campaign_id', $campaignId)
                            ->where('ad_type', 'SPONSORED_BRANDS')
                            ->where('campaignStatus', '!=', 'ARCHIVED')
                            ->where('report_date_range', 'REGEXP', '^[0-9]{4}-[0-9]{2}-[0-9]{2}$')
                            ->whereNotNull('campaign_id')
                            ->first();
                        
                        if ($avgCpcRecord && $avgCpcRecord->avg_cpc > 0) {
                            $avgCpc = floatval($avgCpcRecord->avg_cpc);
                        }
                    } catch (\Exception $e) {
                        // Continue without avg_cpc if there's an error
                    }
                }
                
                $ub7 = $budget > 0 ? ($l7Spend / ($budget * 7)) * 100 : 0;
                $ub1 = $budget > 0 ? ($l1Spend / $budget) * 100 : 0;
                
                // For HL campaigns, use 'cost' and 'sales' (not 'spend' and 'sales30d')
                $l30Spend = $l30 ? floatval($l30->cost ?? 0) : 0;
                $l30Sales = $l30 ? floatval($l30->sales ?? 0) : 0;
                $l30Purchases = $l30 ? floatval($l30->unitsSold ?? 0) : 0;
                $l30UnitsSold = $l30 ? floatval($l30->unitsSold ?? 0) : 0;
                
                $adCvr = $l30Clicks > 0 ? (($l30Purchases / $l30Clicks) * 100) : 0;
                
                // Calculate ACOS for HL campaigns
                if ($l30Spend > 0 && $l30Sales > 0) {
                    $acos = ($l30Spend / $l30Sales) * 100;
                } elseif ($l30Spend > 0 && $l30Sales == 0) {
                    $acos = 100;
                } else {
                    $acos = 0;
                }
                
                $sbgt = AmazonAcosSbgtRule::sbgtFromAcosL30((float) $acos);
                
                // Fetch last_sbid from day-before-yesterday's records for HL campaigns
                $lastSbidMapHl = [];
                $lastSbidReportsHl = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                    ->where(function($q) use ($dayBeforeYesterday, $yesterday) {
                        $q->where('report_date_range', $dayBeforeYesterday)
                          ->orWhere('report_date_range', $yesterday);
                    })
                    ->where('campaignStatus', '!=', 'ARCHIVED')
                    ->get()
                    ->sortBy(function($report) use ($dayBeforeYesterday) {
                        return $report->report_date_range === $dayBeforeYesterday ? 0 : 1;
                    })
                    ->groupBy('campaign_id');
                
                foreach ($lastSbidReportsHl as $campaignIdHl => $reports) {
                    $report = $reports->first();
                    $campaignIdStrHl = (string)$campaignIdHl;
                    if (!empty($campaignIdStrHl) && isset($report->last_sbid) && $report->last_sbid !== '' && $report->last_sbid !== null) {
                        $lastSbidMapHl[$campaignIdStrHl] = $report->last_sbid;
                    } elseif (!empty($campaignIdStrHl) && isset($report->last_sbid) && ($report->last_sbid === 0 || $report->last_sbid === '0')) {
                        $lastSbidMapHl[$campaignIdStrHl] = $report->last_sbid;
                    }
                }
                
                $campaignIdStr = $campaignId ? (string)$campaignId : null;
                $lastSbid = '';
                if ($campaignIdStr && isset($lastSbidMapHl[$campaignIdStr])) {
                    $lastSbid = $lastSbidMapHl[$campaignIdStr];
                }
                
                // Calculate SBID for HL (same tier idea as the removed HL utilized grid)
                $sbid = 0;
                $utilizationType = 'all';
                if ($ub7 > 99 && $ub1 > 99) {
                    $utilizationType = 'over';
                } elseif ($ub7 < 66 && $ub1 < 66) {
                    $utilizationType = 'under';
                } elseif ($ub7 >= 66 && $ub7 <= 99 && $ub1 >= 66 && $ub1 <= 99) {
                    $utilizationType = 'correctly';
                }
                
                // Special case: If UB7 and UB1 = 0%, use default value
                if ($ub7 == 0 && $ub1 == 0) {
                    $sbid = 1.00;
                } elseif ($utilizationType === 'over') {
                    // Over-utilized: Priority L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
                    if ($l1Cpc > 0) {
                        $sbid = floor($l1Cpc * 0.90 * 100) / 100;
                    } elseif ($l7Cpc > 0) {
                        $sbid = floor($l7Cpc * 0.90 * 100) / 100;
                    } elseif ($avgCpc > 0) {
                        $sbid = floor($avgCpc * 0.90 * 100) / 100;
                    } else {
                        $sbid = 1.00;
                    }
                } elseif ($utilizationType === 'under') {
                    // Under-utilized: Priority L1 CPC → L7 CPC → AVG CPC → 1.00, then increase by 10%
                    if ($l1Cpc > 0) {
                        $sbid = floor($l1Cpc * 1.10 * 100) / 100;
                    } elseif ($l7Cpc > 0) {
                        $sbid = floor($l7Cpc * 1.10 * 100) / 100;
                    } elseif ($avgCpc > 0) {
                        $sbid = floor($avgCpc * 1.10 * 100) / 100;
                    } else {
                        $sbid = 1.00;
                    }
                } else {
                    // Correctly-utilized or all: no SBID change needed (set to 0)
                    $sbid = 0;
                }
                
                return [
                    'campaign_name' => $campaign->campaignName,
                    'bgt' => $budget,
                    'sbgt' => $sbgt,
                    'acos' => round($acos, 2),
                    'clicks' => $l30Clicks,
                    'ad_spend' => $l30Spend,
                    'ad_sales' => $l30Sales,
                    'ad_sold' => $l30UnitsSold,
                    'ad_cvr' => round($adCvr, 2),
                    '7ub' => round($ub7, 2),
                    '1ub' => round($ub1, 2),
                    'avg_cpc' => round($avgCpc, 2),
                    'l7cpc' => round($l7Cpc, 2),
                    'l1cpc' => round($l1Cpc, 2),
                    'l_bid' => $lastSbid,
                    'sbid' => ($utilizationType === 'over' || $utilizationType === 'under') && $sbid > 0 ? round($sbid, 2) : 0,
                    'utilization_type' => $utilizationType,
                ];
            })
            ->filter()
            ->values();
        
        // If HL campaigns exist, only return HL campaigns (not KW/PT)
        if ($hlCampaigns->count() > 0) {
            return response()->json([
                'hl_campaigns' => $hlCampaigns,
                'kw_campaigns' => [],
                'pt_campaigns' => []
            ]);
        }
        
        return response()->json([
            'kw_campaigns' => $kwCampaigns,
            'pt_campaigns' => $ptCampaigns,
            'hl_campaigns' => []
        ]);
    }

    /**
     * Save daily badge stats (called from frontend after stats are computed)
     */
    public function saveAmazonBadgeStats(Request $request)
    {
        try {
            $today = now('America/Los_Angeles')->toDateString();

            \App\Models\AmazonDailyBadgeStat::updateOrCreate(
                ['snapshot_date' => $today],
                [
                    'sold_count' => intval($request->input('sold_count', 0)),
                    'zero_sold_count' => intval($request->input('zero_sold_count', 0)),
                    'map_count' => intval($request->input('map_count', 0)),
                    'nmap_count' => intval($request->input('nmap_count', 0)),
                    'missing_count' => intval($request->input('missing_count', 0)),
                    'prc_gt_lmp_count' => intval($request->input('prc_gt_lmp_count', 0)),
                    'campaign_count' => intval($request->input('campaign_count', 0)),
                    'missing_campaign_count' => intval($request->input('missing_campaign_count', 0)),
                    'nra_count' => intval($request->input('nra_count', 0)),
                    'ra_count' => intval($request->input('ra_count', 0)),
                    'paused_count' => intval($request->input('paused_count', 0)),
                    'ub7_count' => intval($request->input('ub7_count', 0)),
                    'ub7_ub1_count' => intval($request->input('ub7_ub1_count', 0)),
                    'kw_spend' => floatval($request->input('kw_spend', 0)),
                    'hl_spend' => floatval($request->input('hl_spend', 0)),
                    'pt_spend' => floatval($request->input('pt_spend', 0)),
                    'total_pft' => floatval($request->input('total_pft', 0)),
                    'total_sales' => floatval($request->input('total_sales', 0)),
                    'total_spend' => floatval($request->input('total_spend', 0)),
                    'gpft_pct' => floatval($request->input('gpft_pct', 0)),
                    'npft_pct' => floatval($request->input('npft_pct', 0)),
                    'groi_pct' => floatval($request->input('groi_pct', 0)),
                    'nroi_pct' => floatval($request->input('nroi_pct', 0)),
                    'tcos_pct' => floatval($request->input('tcos_pct', 0)),
                    'total_l30_orders' => intval($request->input('total_l30_orders', 0)),
                ]
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('saveAmazonBadgeStats error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error saving badge stats'], 500);
        }
    }

    /**
     * Get badge stat chart data for a specific metric
     */
    public function getAmazonBadgeChartData(Request $request)
    {
        try {
            $metric = $request->input('metric', 'sold_count');
            $days = intval($request->input('days', 30));

            $allowedMetrics = [
                'sold_count', 'zero_sold_count', 'map_count', 'nmap_count',
                'missing_count', 'prc_gt_lmp_count', 'campaign_count',
                'missing_campaign_count', 'nra_count', 'ra_count',
                'paused_count', 'ub7_count', 'ub7_ub1_count',
                'kw_spend', 'hl_spend', 'pt_spend',
                'total_pft', 'total_sales', 'total_spend',
                'gpft_pct', 'npft_pct', 'groi_pct', 'nroi_pct', 'tcos_pct',
                'total_l30_orders',
            ];

            if (!in_array($metric, $allowedMetrics)) {
                return response()->json(['success' => false, 'message' => 'Invalid metric'], 400);
            }

            $query = \App\Models\AmazonDailyBadgeStat::orderBy('snapshot_date', 'asc');

            if ($days > 0) {
                $startDate = now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $startDate);
            }

            $rows = $query->get();

            $chartData = $rows->map(function ($row) use ($metric) {
                return [
                    'date' => \Carbon\Carbon::parse($row->snapshot_date)->format('M d'),
                    'value' => floatval($row->$metric),
                ];
            })->values()->toArray();

            return response()->json(['success' => true, 'data' => $chartData]);
        } catch (\Exception $e) {
            Log::error('getAmazonBadgeChartData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching chart data'], 500);
        }
    }

    /**
     * Get KW Last SBID date-wise chart data for a campaign (daily last_sbid from amazon_sp_campaign_reports).
     */
    public function getAmazonKwLastSbidChartData(Request $request)
    {
        try {
            $campaignId = $request->input('campaign_id');
            $days = min(90, max(7, intval($request->input('days', 30))));

            if (empty($campaignId)) {
                return response()->json(['success' => false, 'message' => 'campaign_id required'], 400);
            }

            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            $rows = DB::table('amazon_kw_last_sbid_daily')
                ->select('report_date', 'last_sbid', 'campaign_name')
                ->where('campaign_id', $campaignId)
                ->where('report_date', '>=', $startDate)
                ->orderBy('report_date', 'asc')
                ->get();

            $chartData = $rows->map(function ($row) {
                $val = $row->last_sbid;
                if ($val !== null && $val !== '') {
                    $val = floatval($val);
                } else {
                    $val = null;
                }
                $rawDate = $row->report_date instanceof \DateTimeInterface
                    ? $row->report_date->format('Y-m-d')
                    : (string) $row->report_date;
                return [
                    'date' => \Carbon\Carbon::parse($rawDate)->format('M d'),
                    'value' => $val,
                    'raw_date' => $rawDate,
                ];
            })->values()->toArray();

            $campaignName = $rows->isNotEmpty() ? ($rows->first()->campaign_name ?? '') : '';

            // Fallback: if no daily history, show current last_sbid (same source as KW Last SBID column)
            if (empty($chartData)) {
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $dayBefore = date('Y-m-d', strtotime('-2 days'));
                // Match table: yesterday, day-before, L1, L7 (KW only: exclude PT)
                $current = DB::table('amazon_sp_campaign_reports')
                    ->select('report_date_range', 'last_sbid', 'campaignName')
                    ->where('campaign_id', $campaignId)
                    ->where('ad_type', 'SPONSORED_PRODUCTS')
                    ->where(function ($q) {
                        $q->where('campaignName', 'NOT LIKE', '%PT')
                          ->where('campaignName', 'NOT LIKE', '%PT.');
                    })
                    ->where(function ($q) use ($yesterday, $dayBefore) {
                        $q->where('report_date_range', $yesterday)
                          ->orWhere('report_date_range', $dayBefore)
                          ->orWhereIn('report_date_range', ['L1', 'L7', 'L30']);
                    })
                    ->whereNotNull('last_sbid')
                    ->orderByRaw("CASE WHEN report_date_range = '{$yesterday}' THEN 0 WHEN report_date_range = '{$dayBefore}' THEN 1 WHEN report_date_range = 'L1' THEN 2 WHEN report_date_range = 'L7' THEN 3 ELSE 4 END")
                    ->first();
                $val = $current ? (trim((string)$current->last_sbid) !== '' ? floatval($current->last_sbid) : null) : null;
                if ($current && $val !== null) {
                    $rawDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $current->report_date_range) ? $current->report_date_range : $yesterday;
                    $chartData = [[
                        'date' => \Carbon\Carbon::parse($rawDate)->format('M d'),
                        'value' => $val,
                        'raw_date' => $rawDate,
                    ]];
                    if (empty($campaignName) && !empty($current->campaignName)) {
                        $campaignName = $current->campaignName;
                    }
                }
            }

            // If still empty, use value from table cell (passed as current_value) so chart always shows what table shows
            if (empty($chartData)) {
                $currentValue = $request->input('current_value');
                if ($currentValue !== null && $currentValue !== '' && is_numeric($currentValue)) {
                    $today = date('Y-m-d');
                    $chartData = [[
                        'date' => \Carbon\Carbon::parse($today)->format('M d'),
                        'value' => floatval($currentValue),
                        'raw_date' => $today,
                    ]];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $chartData,
                'campaign_name' => $campaignName,
            ]);
        } catch (\Exception $e) {
            Log::error('getAmazonKwLastSbidChartData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching KW Last SBID chart data'], 500);
        }
    }

    /**
     * Child SKUs ending in "FBA" with no FBA inventory: treat KW/PT/HL campaign status as PAUSED in the Amazon tabulator.
     */
    private function applyAmazonTabulatorFbaSuffixZeroFbaInvAdPauseOverlay(array &$row): void
    {
        $child = isset($row['(Child) sku']) ? (string) $row['(Child) sku'] : '';
        if (! FbaInventoryService::childSkuHasFbaSuffix($child)) {
            return;
        }
        if ((int) ($row['FBA_Quantity'] ?? 0) > 0) {
            return;
        }
        if (isset($row['kw_campaign_status']) && (string) $row['kw_campaign_status'] !== '') {
            $row['kw_campaign_status'] = 'PAUSED';
        }
        if (! empty($row['campaign_id']) || ! empty($row['campaignName'])) {
            $row['campaignStatus'] = 'PAUSED';
        }
        if (isset($row['pt_campaign_status']) && (string) $row['pt_campaign_status'] !== '') {
            $row['pt_campaign_status'] = 'PAUSED';
        }
        if (isset($row['hl_campaign_status']) && (string) $row['hl_campaign_status'] !== '') {
            $row['hl_campaign_status'] = 'PAUSED';
        }
    }
}
