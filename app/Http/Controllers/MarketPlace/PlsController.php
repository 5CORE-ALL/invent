<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\ChannelMaster;
use App\Models\MarketplacePercentage;
use App\Models\PLSDataView;
use App\Models\PLSProduct;
use App\Models\PlsListingStatus;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PlsController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function plsPricingCVR(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $percentage = Cache::remember('Walmart', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.walmartPricingCvr', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }

    /**
     * PLS Sales View - Shows last 30 days of sales
     */
    public function salesView(Request $request)
    {
        return view('market-places.pls_sales_view');
    }

    /**
     * PLS Sales Data JSON - Returns last 30 days of sales data
     */
    public function salesDataJson(Request $request)
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        $sales = \App\Models\PlsSale::where('order_date', '>=', $thirtyDaysAgo)
            ->orderBy('order_date', 'desc')
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'order_date' => $sale->order_date ? $sale->order_date->format('Y-m-d') : '',
                    'order_number' => $sale->order_number,
                    'order_name' => $sale->order_name,
                    'sku' => $sale->sku,
                    'product_title' => $sale->product_title,
                    'variant_title' => $sale->variant_title,
                    'quantity' => $sale->quantity,
                    'price' => $sale->price,
                    'total_amount' => $sale->total_amount,
                    'discount_amount' => $sale->discount_amount,
                    'tax_amount' => $sale->tax_amount,
                    'financial_status' => $sale->financial_status,
                    'fulfillment_status' => $sale->fulfillment_status,
                    'customer_email' => $sale->customer_email,
                    'customer_name' => $sale->customer_name,
                    'currency' => $sale->currency,
                ];
            });

        return response()->json($sales);
    }

    /**
     * PLS Pricing View - Shows pricing and inventory data
     */
    public function pricingView(Request $request)
    {
        // Get PLS marketplace percentage from database
        $plsPercentage = MarketplacePercentage::where('marketplace', 'LIKE', '%PLS%')->value('percentage') ?? 100;
        
        return view('market-places.pls_pricing_view', [
            'plsPercentage' => $plsPercentage
        ]);
    }

    /**
     * PLS Pricing Data JSON - Returns combined pricing and inventory data
     */
    public function pricingDataJson(Request $request)
    {
        // 1. Base ProductMaster fetch - Sort by SKU only (ascending order)
        $productMasters = ProductMaster::orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        // Filter out PARENT items
        $productMasters = $productMasters->filter(function ($item) {
            return stripos($item->sku, 'PARENT') === false;
        })->values();

        // 2. Get SKU list
        $skus = $productMasters->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Get PLS marketplace percentage from marketplace_percentages table
        $plsPercentage = MarketplacePercentage::where('marketplace', 'LIKE', '%PLS%')->value('percentage') ?? 100;
        $plsPercentage = $plsPercentage / 100; // convert to fraction

        // 3. Get inventory and L30 from shopify_skus table (like Purchasing Power page)
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // 4. Get PLS products data (price, L30, L60) — normalized keys (NBSP vs space tolerant)
        $plsProducts = PLSProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get()
            ->keyBy(fn ($item) => ShopifySku::normalizeSkuForShopifyLookup($item->sku));

        // 5. Get PLS inventory from shopify_catalog_variants — same normalization as shopify_skus
        $catalogVariants = \DB::table('shopify_catalog_variants')
            ->where('store', 'pls')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->select('sku', 'inventory_quantity', 'price')
            ->get()
            ->groupBy(fn ($item) => ShopifySku::normalizeSkuForShopifyLookup($item->sku));

        // 6. Get SPRICE, SGPFT, SROI from pls_data_views
        $plsDataViews = $this->buildPlsDataViewLookupByNormalizedSku($skus);

        // 6b. Get PLS_STATUS from amazon_data_views
        $amazonDataViews = $this->buildAmazonDataViewLookupByNormalizedSku($skus);

        // 6b-2. Get Amazon price from amazon_datsheets (normalized sku lookup)
        $amazonPriceBySku = [];
        foreach (\App\Models\AmazonDatasheet::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'price']) as $amzRow) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($amzRow->sku);
            if ($key !== '' && ! isset($amazonPriceBySku[$key])) {
                $amazonPriceBySku[$key] = floatval($amzRow->price);
            }
        }

        // 6c. Buyer / Seller links from pls_listing_statuses
        $plsLinkBySku = [];
        foreach (PlsListingStatus::whereIn('sku', $skus)->get() as $linkRow) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($linkRow->sku);
            if ($key !== '' && ! isset($plsLinkBySku[$key])) {
                $plsLinkBySku[$key] = $linkRow;
            }
        }

        // 7. Build Result
        $data = [];

        foreach ($productMasters as $pm) {
            $skuNorm = ShopifySku::normalizeSkuForShopifyLookup($pm->sku);
            
            // Get related data
            $shopify = $shopifyData->get($pm->sku);
            $plsProduct = $plsProducts->get($skuNorm);
            $plsCatalogVariants = $catalogVariants->get($skuNorm);
            
            // Basic info
            $row = [];
            $row['parent'] = $pm->parent ?? '';
            $row['sku'] = $pm->sku;
            $row['title'] = $pm->title ?? '';
            
            // Parse Values JSON for LP, Ship, and Image Path
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === "lp") {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            
            $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
            
            // Get image_path from Values JSON or ProductMaster direct property (same as Temu2)
            $imagePath = $values['image_path'] ?? ($pm->image_path ?? null);
            
            // Get inventory and OV L30 from shopify_skus (like Purchasing Power page)
            $inventory = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $ovl30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
            
            // Get PLS inventory from shopify_catalog_variants
            $plsInventory = $plsCatalogVariants ? $plsCatalogVariants->sum('inventory_quantity') : 0;
            
            // Get SPRICE, SGPFT, SROI from pls_data_views
            $plsDataView = $plsDataViews[$skuNorm] ?? null;
            $sprice = null;
            $sgpft = null;
            $sroi = null;
            $hasCustomSprice = false;
            
            if ($plsDataView) {
                $dataValue = is_array($plsDataView->value) 
                    ? $plsDataView->value 
                    : (is_string($plsDataView->value) ? json_decode($plsDataView->value, true) : []);
                
                if (is_array($dataValue)) {
                    $sprice = isset($dataValue['sprice']) ? floatval($dataValue['sprice']) : null;
                    $sgpft = isset($dataValue['sgpft']) ? floatval($dataValue['sgpft']) : null;
                    $sroi = isset($dataValue['sroi']) ? floatval($dataValue['sroi']) : null;
                    $hasCustomSprice = $sprice !== null && $sprice > 0;
                }
            }
            
            // Sales data from pls_products (price, L30 for MC L30 column, L60)
            $price = $plsProduct ? floatval($plsProduct->price) : 0;
            $plsL30 = $plsProduct ? intval($plsProduct->p_l30) : 0;
            $l60 = $plsProduct ? intval($plsProduct->p_l60) : 0;
            
            $row['image_path'] = $imagePath;
            $row['price'] = $price;
            $row['amazon_price'] = $amazonPriceBySku[$skuNorm] ?? 0;
            $row['lp'] = $lp;
            $row['ship'] = $ship;
            $row['inventory'] = $inventory;
            $row['pls_inventory'] = $plsInventory;  // PLS marketplace inventory
            $row['l30'] = $ovl30;  // OV L30 - Our Velocity from Shopify
            $row['l60'] = $l60;    // PLS L60 from marketplace
            $row['pls_l30'] = $plsL30;  // PLS marketplace L30 sold
            $row['pls_l60'] = $l60;     // PLS marketplace L60 sold
            
            // Calculate GPFT (with marketplace percentage)
            $gpft = 0;
            $gpftPct = 0;
            $roiPct = 0;
            
            if ($price > 0) {
                $gpft = ($price * $plsPercentage) - $lp - $ship;
                $gpftPct = ($gpft / $price) * 100;
            }
            
            if ($lp > 0) {
                $roiPct = ((($price * $plsPercentage) - $lp - $ship) / $lp) * 100;
            }
            
            $row['gpft'] = round($gpft, 2);
            $row['gpft_pct'] = round($gpftPct, 2);
            $row['roi_pct'] = round($roiPct, 2);
            
            // Add SPRICE, SGPFT%, SROI% from pls_data_views
            $row['sprice'] = $sprice;
            $row['sgpft'] = $sgpft;
            $row['sroi'] = $sroi;
            $row['has_custom_sprice'] = $hasCustomSprice;
            
            // Get PLS_STATUS from amazon_data_views
            $amazonDataView = $amazonDataViews[$skuNorm] ?? null;
            $plsStatus = null;
            
            if ($amazonDataView) {
                $amazonValue = is_array($amazonDataView->value) 
                    ? $amazonDataView->value 
                    : (is_string($amazonDataView->value) ? json_decode($amazonDataView->value, true) : []);
                
                if (is_array($amazonValue)) {
                    $plsStatus = isset($amazonValue['PLS_STATUS']) ? $amazonValue['PLS_STATUS'] : null;
                }
            }
            
            $row['pls_status'] = $plsStatus;

            // Buyer / Seller links
            $linkRecord = $plsLinkBySku[$skuNorm] ?? null;
            $linkVal = $linkRecord
                ? (is_array($linkRecord->value) ? $linkRecord->value : (json_decode($linkRecord->value, true) ?: []))
                : [];
            $row['buyer_link'] = $linkVal['buyer_link'] ?? '';
            $row['seller_link'] = $linkVal['seller_link'] ?? '';

            // Total profit based on OV L30 (our sales)
            $row['total_pft_l30'] = round($gpft * $ovl30, 2);
            
            // Sales value L30 (our sales)
            $row['sales_l30'] = round($price * $ovl30, 2);
            
            // DIL% (Days of Inventory Left based on OV L30 sales rate)
            $row['dil_pct'] = ($ovl30 > 0 && $inventory > 0) 
                ? round(($ovl30 / $inventory) * 100, 2) 
                : 0;
            
            // Calculate Missing status (same logic as Temu2)
            // Missing = Not in pls_products OR (in pls_products but INV > 0 and price <= 0)
            $inPricing = $plsProduct !== null && $price > 0;
            $missing = $inPricing ? '' : 'M';
            if ($inPricing && $inventory > 0 && $price <= 0) {
                $missing = 'M';
            }
            if ($inPricing && $inventory <= 0 && $price > 0) {
                $missing = '';
            }
            
            $row['missing'] = $missing;
            
            $data[] = $row;
        }
        
        return response()->json($data);
    }

    /**
     * @param  array<int, string>  $productSkus
     * @return array<string, PLSDataView>
     */
    private static function buildPlsDataViewLookupByNormalizedSku(array $productSkus): array
    {
        $lookup = [];
        foreach (PLSDataView::whereIn('sku', $productSkus)->get() as $row) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
            if ($key !== '' && ! isset($lookup[$key])) {
                $lookup[$key] = $row;
            }
        }

        $missing = [];
        foreach ($productSkus as $pmSku) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $pmSku);
            if ($key !== '' && ! isset($lookup[$key])) {
                $missing[$key] = true;
            }
        }

        if ($missing !== []) {
            PLSDataView::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$lookup, &$missing) {
                    foreach ($rows as $row) {
                        $key = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
                        if ($key !== '' && isset($missing[$key]) && ! isset($lookup[$key])) {
                            $lookup[$key] = $row;
                            unset($missing[$key]);
                        }
                    }

                    return count($missing) > 0;
                });
        }

        return $lookup;
    }

    /**
     * @param  array<int, string>  $productSkus
     * @return array<string, \App\Models\AmazonDataView>
     */
    private static function buildAmazonDataViewLookupByNormalizedSku(array $productSkus): array
    {
        $lookup = [];
        foreach (\App\Models\AmazonDataView::whereIn('sku', $productSkus)->get() as $row) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
            if ($key !== '' && ! isset($lookup[$key])) {
                $lookup[$key] = $row;
            }
        }

        $missing = [];
        foreach ($productSkus as $pmSku) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $pmSku);
            if ($key !== '' && ! isset($lookup[$key])) {
                $missing[$key] = true;
            }
        }

        if ($missing !== []) {
            \App\Models\AmazonDataView::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$lookup, &$missing) {
                    foreach ($rows as $row) {
                        $key = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
                        if ($key !== '' && isset($missing[$key]) && ! isset($lookup[$key])) {
                            $lookup[$key] = $row;
                            unset($missing[$key]);
                        }
                    }

                    return count($missing) > 0;
                });
        }

        return $lookup;
    }

    /**
     * Map / Miss / NMap for pls-pricing badges and all-marketplace-master PLS row.
     * Matches pls_pricing_view updateSummary() + MAP / N MP column (|INV − PLS INV| ≤ 3).
     *
     * Missing L: INV > 0 and price <= 0 (listed as Missing on pricing page).
     * N Map: not Missing + INV > 0 + inventory mismatch beyond 3-unit tolerance.
     * Map: not Missing + INV > 0 + within 3-unit tolerance (MP / green MAP column).
     */
    public static function countPlsPricingBadgeTotals(iterable $rows): array
    {
        $map = 0;
        $miss = 0;
        $nmap = 0;

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row)) {
                continue;
            }

            $inv = (float) ($row['inventory'] ?? 0);
            $plsInv = (float) ($row['pls_inventory'] ?? 0);
            $price = (float) ($row['price'] ?? 0);
            $missing = (string) ($row['missing'] ?? '');

            if ($inv > 0 && $price <= 0) {
                $miss++;
            }

            if ($missing !== 'M') {
                if ($inv > 0 && $plsInv === 0.0 && $inv > 3) {
                    $nmap++;
                } elseif ($inv > 0 && $plsInv > 0) {
                    if ($inv !== $plsInv && abs($inv - $plsInv) > 3) {
                        $nmap++;
                    } else {
                        $map++;
                    }
                } elseif ($inv > 0 && $plsInv === 0.0 && $inv <= 3) {
                    $map++;
                }
            }
        }

        return [
            'map' => $map,
            'miss' => $miss,
            'nmap' => $nmap,
            'total_views' => 0,
        ];
    }

    /**
     * Save PLS SPRICE and calculate SGPFT% and SROI%
     */
    public function savePlsSprice(Request $request)
    {
        $sku = $request->input('sku');
        $sprice = $request->input('sprice');

        if (!$sku || !$sprice) {
            return response()->json(['error' => 'SKU and SPRICE are required'], 400);
        }

        // Get product master data for LP and Ship
        $productMaster = ProductMaster::where('sku', $sku)->first();
        
        if (!$productMaster) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Parse Values JSON for LP and Ship
        $values = is_array($productMaster->Values) 
            ? $productMaster->Values 
            : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
        
        $lp = 0;
        foreach ($values as $k => $v) {
            if (strtolower($k) === "lp") {
                $lp = floatval($v);
                break;
            }
        }
        if ($lp === 0 && isset($productMaster->lp)) {
            $lp = floatval($productMaster->lp);
        }
        
        $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($productMaster->ship) ? floatval($productMaster->ship) : 0);

        // Calculate SGPFT% = ((SPRICE - LP - Ship) / SPRICE) * 100
        $sgpft_percent = 0;
        if ($sprice > 0) {
            $sgpft_percent = (($sprice - $lp - $ship) / $sprice) * 100;
        }

        // Calculate SROI% = ((SPRICE - LP - Ship) / LP) * 100
        $sroi_percent = 0;
        if ($lp > 0) {
            $sroi_percent = (($sprice - $lp - $ship) / $lp) * 100;
        }

        // Save to pls_data_views table (create or update)
        $dataView = PLSDataView::firstOrNew(['sku' => $sku]);
        
        $currentValue = $dataView->value;
        if (is_string($currentValue)) {
            $currentValue = json_decode($currentValue, true) ?: [];
        } elseif (!is_array($currentValue)) {
            $currentValue = [];
        }
        
        $currentValue['sprice'] = floatval($sprice);
        $currentValue['sgpft'] = round($sgpft_percent, 2);
        $currentValue['sroi'] = round($sroi_percent, 2);
        
        $dataView->value = $currentValue;
        $dataView->save();

        return response()->json([
            'success' => true,
            'data' => floatval($sprice),
            'sgpft_percent' => round($sgpft_percent, 2),
            'sroi_percent' => round($sroi_percent, 2),
            'has_custom_sprice' => true
        ]);
    }

    /**
     * Batch-clear SPRICE for multiple PLS SKUs
     */
    public function clearPlsSprice(Request $request)
    {
        $updates = $request->input('updates', []);

        if (empty($updates)) {
            return response()->json(['error' => 'No updates provided'], 400);
        }

        $cleared = 0;
        foreach ($updates as $item) {
            $sku = $item['sku'] ?? null;
            if (!$sku) continue;

            $dataView = PLSDataView::firstOrNew(['sku' => $sku]);
            $currentValue = $dataView->value;
            if (is_string($currentValue)) {
                $currentValue = json_decode($currentValue, true) ?: [];
            } elseif (!is_array($currentValue)) {
                $currentValue = [];
            }

            unset($currentValue['sprice'], $currentValue['sgpft'], $currentValue['sroi']);
            $dataView->value = $currentValue;
            $dataView->save();
            $cleared++;
        }

        return response()->json([
            'success' => true,
            'message' => "SPRICE cleared for {$cleared} SKU(s)",
            'cleared' => $cleared
        ]);
    }

    /**
     * Save buyer / seller links for a SKU into pls_listing_statuses.value JSON.
     * Empty strings clear the link (URL validation only applies to non-empty values).
     */
    public function saveLinks(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }

        $buyerLink = trim((string) $request->input('buyer_link', ''));
        $sellerLink = trim((string) $request->input('seller_link', ''));

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $field => $val) {
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'Invalid URL for ' . $field], 422);
            }
        }

        $status = PlsListingStatus::firstOrNew(['sku' => $sku]);
        $existing = is_array($status->value)
            ? $status->value
            : (json_decode($status->value, true) ?: []);

        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;

        $status->value = $existing;
        $status->save();

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
    }
}


