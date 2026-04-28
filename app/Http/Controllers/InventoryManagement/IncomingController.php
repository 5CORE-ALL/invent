<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Http\Controllers\ShopifyApiInventoryController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ApiController;
use App\Models\IncomingData;
use App\Models\IncomingOrder;
use App\Models\IncomingReason;
use App\Models\AmazonDatasheet;
use App\Models\ChannelMaster;
use App\Models\IncomingReturnChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;



class IncomingController extends Controller
{

    protected $shopifyDomain;
    protected $shopifyApiKey;
    protected $shopifyPassword;

    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
        $this->shopifyDomain = config('services.shopify.store_url');
        $this->shopifyApiKey =  '9d5c067dd4bcaf83a72137dddab72a4d';  //config('services.shopify.api_key');
        $this->shopifyPassword =  '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'; //config('services.shopify.password');
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::select('id', 'name')->get();

        return view('inventory-management.incoming-view', compact('warehouses'));
    }

    /**
     * Resolve SKU / barcode to product master row (mobile scanner + optional auto-fill).
     * Uses the same resolution rules as WMS scan (ProductMaster::findByWmsScanCode): sku, barcode,
     * upc column, Values JSON (upc/gtin/ean/barcode), numeric leading-zero variants, shopify_skus.
     */
    public function lookupSku(Request $request)
    {
        $q = trim((string) $request->query('sku', ''));
        if ($q === '') {
            return response()->json(['found' => false, 'message' => 'SKU or barcode is required'], 422);
        }

        $pm = ProductMaster::findByWmsScanCode($q);

        if (! $pm) {
            return response()->json([
                'found' => false,
                'message' => 'No product found for this SKU or barcode.',
            ]);
        }

        return response()->json([
            'found' => true,
            'sku' => $pm->sku,
            'parent' => $pm->parent,
            'title' => $pm->title150 ?? $pm->title100 ?? $pm->sku,
            'product_master_id' => $pm->id,
        ]);
    }

    /**
     * Normalize image path/URL for browser use (aligned with customer-care SKU details).
     */
    protected function normalizeSuggestionImageUrl(?string $path): ?string
    {
        $p = trim((string) ($path ?? ''));
        if ($p === '') {
            return null;
        }
        if (str_starts_with($p, '//')) {
            return 'https:'.$p;
        }
        if (preg_match('#^https?://#i', $p) || str_starts_with($p, 'data:')) {
            return $p;
        }

        return '/'.ltrim(str_replace('\\', '/', $p), '/');
    }

    /**
     * Thumbnail URL: shopify_skus.image_src, then Values JSON, then product_master columns.
     */
    protected function resolveSuggestionImageUrl(?ProductMaster $p, ?ShopifySku $shopify): ?string
    {
        if ($shopify) {
            $u = $this->normalizeSuggestionImageUrl($shopify->image_src ?? null);
            if ($u) {
                return $u;
            }
        }

        if (! $p) {
            return null;
        }

        $values = $p->Values;
        if (! is_array($values)) {
            $values = [];
        }
        foreach (['image_path', 'main_image', 'image1', 'image2', 'image3'] as $k) {
            $u = $this->normalizeSuggestionImageUrl(isset($values[$k]) ? (string) $values[$k] : null);
            if ($u) {
                return $u;
            }
        }

        foreach (['image_path', 'main_image', 'main_image_brand', 'image1', 'image2', 'image3'] as $col) {
            if (! Schema::hasColumn('product_master', $col)) {
                continue;
            }
            $u = $this->normalizeSuggestionImageUrl($p->{$col} ?? null);
            if ($u) {
                return $u;
            }
        }

        return null;
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<string, ProductMaster>, 1: \Illuminate\Support\Collection<string, ShopifySku>}
     */
    protected function batchProductMasterAndShopifyBySkuKeys(Collection $skus): array
    {
        $keys = $skus->map(fn ($s) => strtolower(trim((string) $s)))->unique()->filter(fn ($k) => $k !== '')->values();
        if ($keys->isEmpty()) {
            return [collect(), collect()];
        }

        $placeholders = implode(',', array_fill(0, $keys->count(), '?'));
        $params = $keys->all();

        $select = ['id', 'sku', 'parent', 'barcode', 'title150', 'title100'];
        if (Schema::hasColumn('product_master', 'Values')) {
            $select[] = 'Values';
        }
        foreach (['image_path', 'main_image', 'main_image_brand', 'image1', 'image2', 'image3'] as $col) {
            if (Schema::hasColumn('product_master', $col)) {
                $select[] = $col;
            }
        }

        $pms = ProductMaster::query()
            ->select($select)
            ->whereRaw('LOWER(TRIM(sku)) IN ('.$placeholders.')', $params)
            ->get();

        $pmByLower = collect();
        foreach ($pms as $p) {
            $k = strtolower(trim((string) $p->sku));
            if (! $pmByLower->has($k)) {
                $pmByLower->put($k, $p);
            }
        }

        $shopifyByLower = collect();
        $shopifyRows = ShopifySku::query()
            ->whereRaw('LOWER(TRIM(sku)) IN ('.$placeholders.')', $params)
            ->get();
        foreach ($shopifyRows as $s) {
            $k = strtolower(trim((string) $s->sku));
            $existing = $shopifyByLower->get($k);
            $hasImg = trim((string) ($s->image_src ?? '')) !== '';
            if (! $existing) {
                $shopifyByLower->put($k, $s);
            } elseif ($hasImg && trim((string) ($existing->image_src ?? '')) === '') {
                $shopifyByLower->put($k, $s);
            }
        }

        return [$pmByLower, $shopifyByLower];
    }

    protected function normalizePublicDiskImageUrl(string $path): ?string
    {
        $p = trim(str_replace('\\', '/', $path));
        if ($p === '') {
            return null;
        }
        if (str_starts_with($p, '//')) {
            return 'https:'.$p;
        }
        if (preg_match('#^https?://#i', $p)) {
            return $p;
        }
        $p = ltrim($p, '/');
        if (str_starts_with($p, 'storage/')) {
            return asset($p);
        }

        return asset('storage/'.$p);
    }

    /**
     * URLs for user-uploaded photos only (inventories.incoming_images JSON), not catalog/Shopify.
     *
     * @param  mixed  $incoming
     * @return list<string>
     */
    protected function resolveIncomingSavedPhotoUrlsFromArray($incoming): array
    {
        if (! is_array($incoming)) {
            return [];
        }
        $out = [];
        foreach ($incoming as $v) {
            if (is_string($v) && trim($v) !== '') {
                $u = $this->normalizePublicDiskImageUrl($v);
                if ($u) {
                    $out[] = $u;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  iterable<int, Inventory>  $items
     * @return list<string>
     */
    protected function mergeIncomingSavedPhotoUrlsFromItems(iterable $items): array
    {
        $paths = [];
        foreach ($items as $item) {
            $incoming = $item->incoming_images ?? null;
            if (! is_array($incoming)) {
                continue;
            }
            foreach ($incoming as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $paths[] = trim($v);
                }
            }
        }
        $paths = array_values(array_unique($paths));
        $urls = [];
        foreach ($paths as $p) {
            $u = $this->normalizePublicDiskImageUrl($p);
            if ($u) {
                $urls[] = $u;
            }
        }

        return $urls;
    }

    /**
     * @param  iterable<int, Inventory>  $items
     */
    protected function mergeIncomingVoicePathFromItems(iterable $items): ?string
    {
        $sorted = collect($items)->sortByDesc('id');
        foreach ($sorted as $item) {
            $v = trim((string) ($item->incoming_voice_note ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }

    protected function resolveInventoryRowImageUrl(Inventory $item, ?ProductMaster $pm, ?ShopifySku $shop): ?string
    {
        $url = $this->resolveSuggestionImageUrl($pm, $shop);
        if ($url) {
            return $url;
        }

        $incoming = $item->incoming_images;
        if (! is_array($incoming)) {
            return null;
        }
        foreach ($incoming as $v) {
            if (is_string($v) && trim($v) !== '') {
                $u = $this->normalizePublicDiskImageUrl($v);
                if ($u) {
                    return $u;
                }
            }
        }

        return null;
    }

    /**
     * SKU autocomplete for Incoming Return modal (product_master: sku, parent, barcode, upc).
     */
    public function suggestSkusForReturn(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(30, max(1, (int) $request->query('limit', 15)));

        if ($q === '') {
            return response()->json(['items' => []]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';

        $select = ['id', 'sku', 'parent', 'barcode', 'title150', 'title100'];
        if (Schema::hasColumn('product_master', 'Values')) {
            $select[] = 'Values';
        }
        foreach (['image_path', 'main_image', 'main_image_brand', 'image1', 'image2', 'image3'] as $col) {
            if (Schema::hasColumn('product_master', $col)) {
                $select[] = $col;
            }
        }

        $query = ProductMaster::query()
            ->select($select)
            ->where(function ($qq) use ($like) {
                $qq->where('sku', 'like', $like)
                    ->orWhere('parent', 'like', $like);

                $qq->orWhere(function ($b) use ($like) {
                    $b->whereNotNull('barcode')
                        ->where('barcode', '!=', '')
                        ->where('barcode', 'like', $like);
                });

                if (Schema::hasColumn('product_master', 'upc')) {
                    $qq->orWhere(function ($u) use ($like) {
                        $u->whereNotNull('upc')
                            ->where('upc', '!=', '')
                            ->where('upc', 'like', $like);
                    });
                }
            })
            ->orderBy('sku')
            ->limit($limit);

        $rows = $query->get();

        $skuKeys = $rows
            ->map(fn ($r) => strtolower(trim((string) ($r->sku ?? ''))))
            ->filter(fn ($k) => $k !== '')
            ->unique()
            ->values();

        $shopifyByLowerSku = collect();
        if ($skuKeys->isNotEmpty()) {
            $placeholders = implode(',', array_fill(0, $skuKeys->count(), '?'));
            $shopifyRows = ShopifySku::query()
                ->whereRaw('LOWER(TRIM(sku)) IN ('.$placeholders.')', $skuKeys->all())
                ->get();
            foreach ($shopifyRows as $s) {
                $k = strtolower(trim((string) $s->sku));
                $existing = $shopifyByLowerSku->get($k);
                $hasImg = trim((string) ($s->image_src ?? '')) !== '';
                if (! $existing) {
                    $shopifyByLowerSku->put($k, $s);
                } elseif ($hasImg && trim((string) ($existing->image_src ?? '')) === '') {
                    $shopifyByLowerSku->put($k, $s);
                }
            }
        }

        return response()->json([
            'items' => $rows->map(function ($r) use ($shopifyByLowerSku) {
                $k = strtolower(trim((string) ($r->sku ?? '')));
                $shop = $shopifyByLowerSku->get($k);

                return [
                    'sku' => $r->sku,
                    'parent' => $r->parent,
                    'label' => $r->title150 ?? $r->title100 ?? $r->sku,
                    'image_url' => $this->resolveSuggestionImageUrl($r, $shop),
                ];
            })->values(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(Request $request)
    // {   

    //     $request->validate([
    //         'sku' => 'required|string',
    //         'parent' => 'required|string',
    //         'qty' => 'required|integer|min:1',
    //         'warehouse_id' => 'required|exists:warehouses,id',
    //         'reason' => 'required|string',
    //         'date' => 'nullable',
    //     ]);

    //     $sku = trim($request->sku);
    //     $incomingQty = (int) $request->qty;

    //     try {

    //         $normalizedSku = strtoupper(preg_replace('/\s+/u', ' ', $sku));

    //         // Use same logic as updateVerifiedStock to get inventory_item_id
    //         $inventoryItemId = null;
    //         $pageInfo = null;

    //         do {
    //             $queryParams = ['limit' => 250];
    //             if ($pageInfo) $queryParams['page_info'] = $pageInfo;
                

    //             // $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //             //     ->get("https://5-core.myshopify.com/admin/api/2025-01/products.json", $queryParams);

    //             try {

    //             // $response = Http::withBasicAuth(config('services.shopify.api_key'),config('services.shopify.password') 
    //             // )->get("https://{$shopifyDomain}/admin/api/2025-01/products.json",$query_p);

    //             $response = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //                 ->get("https://5-core.myshopify.com/admin/api/2025-01/products.json", $queryParams);


    //                 // $response = Http::withHeaders([
    //                 //     'X-Shopify-Access-Token' => $accessToken,
    //                 // ])->get("https://{$shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

    //                 // dd($response);

    //             } catch (\Exception $e) {
    //                 Log::error("Incoming store failed for SKU $sku: " . $e->getMessage(), [
    //                     'trace' => $e->getTraceAsString()
    //                 ]);
    //                 return response()->json(['error' => 'Something went wrong.'], 500);
    //             }

    //             // if (!$response->successful()) {
    //             //     Log::error('Failed to fetch products from Shopify', $response->json());
    //             //     return response()->json(['error' => 'Failed to fetch products from Shopify'], 500);
    //             // }

    //             $products = $response->json('products');

    //             foreach ($products as $product) {
    //                 foreach ($product['variants'] as $variant) {
    //                     $variantSku = strtoupper(preg_replace('/\s+/u', ' ', trim($variant['sku'] ?? '')));

    //                     if ($variantSku === $normalizedSku) {
    //                         $inventoryItemId = $variant['inventory_item_id'];
    //                         break 2;
    //                     }
    //                 }
    //             }



    //             // Pagination support
    //             $linkHeader = $response->header('Link');
    //             $pageInfo = null;
    //             if ($linkHeader && preg_match('/<([^>]+page_info=([^&>]+)[^>]*)>; rel="next"/', $linkHeader, $matches)) {
    //                 $pageInfo = $matches[2];
    //             }
    //         } while (!$inventoryItemId && $pageInfo);

    //         if (!$inventoryItemId) {
    //             Log::error("Inventory Item ID not found for SKU: $sku");
    //             return response()->json(['error' => 'SKU not found in Shopify'], 404);
    //         }

    //         try {

    //         //  Get location ID from inventory_levels
    //         $invLevelResponse = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //             ->get("https://5-core.myshopify.com/admin/api/2025-01/inventory_levels.json", [
    //                 'inventory_item_ids' => $inventoryItemId,
    //             ]);

    //         } catch (\Exception $e) {
    //             Log::error("Incoming store failed for SKU $sku: " . $e->getMessage(), [
    //                 'trace' => $e->getTraceAsString()
    //             ]);
    //             return response()->json(['error' => 'inventory issuse.'], 500);
    //         }

    //         // $invLevelResponse = Http::withHeaders([
    //         //     'X-Shopify-Access-Token' => $accessToken,
    //         // ])->get("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
    //         //     'inventory_item_ids' => $inventoryItemId,
    //         // ]);

    //         if (!$invLevelResponse->successful()) {
    //             Log::error('Failed to fetch inventory levels', $invLevelResponse->json());
    //             return response()->json(['error' => 'Failed to fetch inventory levels'], 500);
    //         }

    //         $levels = $invLevelResponse->json('inventory_levels');
    //         $locationId = $levels[0]['location_id'] ?? null;

    //         if (!$locationId) {
    //             Log::error("Location ID not found for inventory item: $inventoryItemId");
    //             return response()->json(['error' => 'Location ID not found'], 404);
    //         }

    //         try {

    //             // Send adjustment to Shopify (increase available by qty)
    //             $adjustResponse = Http::withBasicAuth(config('services.shopify.api_key'), config('services.shopify.password'))
    //                 ->post("https://5-core.myshopify.com/admin/api/2025-01/inventory_levels/adjust.json", [
    //                     'inventory_item_id' => $inventoryItemId,
    //                     'location_id' => $locationId,   
    //                     'available_adjustment' => $incomingQty,
    //                 ]);

    //         } catch (\Exception $e) {
    //         Log::error("Incoming store failed for SKU $sku: " . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return response()->json(['error' => 'inventory issuse.'], 500);
    //         }

    //         // $adjustResponse = Http::withHeaders([
    //         //     'X-Shopify-Access-Token' => $accessToken,
    //         // ])->post("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
    //         //     'inventory_item_id' => $inventoryItemId,
    //         //     'location_id' => $locationId,
    //         //     'available_adjustment' => $incomingQty,
    //         // ]);

    //         if (!$adjustResponse->successful()) {
    //             Log::error("Failed to update Shopify for SKU $sku", $adjustResponse->json());
    //             return response()->json(['error' => 'Failed to update Shopify inventory'], 500);
    //         }

    //         //  Store in database
    //         Inventory::create([
    //             'sku' => $sku,
    //             'verified_stock' => $incomingQty,
    //             'to_adjust' => $incomingQty,
    //             'reason' => $request->reason,
    //             'is_approved' => true,
    //             'approved_by' => Auth::user()->name ?? 'N/A',
    //             'approved_at' => Carbon::now('America/New_York'),
    //             'type' => 'incoming',
    //             'warehouse_id' => $request->warehouse_id,
    //         ]);

    //         return response()->json(['message' => 'Incoming inventory stored and updated in Shopify successfully']);

    //     } catch (\Exception $e) {
    //         Log::error("Incoming store failed for SKU $sku: " . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return response()->json(['error' => 'Something went wrong.'], 500);
    //     }
    // }


    public function store(Request $request)
    {
        return $this->storeWithType($request, 'incoming', 'incoming');
    }

    public function storeReturn(Request $request)
    {
        return $this->storeWithType($request, 'incoming_return', 'incoming-return');
    }

    /**
     * Shared Shopify + DB flow for incoming and incoming-return pages.
     */
    protected function storeWithType(Request $request, string $inventoryType, string $imageFolderPrefix)
    {
        // Set execution time limit to 90 seconds to handle slow API responses
        set_time_limit(90);
        ini_set('max_execution_time', 90);

        $storedImagePaths = [];
        $storedVoicePath = null;

        try {
            // Validate input (date is always server-side now; images optional)
            $validated = $request->validate([
                'sku' => 'required|string|max:255',
                'qty' => 'required|integer|min:1',
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                // Includes optional client-side speech-to-text for incoming return Condition/Remarks.
                'reason' => 'required|string|max:10000',
                'returns' => 'nullable|string|max:255',
                'images' => 'nullable|array|max:20',
                'images.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
                'voice_note' => 'nullable|file|max:15360|mimetypes:audio/webm,video/webm,audio/ogg,audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav',
                'pallet' => 'nullable|string|max:255',
                'order_id' => 'nullable|string|max:255',
                'return_channel' => 'nullable|string|max:255',
            ]);

            if ($inventoryType === 'incoming_return') {
                $whName = Warehouse::where('id', $validated['warehouse_id'])->value('name');
                if ($this->isWarehouseExcludedForIncomingReturn($whName)) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => ['This warehouse is not available for incoming returns.'],
                    ]);
                }
            }

            $sku = trim($validated['sku']);
            $qty = (int) $validated['qty'];

            $returnsForRow = 'returns';
            if ($inventoryType === 'incoming_return' && Schema::hasColumn('inventories', 'returns')) {
                $r = isset($validated['returns']) ? trim((string) $validated['returns']) : '';
                $returnsForRow = $r !== '' ? $r : 'returns';
            }

            $warehouseName = trim((string) Warehouse::where('id', $validated['warehouse_id'])->value('name'));
            $pushToShopify = $this->isMainGodownWarehouse($warehouseName);

            Log::info("Incoming request received", [
                'sku' => $sku,
                'qty' => $qty,
                'warehouse_id' => $validated['warehouse_id'],
                'warehouse_name' => $warehouseName,
                'push_to_shopify' => $pushToShopify,
                'user' => Auth::user()->name ?? 'Unknown'
            ]);

            if ($pushToShopify) {
            // Shopify credentials
            $shopifyDomain = config('services.shopify.store_url');
            $accessToken = config('services.shopify.access_token');

            if (!$accessToken || !$shopifyDomain) {
                Log::error("Missing Shopify credentials");
                return response()->json(['error' => 'Configuration error', 'details' => 'Shopify credentials not configured'], 500);
            }

            /** -----------------------------------------------------------------
             * Find the Shopify Inventory Item ID
             * Strategy:
             * 1) Fast-path: check local `shopify_skus` table for known variant_id
             * 2) Quick variants endpoint lookup (sku query)
             * 3) Fallback: paginated products.json search
             * ----------------------------------------------------------------- */
            $inventoryItemId = null;
            $pageInfo = null;
            $maxPages = 20;
            $pageCount = 0;

            // Fast-path: check local `shopify_skus` table for a known variant_id
            try {
                $shopifyRow = ShopifySku::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->first();
                if ($shopifyRow && !empty($shopifyRow->variant_id)) {
                    Log::info('Incoming: trying fast-path variant lookup', ['sku' => $sku, 'variant_id' => $shopifyRow->variant_id]);
                    try {
                        $variantResp = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                            ->timeout(30)
                            ->retry(5, 2000, function ($exception, $request) {
                                return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                                       ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                                       $exception->getCode() === 429;
                            })
                            ->get("https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$shopifyRow->variant_id}.json");

                        if ($variantResp->successful()) {
                            $inventoryItemId = $variantResp->json('variant.inventory_item_id') ?? null;
                            Log::info('Incoming: Found inventory_item_id from variant fast-path', ['sku' => $sku, 'inventory_item_id' => $inventoryItemId]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Incoming: fast-path variant fetch failed, will fall back to normal lookup', ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Incoming: error checking shopify_skus fast-path', ['error' => $e->getMessage()]);
            }

            // Quick attempt: query /variants.json?sku=... which is faster than full pagination
            // This is the PRIMARY method - should work if SKU exists
            if (!$inventoryItemId) {
                $variantRetries = 0;
                $maxVariantRetries = 5;
                
                while ($variantRetries < $maxVariantRetries && !$inventoryItemId) {
                    $variantRetries++;
                    try {
                        Log::info("Incoming: Attempting variants endpoint lookup (attempt {$variantRetries}/{$maxVariantRetries})", ['sku' => $sku]);
                        
                        $vResp = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                            ->timeout(30)
                            ->retry(3, 2000, function ($exception, $request) {
                                return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                                       ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                                       $exception->getCode() === 429;
                            })
                            ->get("https://{$this->shopifyDomain}/admin/api/2025-01/variants.json", [
                                'sku' => $sku,
                            ]);

                        if ($vResp->successful()) {
                            $variants = $vResp->json('variants') ?? [];
                            if (!empty($variants) && !empty($variants[0]['inventory_item_id'])) {
                                $inventoryItemId = $variants[0]['inventory_item_id'];
                                Log::info('Incoming: Found inventory_item_id via variants endpoint', ['sku' => $sku, 'inventory_item_id' => $inventoryItemId, 'attempt' => $variantRetries]);
                                break;
                            } else {
                                Log::warning('Incoming: Variants endpoint returned empty results', ['sku' => $sku, 'attempt' => $variantRetries]);
                            }
                        } else {
                            Log::warning('Incoming: Variants endpoint request failed', ['sku' => $sku, 'status' => $vResp->status(), 'attempt' => $variantRetries]);
                            if ($vResp->status() === 429) {
                                // Rate limited - wait longer
                                sleep(5);
                            } elseif ($vResp->status() >= 500) {
                                // Server error - retry
                                sleep(2);
                            } else {
                                // Client error - don't retry
                                break;
                            }
                        }
                    } catch (\Illuminate\Http\Client\ConnectionException $e) {
                        Log::warning('Incoming: Connection timeout on variants endpoint', ['sku' => $sku, 'attempt' => $variantRetries, 'error' => $e->getMessage()]);
                        if ($variantRetries < $maxVariantRetries) {
                            sleep(3);
                            continue;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Incoming: variants endpoint quick lookup failed', ['error' => $e->getMessage(), 'attempt' => $variantRetries]);
                        if ($variantRetries < $maxVariantRetries) {
                            sleep(2);
                            continue;
                        }
                    }
                }
            }

            // Fallback: paginated products.json search (only if variants endpoint didn't work)
            if (!$inventoryItemId) {
                Log::info("Incoming: Falling back to paginated products search", ['sku' => $sku]);
                
                do {
                    $pageCount++;
                    if ($pageCount > $maxPages) {
                        Log::warning("Incoming: Max pages reached in pagination", ['sku' => $sku, 'max_pages' => $maxPages]);
                        break;
                    }

                    $url = "https://{$shopifyDomain}/admin/api/2025-01/products.json?limit=250";
                    if ($pageInfo) {
                        $url .= "&page_info={$pageInfo}";
                    }

                    $pageRetries = 0;
                    $maxPageRetries = 3;
                    
                    while ($pageRetries < $maxPageRetries) {
                        $pageRetries++;
                        try {
                            $response = Http::withHeaders([
                                'X-Shopify-Access-Token' => $accessToken,
                            ])
                            ->timeout(30)
                            ->retry(2, 2000, function ($exception, $request) {
                                return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                                       ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                                       $exception->getCode() === 429;
                            })
                            ->get($url);

                            if ($response->successful()) {
                                $products = $response->json('products') ?? [];

                                foreach ($products as $product) {
                                    foreach ($product['variants'] as $variant) {
                                        if (trim(strtolower($variant['sku'] ?? '')) === strtolower($sku)) {
                                            $inventoryItemId = $variant['inventory_item_id'];
                                            Log::info("Found inventory item for SKU via pagination", ['sku' => $sku, 'inventory_item_id' => $inventoryItemId, 'page' => $pageCount]);
                                            break 3; // Break out of all loops
                                        }
                                    }
                                }

                                // Handle pagination
                                $linkHeader = $response->header('Link');
                                $pageInfo = null;
                                if ($linkHeader && preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
                                    $parsedUrl = parse_url($matches[1]);
                                    parse_str($parsedUrl['query'] ?? '', $query);
                                    $pageInfo = $query['page_info'] ?? null;
                                }
                                break; // Success, exit retry loop
                            } else {
                                if ($response->status() === 429) {
                                    Log::warning("Rate limited on products page", ['status' => $response->status(), 'page_count' => $pageCount, 'retry' => $pageRetries]);
                                    if ($pageRetries < $maxPageRetries) {
                                        sleep(5);
                                        continue;
                                    }
                                } elseif ($response->status() >= 500) {
                                    Log::warning("Server error on products page", ['status' => $response->status(), 'page_count' => $pageCount, 'retry' => $pageRetries]);
                                    if ($pageRetries < $maxPageRetries) {
                                        sleep(2);
                                        continue;
                                    }
                                }
                                $pageInfo = null;
                                break;
                            }
                        } catch (\Illuminate\Http\Client\ConnectionException $e) {
                            Log::warning("Connection timeout fetching Shopify products page", ['page_count' => $pageCount, 'retry' => $pageRetries, 'error' => $e->getMessage()]);
                            if ($pageRetries < $maxPageRetries) {
                                sleep(3);
                                continue;
                            }
                            $pageInfo = null;
                            break;
                        } catch (\Exception $e) {
                            Log::warning("Exception fetching Shopify products: " . $e->getMessage(), ['page_count' => $pageCount, 'retry' => $pageRetries]);
                            if ($pageRetries < $maxPageRetries) {
                                sleep(2);
                                continue;
                            }
                            $pageInfo = null;
                            break;
                        }
                    }

                } while (!$inventoryItemId && $pageInfo);
            }

            if (!$inventoryItemId) {
                Log::warning("SKU not found in Shopify: {$sku}");
                return response()->json([
                    'error' => 'SKU not found',
                    'details' => "The SKU '{$sku}' was not found in Shopify. Please check the SKU spelling."
                ], 404);
            }

            /** -----------------------------------------------------------------
             * Find the Shopify Location ID for "Ohio" with retries
             * ----------------------------------------------------------------- */
            $locationId = null;
            $maxRetries = 3;
            $attempt = 0;

            while ($attempt < $maxRetries && !$locationId) {
                $attempt++;
                try {
                    $locationResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $accessToken,
                    ])
                    ->timeout(30)
                    ->retry(3, 2000, function ($exception, $request) {
                        return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                               ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                               $exception->getCode() === 429;
                    })
                    ->get("https://{$shopifyDomain}/admin/api/2025-01/locations.json");

                    if ($locationResponse->successful()) {

                        $locations = $locationResponse->json('locations') ?? [];
                        
                        $ohioLocation = collect($locations)->first(function ($loc) {
                            return stripos($loc['name'] ?? '', 'ohio') !== false;
                        });

                        if ($ohioLocation) {
                            $locationId = $ohioLocation['id'];
                            Log::info("Found Ohio location", ['location_id' => $locationId]);
                        } else {
                            Log::warning("No Ohio location found in Shopify");
                            return response()->json([
                                'error' => 'Location not found',
                                'details' => 'No Shopify location found with name containing "Ohio"'
                            ], 404);
                        }
                    } else {
                        Log::warning("Failed to fetch locations", ['attempt' => $attempt, 'status' => $locationResponse->status()]);
                        if ($attempt < $maxRetries) {
                            if ($locationResponse->status() === 429) {
                                sleep(5);
                            } else {
                                sleep($attempt);
                            }
                            continue;
                        }
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::warning("Connection timeout fetching locations: " . $e->getMessage(), ['attempt' => $attempt]);
                    if ($attempt < $maxRetries) {
                        sleep(3);
                        continue;
                    }
                    throw $e;
                } catch (\Exception $e) {
                    Log::warning("Exception fetching locations: " . $e->getMessage(), ['attempt' => $attempt]);
                    if ($attempt < $maxRetries) {
                        sleep($attempt);
                        continue;
                    }
                    throw $e;
                }
            }

            if (!$locationId) {
                return response()->json([
                    'error' => 'Failed to fetch location',
                    'details' => 'Could not connect to Shopify to get location data'
                ], 503);
            }

            /** -----------------------------------------------------------------
             * Ensure inventory item is connected to the Ohio location (with retries)
             * ----------------------------------------------------------------- */
            $connectAttempt = 0;
            while ($connectAttempt < $maxRetries) {
                $connectAttempt++;
                try {
                    $connectResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ])->timeout(10)->post("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels/connect.json", [
                        'location_id' => $locationId,
                        'inventory_item_id' => $inventoryItemId,
                    ]);

                    // 422 means already connected - that's fine
                    if ($connectResponse->successful() || $connectResponse->status() == 422) {
                        Log::info("Inventory item connected to location", ['status' => $connectResponse->status()]);
                        break;
                    } elseif ($connectResponse->status() >= 500 || $connectResponse->status() == 429) {
                        if ($connectAttempt < $maxRetries) {
                            sleep($connectAttempt);
                            continue;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Exception connecting inventory: " . $e->getMessage(), ['attempt' => $connectAttempt]);
                    if ($connectAttempt < $maxRetries) {
                        sleep($connectAttempt);
                        continue;
                    }
                }
            }

            /** -----------------------------------------------------------------
             * Adjust inventory quantity for the Ohio location (with retries)
             * ----------------------------------------------------------------- */
            $adjustAttempt = 0;
            $adjustResponse = null;

            while ($adjustAttempt < $maxRetries) {
                $adjustAttempt++;
                try {
                    $adjustResponse = Http::withHeaders([
                        'X-Shopify-Access-Token' => $accessToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(30)
                    ->retry(5, 2000, function ($exception, $request) {
                        return $exception instanceof \Illuminate\Http\Client\ConnectionException || 
                               ($exception->getCode() >= 500 && $exception->getCode() < 600) ||
                               $exception->getCode() === 429;
                    })
                    ->post("https://{$shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json", [
                        'location_id' => $locationId,
                        'inventory_item_id' => $inventoryItemId,
                        'available_adjustment' => $qty,
                    ]);

                    if ($adjustResponse->successful()) {
                        Log::info("Successfully adjusted Shopify inventory", ['sku' => $sku, 'qty' => $qty, 'attempt' => $adjustAttempt]);
                        break;
                    } elseif ($adjustResponse->status() >= 500 || $adjustResponse->status() == 429) {
                        Log::warning("Adjust failed, retrying...", ['status' => $adjustResponse->status(), 'attempt' => $adjustAttempt]);
                        if ($adjustAttempt < $maxRetries) {
                            if ($adjustResponse->status() === 429) {
                                sleep(5);
                            } else {
                                sleep($adjustAttempt);
                            }
                            continue;
                        }
                    } else {
                        Log::error("Adjust failed with non-retryable error", ['sku' => $sku, 'status' => $adjustResponse->status(), 'body' => $adjustResponse->body()]);
                        break;
                    }
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    Log::warning("Connection timeout adjusting inventory: " . $e->getMessage(), ['attempt' => $adjustAttempt]);
                    if ($adjustAttempt < $maxRetries) {
                        sleep(3);
                        continue;
                    }
                    throw $e;
                } catch (\Exception $e) {
                    Log::warning("Exception adjusting inventory: " . $e->getMessage(), ['attempt' => $adjustAttempt]);
                    if ($adjustAttempt < $maxRetries) {
                        sleep($adjustAttempt);
                        continue;
                    }
                    throw $e;
                }
            }

            if (!$adjustResponse || !$adjustResponse->successful()) {
                $status = $adjustResponse ? $adjustResponse->status() : 'No response';
                Log::error("Failed to adjust Shopify inventory after retries", ['sku' => $sku, 'status' => $status]);
                return response()->json([
                    'error' => 'Failed to update Shopify inventory',
                    'details' => 'Could not complete the adjustment. Please try again.'
                ], 503);
            }
            } else {
                Log::info('Incoming: skipping Shopify sync (warehouse is not Main Godown)', [
                    'sku' => $sku,
                    'warehouse_id' => $validated['warehouse_id'],
                    'warehouse_name' => $warehouseName,
                    'inventory_type' => $inventoryType,
                ]);
            }

            /** -----------------------------------------------------------------
             * Optional photo uploads (public disk)
             * ----------------------------------------------------------------- */
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    if ($file && $file->isValid()) {
                        $storedImagePaths[] = $file->store($imageFolderPrefix.'/'.date('Y/m'), 'public');
                    }
                }
            }

            if ($request->hasFile('voice_note')) {
                $voiceFile = $request->file('voice_note');
                if ($voiceFile && $voiceFile->isValid()) {
                    $storedVoicePath = $voiceFile->store($imageFolderPrefix.'/voice/'.date('Y/m'), 'public');
                }
            }

            $productMaster = ProductMaster::query()
                ->whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($sku)])
                ->first();

            /** -----------------------------------------------------------------
             * Store locally in database (timestamp + parent from product master)
             * ----------------------------------------------------------------- */
            try {
                DB::beginTransaction();

                $successLabel = $inventoryType === 'incoming_return' ? 'Incoming return' : 'Incoming stock';
                $nowOhio = Carbon::now('America/New_York');
                $approvedBy = Auth::user()->name ?? 'N/A';

                // Incoming return: same SKU + same warehouse → add quantity to one row (merge duplicates).
                if ($inventoryType === 'incoming_return') {
                    $matches = Inventory::query()
                        ->where('type', 'incoming_return')
                        ->where('warehouse_id', $validated['warehouse_id'])
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->get();

                    if ($matches->isNotEmpty()) {
                        $keep = $matches->first();
                        $newVerified = $matches->sum(fn ($i) => (float) ($i->verified_stock ?? 0)) + $qty;
                        $newToAdjust = $matches->sum(fn ($i) => (float) ($i->to_adjust ?? 0)) + $qty;

                        $reasonParts = $matches->pluck('reason')->map(fn ($r) => trim((string) $r))->filter()->unique()->values()->all();
                        $reasonParts[] = trim($validated['reason']);
                        $mergedReason = implode(' | ', array_unique($reasonParts));

                        $allPaths = [];
                        foreach ($matches as $m) {
                            $imgs = $m->incoming_images;
                            if (is_array($imgs)) {
                                foreach ($imgs as $p) {
                                    if (is_string($p) && trim($p) !== '') {
                                        $allPaths[] = trim($p);
                                    }
                                }
                            }
                        }
                        foreach ($storedImagePaths as $p) {
                            if ($p !== '' && $p !== null) {
                                $allPaths[] = $p;
                            }
                        }
                        $allPaths = array_values(array_unique($allPaths));

                        $update = [
                            'verified_stock' => $newVerified,
                            'to_adjust' => $newToAdjust,
                            'reason' => $mergedReason,
                            'approved_by' => $approvedBy,
                            'approved_at' => $nowOhio,
                            'updated_at' => now(),
                        ];
                        if (Schema::hasColumn('inventories', 'returns')) {
                            $returnsParts = $matches->pluck('returns')->map(fn ($x) => trim((string) $x))->filter()->unique()->values()->all();
                            $returnsParts[] = $returnsForRow;
                            $update['returns'] = implode(' | ', array_unique($returnsParts));
                        }
                        if (Schema::hasColumn('inventories', 'incoming_images')) {
                            $update['incoming_images'] = $allPaths !== [] ? json_encode($allPaths) : null;
                        }

                        if (Schema::hasColumn('inventories', 'incoming_voice_note')) {
                            $mergedVoice = null;
                            if ($storedVoicePath) {
                                $mergedVoice = $storedVoicePath;
                            } else {
                                $mergedVoice = $this->mergeIncomingVoicePathFromItems($matches);
                            }
                            $update['incoming_voice_note'] = $mergedVoice;
                        }

                        if (Schema::hasColumn('inventories', 'restock_fee_usd')) {
                            $newRestockUsd = (float) $matches->sum(fn ($i) => (float) ($i->restock_fee_usd ?? 0));
                            $update['restock_fee_usd'] = (float) round($newRestockUsd, 0);
                        }

                        if ($inventoryType === 'incoming_return' && Schema::hasColumn('inventories', 'pallet')) {
                            $newPallet = trim((string) ($validated['pallet'] ?? ''));
                            if ($newPallet !== '') {
                                $palletParts = $matches->pluck('pallet')->map(fn ($x) => trim((string) $x))->filter()->unique()->values()->all();
                                $palletParts[] = $newPallet;
                                $update['pallet'] = implode(' | ', array_unique($palletParts));
                            }
                        }

                        if ($inventoryType === 'incoming_return' && Schema::hasColumn('inventories', 'order_id')) {
                            $newOrderId = trim((string) ($validated['order_id'] ?? ''));
                            if ($newOrderId !== '') {
                                $orderParts = $matches->pluck('order_id')->map(fn ($x) => trim((string) $x))->filter()->unique()->values()->all();
                                $orderParts[] = $newOrderId;
                                $update['order_id'] = implode(' | ', array_unique($orderParts));
                            }
                        }

                        DB::table('inventories')->where('id', $keep->id)->update($update);

                        $otherIds = $matches->pluck('id')->skip(1)->values()->all();
                        if ($otherIds !== []) {
                            DB::table('inventories')->whereIn('id', $otherIds)->delete();
                        }

                        if ($inventoryType === 'incoming_return' && Schema::hasTable('incoming_return_channels')) {
                            $this->syncIncomingReturnChannelRow((int) $keep->id, $validated['return_channel'] ?? null);
                        }

                        DB::commit();

                        Log::info('Incoming return merged into existing row', [
                            'sku' => $sku,
                            'warehouse_id' => $validated['warehouse_id'],
                            'added_qty' => $qty,
                            'total_qty' => $newVerified,
                            'consolidated_rows' => $matches->count(),
                        ]);

                        $message = $pushToShopify
                            ? "✓ {$successLabel} for {$sku}: +{$qty} units (total {$newVerified})."
                            : "✓ {$successLabel} for {$sku}: +{$qty} units (total {$newVerified}). Saved locally — only Main Godown syncs to Shopify.";

                        return response()->json([
                            'success' => true,
                            'message' => $message,
                            'new_stock_level' => $newVerified,
                            'merged' => true,
                            'parent' => $productMaster->parent ?? null,
                            'recorded_at' => $nowOhio->toIso8601String(),
                            'shopify_synced' => $pushToShopify,
                        ], 200);
                    }
                }

                $incomingImagesJson = count($storedImagePaths) > 0
                    ? json_encode(array_values($storedImagePaths))
                    : null;

                $insert = [
                    'sku' => $sku,
                    'verified_stock' => $qty,
                    'to_adjust' => $qty,
                    'reason' => $validated['reason'],
                    'is_approved' => true,
                    'approved_by' => $approvedBy,
                    'approved_at' => $nowOhio,
                    'type' => $inventoryType,
                    'warehouse_id' => $validated['warehouse_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('inventories', 'incoming_images')) {
                    $insert['incoming_images'] = $incomingImagesJson;
                }

                if (Schema::hasColumn('inventories', 'incoming_voice_note')) {
                    $insert['incoming_voice_note'] = $storedVoicePath;
                }

                if ($inventoryType === 'incoming_return' && Schema::hasColumn('inventories', 'returns')) {
                    $insert['returns'] = $returnsForRow;
                }

                if ($inventoryType === 'incoming_return' && Schema::hasColumn('inventories', 'pallet')) {
                    $palletVal = trim((string) ($validated['pallet'] ?? ''));
                    if ($palletVal !== '') {
                        $insert['pallet'] = $palletVal;
                    }
                }

                if ($inventoryType === 'incoming_return' && Schema::hasColumn('inventories', 'order_id')) {
                    $orderIdVal = trim((string) ($validated['order_id'] ?? ''));
                    if ($orderIdVal !== '') {
                        $insert['order_id'] = $orderIdVal;
                    }
                }

                $newInventoryId = (int) DB::table('inventories')->insertGetId($insert);

                if ($inventoryType === 'incoming_return' && Schema::hasTable('incoming_return_channels')) {
                    $this->syncIncomingReturnChannelRow($newInventoryId, $validated['return_channel'] ?? null);
                }

                DB::commit();

                Log::info('Incoming inventory stored successfully', ['sku' => $sku, 'qty' => $qty, 'type' => $inventoryType]);

                $message = $pushToShopify
                    ? "✓ {$successLabel} for {$sku} added successfully! Quantity: {$qty} units."
                    : "✓ {$successLabel} for {$sku} saved locally ({$qty} units). Shopify was not updated — only Main Godown syncs to Shopify.";

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'new_stock_level' => $qty,
                    'merged' => false,
                    'parent' => $productMaster->parent ?? null,
                    'recorded_at' => $nowOhio->toIso8601String(),
                    'shopify_synced' => $pushToShopify,
                ], 200);

            } catch (\Exception $dbException) {
                DB::rollBack();
                foreach ($storedImagePaths as $path) {
                    Storage::disk('public')->delete($path);
                }
                if ($storedVoicePath) {
                    Storage::disk('public')->delete($storedVoicePath);
                }
                Log::error('Failed to save incoming record to database', ['sku' => $sku, 'error' => $dbException->getMessage(), 'shopify_was_attempted' => $pushToShopify]);

                return response()->json([
                    'error' => 'Database Error',
                    'details' => $pushToShopify
                        ? 'Shopify was updated but database record could not be created. Please contact support.'
                        : 'Record could not be saved. Please try again.',
                    'shopify_updated' => $pushToShopify,
                ], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation Error',
                'details' => $e->errors()
            ], 422);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Connection timeout: {$e->getMessage()}", [
                'sku' => $sku ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Connection Timeout',
                'details' => 'The request to Shopify took too long. The SKU may exist but the API is slow. Please try again in a moment.'
            ], 504);
        } catch (\Exception $e) {
            Log::error("Incoming store failed: " . $e->getMessage(), [
                'sku' => $sku ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'An unexpected error occurred',
                'details' => 'Please try again or contact support if the problem persists. Error: ' . $e->getMessage()
            ], 500);
        }
    }




    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function list()
    {
        $items = Inventory::with('warehouse')
            ->where('type', 'incoming')
            ->latest()
            ->get();

        [$pmByLower, $shopifyByLower] = $this->batchProductMasterAndShopifyBySkuKeys($items->pluck('sku'));

        $data = $items->map(function ($item) use ($pmByLower, $shopifyByLower) {
            $k = strtolower(trim((string) ($item->sku ?? '')));
            $pm = $pmByLower->get($k);
            $shop = $shopifyByLower->get($k);

            return [
                'sku' => $item->sku,
                'verified_stock' => $item->verified_stock,
                'reason' => $item->reason,
                'warehouse_id' => $item->warehouse_id,
                'warehouse_name' => $item->warehouse->name ?? '',
                'approved_by' => $item->approved_by,
                'approved_at' => $item->approved_at
                    ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('m-d-Y')
                    : '',
                'image_url' => $this->resolveInventoryRowImageUrl($item, $pm, $shop),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Single feed for the Incoming Return page: merges general incoming stock rows with grouped return history.
     *
     * Data sources (same DB table, different filters):
     * - General incoming: {@see Inventory} where type = 'incoming' (same as {@see list()}).
     * - Returns: {@see Inventory} where type = 'incoming_return', grouped by SKU + warehouse (same as {@see listReturnHistory()}).
     */
    public function listIncomingReturnViewMerged()
    {
        $hasFinancialCols = Schema::hasColumn('inventories', 'restock_fee_usd');
        $hasReturnsCol = Schema::hasColumn('inventories', 'returns');

        $incomingItems = Inventory::with('warehouse')
            ->where('type', 'incoming')
            ->orderByDesc('id')
            ->get();

        $returnItems = Inventory::with('warehouse')
            ->where('type', 'incoming_return')
            ->latest()
            ->get();

        $returnChannelByInventoryId = collect();
        if (Schema::hasTable('incoming_return_channels') && $returnItems->isNotEmpty()) {
            $returnChannelByInventoryId = IncomingReturnChannel::query()
                ->whereIn('inventory_id', $returnItems->pluck('id')->unique()->all())
                ->pluck('channel', 'inventory_id');
        }

        $allSkus = $incomingItems->pluck('sku')->merge($returnItems->pluck('sku'))->filter(fn ($s) => trim((string) $s) !== '');
        $amazonByUpper = $this->batchAmazonSellPriceRowsByUpperSku($allSkus);

        [$pmIn, $shopIn] = $this->batchProductMasterAndShopifyBySkuKeys($incomingItems->pluck('sku'));

        $hasOrderIdCol = Schema::hasColumn('inventories', 'order_id');

        $incomingRows = $incomingItems->map(function ($item) use ($pmIn, $shopIn, $hasFinancialCols, $amazonByUpper, $hasReturnsCol, $hasOrderIdCol) {
            $k = strtolower(trim((string) ($item->sku ?? '')));
            $pm = $pmIn->get($k);
            $shop = $shopIn->get($k);
            $at = $item->approved_at ?? $item->updated_at;
            $voicePath = Schema::hasColumn('inventories', 'incoming_voice_note')
                ? trim((string) ($item->incoming_voice_note ?? ''))
                : '';

            $skuU = strtoupper(trim((string) ($item->sku ?? '')));
            $amz = $amazonByUpper->get($skuU);
            $unit = $amz && $amz->price !== null && $amz->price !== ''
                ? (float) $amz->price
                : null;
            $qty = (float) ($item->verified_stock ?? 0);
            $restock = $hasFinancialCols ? $item->restock_fee_usd : null;
            $usd = $this->packIncomingReturnFinancialsFromAmazon($unit, $qty, $restock, $hasFinancialCols);

            return array_merge([
                'sku' => $item->sku,
                'verified_stock' => $item->verified_stock,
                'reason' => $item->reason,
                'return_channel' => null,
                'order_id' => $hasOrderIdCol ? ($item->order_id ?? null) : null,
                'returns' => $hasReturnsCol ? ($item->returns ?? null) : null,
                'warehouse_id' => $item->warehouse_id,
                'warehouse_name' => $item->warehouse->name ?? '',
                'approved_by' => $item->approved_by,
                'approved_at' => $item->approved_at
                    ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('m-d-Y')
                    : '',
                'image_url' => $this->resolveInventoryRowImageUrl($item, $pm, $shop),
                'user_upload_image_urls' => $this->resolveIncomingSavedPhotoUrlsFromArray($item->incoming_images),
                'voice_note_url' => $voicePath !== '' ? $this->normalizePublicDiskImageUrl($voicePath) : null,
                'record_type' => 'incoming',
                'record_type_label' => 'General incoming',
                'inventory_id' => $item->id,
                '_sort' => $at ? Carbon::parse($at)->timestamp : 0,
            ], $usd);
        });

        [$pmRet, $shopRet] = $this->batchProductMasterAndShopifyBySkuKeys($returnItems->pluck('sku'));

        $grouped = $returnItems->groupBy(function ($item) {
            $skuKey = strtoupper(trim((string) ($item->sku ?? '')));

            return $skuKey.'|'.(int) ($item->warehouse_id ?? 0);
        });

        $sortedGroups = $grouped->sortByDesc(fn ($group) => $group->max('id'));

        $returnRows = $sortedGroups->values()->map(function ($group) use ($pmRet, $shopRet, $hasFinancialCols, $amazonByUpper, $hasReturnsCol, $returnChannelByInventoryId, $hasOrderIdCol) {
            $rep = $group->sortByDesc('id')->values()->first();
            $sumQty = $group->sum(fn ($i) => (float) ($i->verified_stock ?? 0));
            $k = strtolower(trim((string) ($rep->sku ?? '')));
            $pm = $pmRet->get($k);
            $shop = $shopRet->get($k);
            $reasonParts = $group->pluck('reason')->map(fn ($r) => trim((string) $r))->filter()->unique()->values()->all();
            $returnsMerged = $hasReturnsCol
                ? implode(' | ', $group->pluck('returns')->map(fn ($x) => trim((string) $x))->filter()->unique()->values()->all())
                : null;
            $at = $rep->approved_at ?? $rep->updated_at;
            $mergedVoicePath = Schema::hasColumn('inventories', 'incoming_voice_note')
                ? $this->mergeIncomingVoicePathFromItems($group)
                : null;

            $skuU = strtoupper(trim((string) ($rep->sku ?? '')));
            $amz = $amazonByUpper->get($skuU);
            $unit = $amz && $amz->price !== null && $amz->price !== ''
                ? (float) $amz->price
                : null;
            $restockSum = $hasFinancialCols
                ? (float) round((float) $group->sum(fn ($i) => (float) ($i->restock_fee_usd ?? 0)), 0)
                : null;
            $usd = $this->packIncomingReturnFinancialsFromAmazon($unit, $sumQty, $restockSum, $hasFinancialCols);

            $channelLabels = $group->pluck('id')
                ->map(fn ($rid) => $returnChannelByInventoryId->get($rid))
                ->map(fn ($c) => trim((string) $c))
                ->filter()
                ->unique()
                ->values();
            $channelDisplay = $channelLabels->isNotEmpty() ? $channelLabels->implode(' | ') : null;

            $orderIdDisplay = null;
            if ($hasOrderIdCol) {
                $orderIdParts = $group->pluck('order_id')->map(fn ($x) => trim((string) $x))->filter()->unique()->values()->all();
                $orderIdDisplay = $orderIdParts !== [] ? implode(' | ', $orderIdParts) : null;
            }

            return array_merge([
                'sku' => $rep->sku,
                'verified_stock' => $sumQty,
                'reason' => implode(' | ', $reasonParts),
                'return_channel' => $channelDisplay,
                'order_id' => $orderIdDisplay,
                'returns' => $returnsMerged !== '' ? $returnsMerged : null,
                'warehouse_id' => $rep->warehouse_id,
                'warehouse_name' => $rep->warehouse->name ?? '',
                'approved_by' => $rep->approved_by,
                'approved_at' => $rep->approved_at
                    ? Carbon::parse($rep->approved_at)->timezone('America/New_York')->format('m-d-Y')
                    : '',
                'image_url' => $this->resolveInventoryRowImageUrl($rep, $pm, $shop),
                'user_upload_image_urls' => $this->mergeIncomingSavedPhotoUrlsFromItems($group),
                'voice_note_url' => $mergedVoicePath ? $this->normalizePublicDiskImageUrl($mergedVoicePath) : null,
                'record_type' => 'incoming_return',
                'record_type_label' => 'Return',
                'inventory_id' => $rep->id,
                '_sort' => $at ? Carbon::parse($at)->timestamp : 0,
            ], $usd);
        });

        $merged = $incomingRows->concat($returnRows)
            ->sortByDesc('_sort')
            ->values()
            ->map(function (array $row) {
                $row['financial_at_ts'] = (int) ($row['_sort'] ?? 0);
                unset($row['_sort']);

                return $row;
            })
            ->values();

        $sumTz = 'America/Los_Angeles';
        $nowLa = Carbon::now($sumTz);
        $sumWindowStart = $nowLa->copy()->subDays(29)->startOfDay();
        $sumWindowEnd = $nowLa->copy()->endOfDay();

        return response()->json([
            'data' => $merged->all(),
            'financial_sum_window' => [
                'timezone' => $sumTz,
                'start_ts' => $sumWindowStart->timestamp,
                'end_ts' => $sumWindowEnd->timestamp,
            ],
        ]);
    }

    /**
     * Amazon sell price per unit from {@see AmazonDatasheet} (`amazon_datsheets.price`), keyed by UPPER(TRIM(sku)).
     *
     * @return \Illuminate\Support\Collection<string, AmazonDatasheet>
     */
    protected function batchAmazonSellPriceRowsByUpperSku(Collection $skus): Collection
    {
        $norm = $skus->map(fn ($s) => strtoupper(trim((string) $s)))->filter()->unique()->values();
        if ($norm->isEmpty()) {
            return collect();
        }

        $rows = AmazonDatasheet::query()
            ->whereIn(DB::raw('UPPER(TRIM(sku))'), $norm->all())
            ->get(['sku', 'price']);

        return $rows->keyBy(fn ($r) => strtoupper(trim((string) $r->sku)));
    }

    /**
     * Loss $ = Amazon unit price × quantity when price exists; restock from {@see Inventory::$restock_fee_usd}; net = loss − restock.
     *
     * @return array{loss_usd: float|null, restock_fee_usd: float|null, net_loss_usd: float, amazon_unit_price: float|null}
     */
    protected function packIncomingReturnFinancialsFromAmazon(?float $amazonUnitPrice, float $qty, $restockFee, bool $hasCols): array
    {
        if (! $hasCols) {
            return [
                'loss_usd' => null,
                'restock_fee_usd' => null,
                'net_loss_usd' => null,
                'amazon_unit_price' => null,
            ];
        }

        $loss = null;
        if ($amazonUnitPrice !== null) {
            $loss = (float) round((float) $amazonUnitPrice * $qty, 0);
        }

        $r = $restockFee === null || $restockFee === '' ? null : (float) round((float) $restockFee, 0);

        $lVal = $loss !== null ? (float) $loss : 0.0;
        $rVal = $r !== null ? (float) $r : 0.0;
        $net = (float) round($lVal - $rVal, 0);

        return [
            'loss_usd' => $loss,
            'restock_fee_usd' => $r,
            'net_loss_usd' => $net,
            'amazon_unit_price' => $amazonUnitPrice !== null ? (float) round((float) $amazonUnitPrice, 0) : null,
        ];
    }

    public function updateIncomingReturnRowRestock(Request $request, Inventory $inventory)
    {
        if (! in_array($inventory->type, ['incoming', 'incoming_return'], true)) {
            abort(404);
        }

        if (! Schema::hasColumn('inventories', 'restock_fee_usd')) {
            return response()->json(['success' => false, 'message' => 'Restock column not available.'], 400);
        }

        if ($request->input('restock_fee_usd') === '') {
            $request->merge(['restock_fee_usd' => null]);
        }

        $validated = $request->validate([
            'restock_fee_usd' => 'nullable|numeric|min:0',
        ]);

        $v = $validated['restock_fee_usd'] ?? null;
        $inventory->restock_fee_usd = $v === null ? null : (float) round((float) $v, 0);
        $inventory->save();

        $skuU = strtoupper(trim((string) ($inventory->sku ?? '')));
        $amazonByUpper = $this->batchAmazonSellPriceRowsByUpperSku(collect([$inventory->sku]));
        $amz = $amazonByUpper->get($skuU);
        $unit = $amz && $amz->price !== null && $amz->price !== ''
            ? (float) $amz->price
            : null;
        $qty = (float) ($inventory->verified_stock ?? 0);
        $payload = $this->packIncomingReturnFinancialsFromAmazon($unit, $qty, $inventory->restock_fee_usd, true);
        $payload['inventory_id'] = (int) $inventory->id;

        return response()->json(['success' => true] + $payload);
    }

    /**
     * @return list<string> lowercased names excluded from incoming return pickers
     */
    protected function incomingReturnExcludedWarehouseNamesLower(): array
    {
        return ['returns godown', 'used item godown'];
    }

    protected function isWarehouseExcludedForIncomingReturn(?string $name): bool
    {
        $n = strtolower(trim((string) $name));
        return $n !== '' && in_array($n, $this->incomingReturnExcludedWarehouseNamesLower(), true);
    }

    /**
     * Only Main Godown (or label "Main") should sync quantity to Shopify for incoming / incoming return.
     */
    protected function isMainGodownWarehouse(?string $name): bool
    {
        $n = strtolower(trim((string) $name));
        if ($n === '') {
            return false;
        }
        $compact = str_replace(' ', '', $n);

        return $n === 'main' || $n === 'main godown' || $compact === 'maingodown';
    }

    /**
     * @return list<int>
     */
    protected function warehouseIdsForIncomingReturnGridScope(string $scope): array
    {
        $q = Warehouse::query();
        if ($scope === 'open_box') {
            $q->where(function ($w) {
                $w->whereRaw('LOWER(TRIM(name)) = ?', ['open box'])
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', ['open box godown'])
                    ->orWhereRaw("REPLACE(LOWER(TRIM(name)), ' ', '') = ?", ['openbox']);
            });
        } elseif ($scope === 'trash') {
            $q->where(function ($w) {
                $w->whereRaw('LOWER(TRIM(name)) = ?', ['trash'])
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', ['trash godown'])
                    ->orWhereRaw("REPLACE(LOWER(TRIM(name)), ' ', '') = ?", ['trashgodown']);
            });
        } else {
            return [];
        }

        return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Theme key for warehouse dot/color in grids (matches incoming-return-view.js warehouseThemeKeyFromName).
     */
    protected function warehouseThemeKeyFromNameForGrid(?string $name): string
    {
        $n = strtolower(trim((string) $name));
        if ($n === '') {
            return '';
        }
        $c = str_replace(' ', '', $n);
        if ($n === 'open box' || $n === 'open box godown' || $c === 'openbox') {
            return 'openbox';
        }
        if ($n === 'trash' || $n === 'trash godown' || $c === 'trashgodown') {
            return 'trash';
        }
        if ($n === 'main' || $n === 'main godown' || $c === 'maingodown') {
            return 'main';
        }

        return '';
    }

    /**
     * Latest incoming_return row per SKU for Trash or Open Box godown — same JSON shape as VerificationAdjustmentController::getVerifiedStock plus IMAGE_URL for View Inventory merge.
     */
    public function getIncomingReturnGridInventory(Request $request)
    {
        $validated = $request->validate([
            'warehouse_scope' => 'required|in:trash,open_box',
        ]);

        $warehouseIds = $this->warehouseIdsForIncomingReturnGridScope($validated['warehouse_scope']);
        if ($warehouseIds === []) {
            return response()->json(['data' => []]);
        }

        $rows = Inventory::query()
            ->where('type', 'incoming_return')
            ->whereIn('warehouse_id', $warehouseIds)
            ->with('warehouse')
            ->orderByDesc('id')
            ->get();

        $aggBySku = [];
        foreach ($rows as $item) {
            $k = strtoupper(trim((string) ($item->sku ?? '')));
            if ($k === '') {
                continue;
            }
            if (! isset($aggBySku[$k])) {
                $aggBySku[$k] = ['qty' => 0.0, 'to_adjust_sum' => 0.0, 'latest' => null, 'maxId' => -1];
            }
            $aggBySku[$k]['qty'] += (float) ($item->verified_stock ?? 0);
            $aggBySku[$k]['to_adjust_sum'] += (float) ($item->to_adjust ?? 0);
            if ((int) $item->id > $aggBySku[$k]['maxId']) {
                $aggBySku[$k]['maxId'] = (int) $item->id;
                $aggBySku[$k]['latest'] = $item;
            }
        }

        if ($aggBySku === []) {
            return response()->json(['data' => []]);
        }

        $skuCollection = collect(array_keys($aggBySku))->map(fn ($s) => $s);
        [$pmByLower, $shopifyByLower] = $this->batchProductMasterAndShopifyBySkuKeys($skuCollection);

        $data = [];
        foreach ($aggBySku as $skuUpper => $bundle) {
            $item = $bundle['latest'];
            $totalQty = $bundle['qty'];
            $totalToAdjust = $bundle['to_adjust_sum'];
            $k = strtolower(trim((string) ($item->sku ?? '')));
            $pm = $pmByLower->get($k);
            $shop = $shopifyByLower->get($k);
            $img = $this->resolveInventoryRowImageUrl($item, $pm, $shop);
            $whName = (string) ($item->warehouse?->name ?? '');

            $data[] = [
                'sku' => $skuUpper,
                'R&A' => (bool) $item->is_ra_checked,
                'verified_stock' => $totalQty,
                'quantity' => $totalQty,
                'warehouse_name' => $whName,
                'warehouse_theme' => $this->warehouseThemeKeyFromNameForGrid($whName),
                'to_adjust' => $totalToAdjust,
                'reason' => $item->reason,
                'is_approved' => (bool) $item->is_approved,
                'approved_by_ih' => (bool) $item->approved_by_ih,
                'approved_by' => $item->approved_by,
                'approved_at' => $item->approved_at
                    ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('Y-m-d H:i:s')
                    : '',
                'IMAGE_URL' => $img ? (string) $img : '',
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function viewInventoryIncomingReturnTrash()
    {
        return view('inventory-management.view-inventory-incoming-return', [
            'incomingReturnGridScope' => 'trash',
            'viewTitleSuffix' => ' — Trash Godown',
            'incomingReturnGridLabel' => 'Trash Godown',
            'incomingReturnGridDotColor' => '#9b1c1c',
        ]);
    }

    public function viewInventoryIncomingReturnOpenBox()
    {
        return view('inventory-management.view-inventory-incoming-return', [
            'incomingReturnGridScope' => 'open_box',
            'viewTitleSuffix' => ' — Open Box Godown',
            'incomingReturnGridLabel' => 'Open Box Godown',
            'incomingReturnGridDotColor' => '#b8860b',
        ]);
    }

    public function incomingReturnIndex()
    {
        $excluded = $this->incomingReturnExcludedWarehouseNamesLower();
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));

        $warehouses = Warehouse::select('id', 'name')
            ->whereRaw("LOWER(TRIM(name)) NOT IN ({$placeholders})", $excluded)
            ->orderBy('name')
            ->get();

        $channels = ChannelMaster::where('status', 'Active')
            ->orderBy('channel')
            ->pluck('channel')
            ->values()
            ->all();

        // incoming-return-view: Condition/Remarks can be filled with the browser speech-to-text control; still posted as `reason`.
        return view('inventory-management.incoming-return-view', compact('warehouses', 'channels'));
    }

    /**
     * Store channel for an incoming return on a separate table (not on inventories).
     */
    protected function syncIncomingReturnChannelRow(int $inventoryId, mixed $channel): void
    {
        if (! Schema::hasTable('incoming_return_channels')) {
            return;
        }
        $ch = trim((string) ($channel ?? ''));
        if ($ch === '') {
            IncomingReturnChannel::where('inventory_id', $inventoryId)->delete();

            return;
        }
        IncomingReturnChannel::updateOrCreate(
            ['inventory_id' => $inventoryId],
            ['channel' => $ch]
        );
    }

    public function listReturnHistory()
    {
        $items = Inventory::with('warehouse')
            ->where('type', 'incoming_return')
            ->latest()
            ->get();

        [$pmByLower, $shopifyByLower] = $this->batchProductMasterAndShopifyBySkuKeys($items->pluck('sku'));

        $grouped = $items->groupBy(function ($item) {
            $skuKey = strtoupper(trim((string) ($item->sku ?? '')));

            return $skuKey.'|'.(int) ($item->warehouse_id ?? 0);
        });

        $sorted = $grouped->sortByDesc(fn ($group) => $group->max('id'));

        $data = $sorted->values()->map(function ($group) use ($pmByLower, $shopifyByLower) {
            $rep = $group->sortByDesc('id')->values()->first();
            $sumQty = $group->sum(fn ($i) => (float) ($i->verified_stock ?? 0));
            $k = strtolower(trim((string) ($rep->sku ?? '')));
            $pm = $pmByLower->get($k);
            $shop = $shopifyByLower->get($k);

            $reasonParts = $group->pluck('reason')->map(fn ($r) => trim((string) $r))->filter()->unique()->values()->all();

            return [
                'sku' => $rep->sku,
                'verified_stock' => $sumQty,
                'reason' => implode(' | ', $reasonParts),
                'warehouse_id' => $rep->warehouse_id,
                'warehouse_name' => $rep->warehouse->name ?? '',
                'approved_by' => $rep->approved_by,
                'approved_at' => $rep->approved_at
                    ? Carbon::parse($rep->approved_at)->timezone('America/New_York')->format('m-d-Y')
                    : '',
                'image_url' => $this->resolveInventoryRowImageUrl($rep, $pm, $shop),
            ];
        });

        return response()->json(['data' => $data]);
    }



    public function incomingOrderIndex()
    {
        $warehouses = Warehouse::select('id', 'name')->get();
        $skus = ProductMaster::select('id','parent','sku')->get();
        $reasons = IncomingReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray();

        return view('inventory-management.incoming-orders-view', compact('warehouses', 'skus', 'reasons'));
    }

    public function getIncomingReasons()
    {
        $reasons = IncomingReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray();
        return response()->json(['reasons' => $reasons]);
    }

    public function storeIncomingReason(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $name = trim($request->name);
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Reason name is required.'], 422);
        }
        if (IncomingReason::where('name', $name)->exists()) {
            return response()->json(['success' => false, 'message' => 'This reason already exists.'], 422);
        }
        $maxOrder = IncomingReason::max('sort_order') ?? 0;
        IncomingReason::create([
            'name' => $name,
            'sort_order' => $maxOrder + 1,
        ]);
        return response()->json([
            'success' => true,
            'reasons' => IncomingReason::orderBy('sort_order')->orderBy('name')->pluck('name')->toArray(),
        ]);
    }

   

    public function incomingOrderStore(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'qty' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'reason' => 'required|string',
        ]);

        $sku = trim($request->sku);

        try {
            // Store in incoming_data table
            $incomingOrder = IncomingData::updateOrCreate(
                ['sku' => $sku], // since sku is unique
                [
                    'warehouse_id' => $request->warehouse_id,
                    'quantity'     => (int) $request->qty,
                    'reason'       => $request->reason,
                    'approved_by'  => Auth::user()->name ?? 'N/A',
                    'approved_at'  => Carbon::now('America/New_York'),

                ]
            );

            return response()->json([
                'message' => 'Incoming order stored successfully',
                'data'    => $incomingOrder
            ]);

        } catch (\Exception $e) {
            Log::error("Incoming order store failed for SKU $sku: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }


    public function incomingOrderList()
    {
        $data = IncomingData::with('warehouse')
            ->get()
            ->map(function ($item) {
                return [
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'reason' => $item->reason,
                    'warehouse_name' => $item->warehouse->name ?? '',
                    'approved_by' => $item->approved_by,
                    'approved_at' =>  $item->approved_at
                        ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('m-d-Y')
                        : '',
                ];
            });

        return response()->json(['data' => $data]);
    }




}
