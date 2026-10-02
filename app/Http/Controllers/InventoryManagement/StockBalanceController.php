<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Inventory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\StockBalance;
use App\Models\StockBalanceTransferPreference;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ShopifyApiInventoryController;
use App\Models\ShopifySku;
use App\Models\SkuRelationship;
use Illuminate\Support\Facades\DB;
use App\Services\ShopifyAdminCallGate;
use App\Services\ShopifyOhioLocationResolver;
use App\Services\ShopifyStockTransferGraphql;


class StockBalanceController extends Controller
{

    protected $shopifyDomain;
    protected $shopifyApiKey;
    protected $shopifyPassword;

    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
        $this->shopifyDomain = config('services.shopify.store_url');
        $this->shopifyApiKey = config('services.shopify.api_key');
        $this->shopifyPassword = config('services.shopify.password');
    }


    /**
     * Make Shopify REST call, sharing the app-wide leaky-bucket gate.
     * Retries 429 and 5xx. Wait grows even when Retry-After is only 2s,
     * because other workers keep refilling the same bucket.
     */
    private function shopifyApiCall($method, $url, $data = [], $maxRetries = 6)
    {
        $attempt = 0;
        $response = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            ShopifyAdminCallGate::acquire();

            try {
                $request = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                    ->timeout(30);

                if ($method === 'GET') {
                    $response = $request->get($url, $data);
                } else {
                    $response = $request->post($url, $data);
                }
            } catch (\Throwable $e) {
                Log::warning('Shopify REST call failed, will retry', [
                    'attempt' => $attempt,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                sleep(min(2 ** $attempt, 8));
                continue;
            }

            ShopifyAdminCallGate::record($response);

            $retryable = ShopifyAdminCallGate::isRateLimited($response) || $response->status() >= 500;
            if (! $retryable) {
                return $response;
            }

            if ($attempt >= $maxRetries) {
                break;
            }

            $retryAfter = $response->header('Retry-After');
            $headerWait = is_numeric($retryAfter) ? (float) $retryAfter : 0;
            $waitTime = (int) ceil(max($headerWait, min(2 ** $attempt, 8), 2));

            Log::info('Shopify rate limit, waiting before retry', [
                'attempt' => $attempt,
                'wait_seconds' => $waitTime,
                'status' => $response->status(),
                'url' => $url,
            ]);
            sleep($waitTime);
        }

        return $response;
    }

    /**
     * Admin GraphQL. Uses the cost bucket, not the REST 2-calls/second limit.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>|null
     */
    private function shopifyGraphql(string $query, array $variables = [], int $maxAttempts = 6): ?array
    {
        $token = $this->shopifyPassword ?: config('services.shopify.access_token');
        $url = "https://{$this->shopifyDomain}/admin/api/2025-01/graphql.json";
        $delay = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $token,
                ])->timeout(30)->post($url, [
                    'query' => $query,
                    'variables' => $variables,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Stock balance Shopify GraphQL exception', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt === $maxAttempts) {
                    return null;
                }
                sleep(min($delay, 8));
                $delay = min($delay * 2, 8);
                continue;
            }

            $json = $response->json();
            if (! is_array($json)) {
                $json = [];
            }

            $throttled = $response->status() === 429 || ShopifyStockTransferGraphql::isThrottled($json);
            if ($throttled || $response->status() >= 500) {
                if ($attempt === $maxAttempts) {
                    Log::warning('Stock balance Shopify GraphQL exhausted retries', [
                        'status' => $response->status(),
                    ]);

                    return null;
                }
                $wait = ShopifyStockTransferGraphql::throttleWaitSeconds($response->header('Retry-After'), $json, $delay);
                Log::info('Stock balance Shopify GraphQL backing off', [
                    'attempt' => $attempt,
                    'wait_seconds' => $wait,
                    'status' => $response->status(),
                ]);
                sleep($wait);
                $delay = min($delay * 2, 8);
                continue;
            }

            if (! $response->successful()) {
                Log::error('Stock balance Shopify GraphQL HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            if (! empty($json['errors']) && ! isset($json['data'])) {
                Log::error('Stock balance Shopify GraphQL errors', ['errors' => $json['errors']]);

                return null;
            }

            return $json;
        }

        return null;
    }

    private function ohioLocationId(): ?string
    {
        $configured = config('services.shopify.inventory_location_id');
        if (! empty($configured)) {
            return (string) $configured;
        }

        $cached = Cache::get('shopify_ohio_preferred_location_id');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $fromGraphql = ShopifyStockTransferGraphql::parseOhioLocationId(
            $this->shopifyGraphql(ShopifyStockTransferGraphql::LOCATIONS_QUERY)
        );
        if ($fromGraphql !== null) {
            Cache::put('shopify_ohio_preferred_location_id', $fromGraphql, 3600);

            return $fromGraphql;
        }

        return ShopifyOhioLocationResolver::preferredLocationId();
    }

    /**
     * Inventory item, Ohio location, and live available qty for a transfer SKU.
     *
     * @return array<string, mixed>
     */
    private function inventoryInfoForSku(string $sku): array
    {
        $shopifySku = ShopifySku::where('sku', $sku)->first();

        if (! $shopifySku || ! $shopifySku->variant_id) {
            Log::error('SKU not found in shopify_skus table', [
                'sku' => $sku,
                'found_in_db' => $shopifySku ? 'yes' : 'no',
            ]);

            return [
                'success' => false,
                'error' => 'SKU not found in Shopify inventory',
                'details' => "The SKU '{$sku}' was not found in your local Shopify inventory table. Please sync your Shopify data first.",
            ];
        }

        $variantId = (string) $shopifySku->variant_id;
        $graph = $this->inventoryInfoViaGraphQl($variantId, $sku);
        if ($graph !== null) {
            return $graph;
        }

        $variantResponse = $this->shopifyApiCall(
            'GET',
            "https://{$this->shopifyDomain}/admin/api/2025-01/variants/{$variantId}.json"
        );

        if (! $variantResponse->successful()) {
            $isRateLimit = ShopifyAdminCallGate::isRateLimited($variantResponse);
            Log::error('Failed to fetch variant for SKU', [
                'sku' => $sku,
                'variant_id' => $variantId,
                'status' => $variantResponse->status(),
                'body' => $variantResponse->body(),
            ]);

            return [
                'success' => false,
                'error' => $isRateLimit ? 'Shopify rate limit' : 'Failed to fetch product from Shopify',
                'details' => $isRateLimit
                    ? "Too many requests to Shopify. Please wait a minute and try again (SKU: {$sku})."
                    : 'Error '.$variantResponse->status()." - Could not retrieve product details for SKU: {$sku}",
                'is_rate_limit' => $isRateLimit,
            ];
        }

        $inventoryItemId = $variantResponse->json('variant.inventory_item_id');
        if (! $inventoryItemId) {
            return [
                'success' => false,
                'error' => 'Invalid product data',
                'details' => "Could not find inventory item ID for SKU: {$sku}",
            ];
        }

        return $this->inventoryInfoFromRestLevels((string) $inventoryItemId, $sku);
    }

    /**
     * @return array<string, mixed>|null null when GraphQL could not be used and REST should run
     */
    private function inventoryInfoViaGraphQl(string $variantId, string $sku): ?array
    {
        $locationId = $this->ohioLocationId();
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $json = $this->shopifyGraphql(ShopifyStockTransferGraphql::VARIANT_INVENTORY_QUERY, [
            'id' => 'gid://shopify/ProductVariant/'.$variantId,
            'locationId' => 'gid://shopify/Location/'.$locationId,
        ]);
        $parsed = ShopifyStockTransferGraphql::parseVariantInventory($json);
        if ($parsed['status'] === 'failed') {
            return null;
        }
        if ($parsed['status'] === 'missing') {
            return [
                'success' => false,
                'error' => 'Failed to fetch product from Shopify',
                'details' => "Could not retrieve product details for SKU: {$sku}",
            ];
        }

        $inventoryItemId = (string) $parsed['inventory_item_id'];
        if ($parsed['available'] === null) {
            $levels = $this->inventoryInfoFromRestLevels($inventoryItemId, $sku);
            if (! ($levels['success'] ?? false)) {
                return $levels;
            }

            return [
                'success' => true,
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $levels['location_id'],
                'available' => $levels['available'],
            ];
        }

        Log::info('Got inventory info for SKU via GraphQL', [
            'sku' => $sku,
            'variant_id' => $variantId,
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'available_qty' => $parsed['available'],
        ]);

        return [
            'success' => true,
            'inventory_item_id' => $inventoryItemId,
            'location_id' => (string) $locationId,
            'available' => (int) $parsed['available'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryInfoFromRestLevels(string $inventoryItemId, string $sku): array
    {
        $levelsResponse = $this->shopifyApiCall(
            'GET',
            "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json",
            ['inventory_item_ids' => $inventoryItemId]
        );

        if (! $levelsResponse->successful()) {
            $status = $levelsResponse->status();
            $isRateLimit = ShopifyAdminCallGate::isRateLimited($levelsResponse);
            Log::error('Failed to fetch inventory levels for SKU', [
                'sku' => $sku,
                'inventory_item_id' => $inventoryItemId,
                'status' => $status,
            ]);

            return [
                'success' => false,
                'error' => $isRateLimit ? 'Shopify rate limit' : 'Failed to get current inventory level',
                'details' => $isRateLimit
                    ? "Too many requests to Shopify. Please wait a minute and try again (SKU: {$sku})."
                    : "Error {$status} - Could not fetch inventory levels for SKU: {$sku}",
                'is_rate_limit' => $isRateLimit,
                'status' => $status,
            ];
        }

        $levels = $levelsResponse->json('inventory_levels') ?? [];
        $ohioLevel = ShopifyOhioLocationResolver::levelFromLevels($levels);
        $locationId = $ohioLevel['location_id'];
        $availableQty = $ohioLevel['available'];

        if (! $locationId) {
            return [
                'success' => false,
                'error' => 'Shopify location not found',
                'details' => "Could not determine location for SKU: {$sku}",
            ];
        }

        Log::info('Got inventory info for SKU', [
            'sku' => $sku,
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'available_qty' => $availableQty,
        ]);

        return [
            'success' => true,
            'inventory_item_id' => $inventoryItemId,
            'location_id' => $locationId,
            'available' => $availableQty,
        ];
    }

    /**
     * Adjust available qty. GraphQL first, REST adjust.json if GraphQL is unavailable.
     *
     * @return array{success: bool, error?: string, is_rate_limit?: bool, response?: mixed}
     */
    private function adjustShopifyAvailable(string $inventoryItemId, string $locationId, int $delta): array
    {
        $json = $this->shopifyGraphql(ShopifyStockTransferGraphql::ADJUST_MUTATION, [
            'input' => [
                'reason' => 'correction',
                'name' => 'available',
                'changes' => [[
                    'delta' => $delta,
                    'inventoryItemId' => 'gid://shopify/InventoryItem/'.$inventoryItemId,
                    'locationId' => 'gid://shopify/Location/'.$locationId,
                ]],
            ],
        ]);

        if ($json !== null && ! ShopifyStockTransferGraphql::isThrottled($json)) {
            $parsed = ShopifyStockTransferGraphql::parseAdjust($json);
            if ($parsed['success']) {
                return ['success' => true, 'response' => $json];
            }

            $error = $parsed['error'] ?? 'Shopify rejected the inventory adjustment';
            if (! str_contains(strtolower($error), 'shopify request failed') && ! str_contains(strtolower($error), 'did not adjust')) {
                return ['success' => false, 'error' => $error, 'response' => $json];
            }
        }

        $response = $this->shopifyApiCall(
            'POST',
            "https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/adjust.json",
            [
                'inventory_item_id' => $inventoryItemId,
                'location_id' => $locationId,
                'available_adjustment' => $delta,
            ]
        );

        if ($response->successful()) {
            return ['success' => true, 'response' => $response->json()];
        }

        $body = $response->json();
        $shopifyError = $body['errors'] ?? $response->body();
        $isRateLimit = ShopifyAdminCallGate::isRateLimited($response);

        return [
            'success' => false,
            'error' => is_string($shopifyError) ? $shopifyError : json_encode($shopifyError),
            'is_rate_limit' => $isRateLimit,
            'response' => $body,
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::select('id', 'name')->get();
        // $skus = ProductMaster::select('id','parent','sku')->get();

        $skus = ProductMaster::select('product_master.id', 'product_master.parent', 'product_master.sku', 'shopify_skus.inv as available_quantity', 'shopify_skus.quantity as l30')
            ->leftJoin('shopify_skus', 'product_master.sku', '=', 'shopify_skus.sku')
            ->get()
            ->map(function ($item) {
            $inv = $item->available_quantity ?? 0;
            $l30 = $item->l30 ?? 0;
            $item->dil = $inv != 0 ? round(($l30 / $inv) * 100) : 0;
            return $item;
        });

        return view('inventory-management.stock-balance-view', compact('warehouses', 'skus'));
    }
    
    /**
     * Display the tabulator view
     */
    public function tabulatorView()
    {
        return view('inventory-management.stock-balance-tabulator');
    }

    /**
     * Display the alternate stock balance view
     */
    public function alternateView()
    {
        return view('inventory-management.stock-balance-alternate');
    }

    /**
     * Display the combo-trf view (duplicate of stock balance tabulator)
     */
    public function comboTrfView()
    {
        return view('inventory-management.combo-trf');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Resolve request SKU to the exact SKU string used in Product Master / shopify_skus
     * so transfer lookup matches getInventoryData (handles casing and spacing differences).
     *
     * @param string $requestSku
     * @param callable $normalizeSku (sku) => normalized string
     * @return string|null Resolved SKU or null if not found
     */
    private function resolveSkuForTransfer(string $requestSku, callable $normalizeSku): ?string
    {
        $normalized = $normalizeSku($requestSku);
        if ($normalized === '') {
            return null;
        }
        // Match how getInventoryData builds the table: ProductMaster SKUs, then shopify_skus by that SKU
        $pm = ProductMaster::all()->first(function ($item) use ($normalizeSku, $normalized) {
            return $normalizeSku($item->sku ?? '') === $normalized;
        });
        if ($pm && !empty(trim($pm->sku ?? ''))) {
            return trim($pm->sku);
        }
        // Fallback: find any shopify_skus row whose SKU normalizes to the same (e.g. synced from Shopify with different format)
        $shopify = ShopifySku::all()->first(function ($item) use ($normalizeSku, $normalized) {
            return $normalizeSku($item->sku ?? '') === $normalized;
        });
        return $shopify ? trim($shopify->sku) : null;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Increase max execution time to prevent timeout
        set_time_limit(120);
        
        $request->validate([
            'from_parent_name' => 'required|string',
            'from_sku' => 'required|string',
            'from_dil_percent' => 'nullable|numeric',
            'from_available_qty' => 'nullable|integer',
            'from_adjust_qty' => 'required|integer|min:1',

            'to_parent_name' => 'required|string',
            'to_sku' => 'required|string',
            'to_dil_percent' => 'nullable|numeric',
            'to_available_qty' => 'nullable|integer',
            'to_adjust_qty' => 'required|integer|min:1',

            'transferred_by' => 'nullable|string',
            'transferred_at' => 'nullable|date',
        ]);

        try {
            // Normalize SKU the same way as getInventoryData so lookup matches table (avoids "not found" when casing/spacing differs)
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim((string) $sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $fromSkuRaw = trim($request->from_sku);
            $toSkuRaw = trim($request->to_sku);
            $fromSku = $this->resolveSkuForTransfer($fromSkuRaw, $normalizeSku);
            $toSku = $this->resolveSkuForTransfer($toSkuRaw, $normalizeSku);
            if ($fromSku === null) {
                return response()->json([
                    'error' => 'FROM SKU not found',
                    'details' => "The SKU '{$fromSkuRaw}' was not found in Product Master / Shopify inventory. Check spelling and sync."
                ], 404);
            }
            if ($toSku === null) {
                return response()->json([
                    'error' => 'TO SKU not found',
                    'details' => "The SKU '{$toSkuRaw}' was not found in Product Master / Shopify inventory. Check spelling and sync."
                ], 404);
            }

            $fromQty = (int) $request->from_adjust_qty;
            $toQty = (int) $request->to_adjust_qty;

            Log::info("Stock balance transfer request received", [
                'from_sku' => $fromSku,
                'to_sku' => $toSku,
                'from_qty' => $fromQty,
                'to_qty' => $toQty,
                'from_available_qty' => $request->from_available_qty,
                'to_available_qty' => $request->to_available_qty,
                'user' => Auth::user()->name ?? 'Unknown'
            ]);
            
            // Block transferring FROM a SKU that has negative inventory (cannot deduct from negative).
            // Allow transferring TO a SKU with negative inventory — that is how you fix it (add stock).
            if ($request->from_available_qty < 0) {
                Log::error("Negative inventory detected for FROM SKU", [
                    'from_sku' => $fromSku,
                    'from_available_qty' => $request->from_available_qty
                ]);
                
                return response()->json([
                    'error' => 'Invalid inventory data: FROM SKU has negative inventory (' . $request->from_available_qty . '). Please check Shopify inventory for ' . $fromSku,
                    'details' => 'Cannot transfer from SKU with negative inventory'
                ], 422);
            }

            $getInventoryInfo = function ($sku) {
                return $this->inventoryInfoForSku($sku);
            };

            // Step 1: Get inventory info and decrease from 'from_sku'
            $fromInfo = $getInventoryInfo($fromSku);
            
            if (!$fromInfo['success']) {
                return response()->json([
                    'error' => $fromInfo['error'],
                    'details' => $fromInfo['details'],
                    'is_rate_limit' => $fromInfo['is_rate_limit'] ?? false,
                ], $fromInfo['is_rate_limit'] ?? false ? 429 : 404);
            }

            // Validate that there's enough inventory available (Shopify live, not table cache)
            $currentAvailable = $fromInfo['available'] ?? 0;
            if ($currentAvailable < $fromQty) {
                Log::warning("Insufficient inventory for transfer", [
                    'from_sku' => $fromSku,
                    'requested_qty' => $fromQty,
                    'available_qty' => $currentAvailable
                ]);
                $tableShows = (int) $request->from_available_qty;
                $hint = ($tableShows > $currentAvailable)
                    ? "<br><br><strong>Note:</strong> The table shows cached inventory ({$tableShows}). Shopify live available is {$currentAvailable}. Refresh the page and try again, or sync Shopify quantities."
                    : '';

                return response()->json([
                    'error' => 'Insufficient Inventory',
                    'details' => "Cannot transfer {$fromQty} units from SKU: {$fromSku}<br><br>" .
                                "<strong>Shopify live available:</strong> {$currentAvailable} units<br>" .
                                "<strong>Requested transfer:</strong> {$fromQty} units<br><br>" .
                                "You need <strong>" . ($fromQty - $currentAvailable) . " more units</strong> to complete this transfer." . $hint
                ], 400);
            }

            $decrease = $this->adjustShopifyAvailable(
                (string) $fromInfo['inventory_item_id'],
                (string) $fromInfo['location_id'],
                -$fromQty
            );

            if (!$decrease['success']) {
                $shopifyError = $decrease['error'] ?? 'Unknown Shopify error';
                
                Log::error("Failed to deduct inventory for SKU", [
                    'sku' => $fromSku,
                    'response' => $decrease['response'] ?? null,
                    'requested_qty' => $fromQty,
                    'available_before_attempt' => $currentAvailable
                ]);
                
                return response()->json([
                    'error' => ($decrease['is_rate_limit'] ?? false) ? 'Shopify rate limit' : 'Failed to deduct inventory from Shopify',
                    'details' => "Could not decrease stock for SKU: {$fromSku}<br><br>" .
                                "<strong>Available Quantity:</strong> {$currentAvailable} units<br>" .
                                "<strong>Attempted Deduction:</strong> {$fromQty} units<br><br>" .
                                "<strong>Shopify Error:</strong> " . $shopifyError,
                    'is_rate_limit' => $decrease['is_rate_limit'] ?? false,
                ], ($decrease['is_rate_limit'] ?? false) ? 429 : 500);
            }

            Log::info("Successfully decreased inventory", [
                'sku' => $fromSku,
                'adjustment' => -$fromQty,
                'response' => $decrease['response'] ?? null
            ]);

            // Step 2: Get inventory info and increase to 'to_sku'
            $toInfo = $getInventoryInfo($toSku);
            
            if (!$toInfo['success']) {
                return response()->json([
                    'error' => $toInfo['error'],
                    'details' => $toInfo['details'],
                    'is_rate_limit' => $toInfo['is_rate_limit'] ?? false,
                ], $toInfo['is_rate_limit'] ?? false ? 429 : 404);
            }

            $increase = $this->adjustShopifyAvailable(
                (string) $toInfo['inventory_item_id'],
                (string) $toInfo['location_id'],
                $toQty
            );

            if (!$increase['success']) {
                Log::error("Failed to increase inventory for SKU", [
                    'sku' => $toSku,
                    'response' => $increase['response'] ?? $increase['error'] ?? null
                ]);
                
                // Try to rollback the first adjustment
                Log::warning("Attempting to rollback first adjustment", [
                    'from_sku' => $fromSku,
                    'rollback_qty' => $fromQty
                ]);
                
                $rollback = $this->adjustShopifyAvailable(
                    (string) $fromInfo['inventory_item_id'],
                    (string) $fromInfo['location_id'],
                    $fromQty
                );
                
                if ($rollback['success']) {
                    Log::info("Successfully rolled back first adjustment", ['sku' => $fromSku]);
                    return response()->json([
                        'error' => ($increase['is_rate_limit'] ?? false) ? 'Shopify rate limit' : 'Failed to increase inventory in Shopify',
                        'details' => "Could not increase stock for SKU: $toSku. Previous deduction has been rolled back.",
                        'is_rate_limit' => $increase['is_rate_limit'] ?? false,
                    ], ($increase['is_rate_limit'] ?? false) ? 429 : 500);
                } else {
                    Log::error("Failed to rollback first adjustment", [
                        'sku' => $fromSku,
                        'error' => $rollback['error'] ?? null
                    ]);
                    return response()->json([
                        'error' => 'Failed to increase inventory in Shopify',
                        'details' => "Could not increase stock for SKU: $toSku. WARNING: $fromSku was decreased by $fromQty but rollback failed!"
                    ], 500);
                }
            }

            Log::info("Successfully increased inventory", [
                'sku' => $toSku,
                'adjustment' => $toQty,
                'response' => $increase['response'] ?? null
            ]);

            // Step 3: Only save to database after both Shopify updates succeed
            try {
                DB::beginTransaction();
                
                // Cap DIL percent values to prevent database overflow
                // Database column is decimal(5,2), range: -999.99 to 999.99
                // We'll clamp between -999.99 and 100 (since DIL % shouldn't exceed 100)
                $fromDilPercent = $request->from_dil_percent;
                if ($fromDilPercent > 100) {
                    Log::warning("from_dil_percent exceeds 100%, capping it", [
                        'original' => $fromDilPercent,
                        'capped' => 100
                    ]);
                    $fromDilPercent = 100;
                } elseif ($fromDilPercent < -999.99) {
                    Log::warning("from_dil_percent below minimum, capping it", [
                        'original' => $fromDilPercent,
                        'capped' => -999.99
                    ]);
                    $fromDilPercent = -999.99;
                }
                
                $toDilPercent = $request->to_dil_percent;
                if ($toDilPercent > 100) {
                    Log::warning("to_dil_percent exceeds 100%, capping it", [
                        'original' => $toDilPercent,
                        'capped' => 100
                    ]);
                    $toDilPercent = 100;
                } elseif ($toDilPercent < -999.99) {
                    Log::warning("to_dil_percent below minimum, capping it", [
                        'original' => $toDilPercent,
                        'capped' => -999.99
                    ]);
                    $toDilPercent = -999.99;
                }
                
                $dataToInsert = [
                    'from_parent_name'     => $request->from_parent_name,
                    'from_sku'             => $fromSku,
                    'from_dil_percent'     => $fromDilPercent,
                    'from_available_qty'   => $request->from_available_qty,
                    'from_adjust_qty'      => $fromQty,

                    'to_parent_name'       => $request->to_parent_name,
                    'to_sku'               => $toSku,
                    'to_dil_percent'       => $toDilPercent,
                    'to_available_qty'     => $request->to_available_qty,
                    'to_adjust_qty'        => $toQty,

                    'transferred_by'       => Auth::user()->name ?? 'N/A',
                    'transferred_at'       => Carbon::now('America/New_York'),
                ];
                
                Log::info("Attempting to save stock balance to database", [
                    'data' => $dataToInsert
                ]);
                
                StockBalance::create($dataToInsert);
                
                DB::commit();
                
                Log::info("Stock balance transfer saved to database", [
                    'from_sku' => $fromSku,
                    'to_sku' => $toSku
                ]);
                
            } catch (\Exception $dbException) {
                DB::rollBack();
                
                Log::error("Failed to save stock balance to database after successful Shopify updates", [
                    'from_sku' => $fromSku,
                    'to_sku' => $toSku,
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                    'line' => $dbException->getLine(),
                    'file' => $dbException->getFile()
                ]);
                
                return response()->json([
                    'error' => 'Shopify updated successfully but failed to save to database',
                    'details' => "Inventory was transferred in Shopify successfully.<br><br>" .
                                "<strong>Database Error:</strong> " . $dbException->getMessage() . "<br><br>" .
                                "<strong>Transfer Details:</strong><br>" .
                                "From: $fromSku (-$fromQty)<br>" .
                                "To: $toSku (+$toQty)<br><br>" .
                                "<em>Note: The inventory has been updated in Shopify but the record was not saved to your local database.</em>",
                    'shopify_updated' => true,
                    'db_error' => $dbException->getMessage()
                ], 500);
            }

            return response()->json([
                'message' => '✓ SHOPIFY - Stock transferred successfully and saved to database'
            ]);

        } catch (\Exception $e) {
            Log::error("Stock transfer failed: " . $e->getMessage(), [
                'from_sku' => $request->from_sku ?? 'N/A',
                'to_sku' => $request->to_sku ?? 'N/A',
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'error' => 'Error storing stock balance',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store combo transfer: multiple FROM SKUs → one TO SKU.
     * Request: to_sku, to_parent_name, to_available_qty, to_dil_percent, to_adjust_qty (total),
     *          from_items = [{ sku, parent_name, available_qty, dil_percent, adjust_qty }, ...]
     */
    public function storeComboTrf(Request $request)
    {
        set_time_limit(120);

        $request->validate([
            'to_parent_name' => 'required|string',
            'to_sku' => 'required|string',
            'to_dil_percent' => 'nullable|numeric',
            'to_available_qty' => 'nullable|integer',
            'to_adjust_qty' => 'required|integer|min:1',
            'from_items' => 'required|array|min:1',
            'from_items.*.sku' => 'required|string',
            'from_items.*.parent_name' => 'nullable|string',
            'from_items.*.available_qty' => 'nullable|integer',
            'from_items.*.dil_percent' => 'nullable|numeric',
            'from_items.*.adjust_qty' => 'required|integer|min:1',
        ]);

        $toSku = trim($request->to_sku);
        $toQty = (int) $request->to_adjust_qty;
        $fromItems = $request->from_items;

        $normalizeDil = function ($v) {
            if ($v === null || $v === '') return null;
            $v = (float) $v;
            if ($v > 100) return 100.0;
            if ($v < -999.99) return -999.99;
            return $v;
        };

        $getInventoryInfo = function ($sku) {
            return $this->inventoryInfoForSku($sku);
        };

        foreach ($fromItems as $item) {
            $qty = (int) ($item['adjust_qty'] ?? 0);
            if ($qty < 1) {
                return response()->json(['error' => 'Invalid from_items', 'details' => 'Each FROM item must have adjust_qty >= 1'], 422);
            }
        }
        // Combo: TO qty = combo count (added to destination); we deduct each from_sku's qty (sum can be larger, e.g. 5+5 from two SKUs, add 5 to combo TO).

        $fromInfos = [];
        foreach ($fromItems as $item) {
            $sku = trim($item['sku']);
            $adjQty = (int) $item['adjust_qty'];
            $info = $getInventoryInfo($sku);
            if (!$info['success']) {
                return response()->json([
                    'error' => $info['error'],
                    'details' => $info['details'],
                    'is_rate_limit' => $info['is_rate_limit'] ?? false,
                ], ($info['is_rate_limit'] ?? false) ? 429 : 404);
            }
            if (($info['available'] ?? 0) < $adjQty) {
                return response()->json([
                    'error' => 'Insufficient Inventory',
                    'details' => "Cannot transfer {$adjQty} units from SKU: {$sku}. Current available: " . ($info['available'] ?? 0)
                ], 400);
            }
            $fromInfos[] = [
                'sku' => $sku,
                'parent_name' => $item['parent_name'] ?? '',
                'available_qty' => $item['available_qty'] ?? 0,
                'dil_percent' => $normalizeDil($item['dil_percent'] ?? null),
                'adjust_qty' => $adjQty,
                'inventory_item_id' => $info['inventory_item_id'],
                'location_id' => $info['location_id'],
            ];
        }

        $deducted = [];
        foreach ($fromInfos as $from) {
            $decrease = $this->adjustShopifyAvailable(
                (string) $from['inventory_item_id'],
                (string) $from['location_id'],
                -$from['adjust_qty']
            );
            if (!$decrease['success']) {
                foreach ($deducted as $rollback) {
                    $this->adjustShopifyAvailable(
                        (string) $rollback['inventory_item_id'],
                        (string) $rollback['location_id'],
                        $rollback['adjust_qty']
                    );
                }
                return response()->json([
                    'error' => ($decrease['is_rate_limit'] ?? false) ? 'Shopify rate limit' : 'Failed to deduct inventory from Shopify',
                    'details' => 'Could not decrease stock for SKU: ' . $from['sku'],
                    'is_rate_limit' => $decrease['is_rate_limit'] ?? false,
                ], ($decrease['is_rate_limit'] ?? false) ? 429 : 500);
            }
            $deducted[] = $from;
        }

        $toInfo = $getInventoryInfo($toSku);
        if (!$toInfo['success']) {
            foreach ($deducted as $rollback) {
                $this->adjustShopifyAvailable(
                    (string) $rollback['inventory_item_id'],
                    (string) $rollback['location_id'],
                    $rollback['adjust_qty']
                );
            }
            return response()->json([
                'error' => $toInfo['error'],
                'details' => $toInfo['details'],
                'is_rate_limit' => $toInfo['is_rate_limit'] ?? false,
            ], ($toInfo['is_rate_limit'] ?? false) ? 429 : 404);
        }

        $increase = $this->adjustShopifyAvailable(
            (string) $toInfo['inventory_item_id'],
            (string) $toInfo['location_id'],
            $toQty
        );

        if (!$increase['success']) {
            foreach ($deducted as $rollback) {
                $this->adjustShopifyAvailable(
                    (string) $rollback['inventory_item_id'],
                    (string) $rollback['location_id'],
                    $rollback['adjust_qty']
                );
            }
            return response()->json([
                'error' => ($increase['is_rate_limit'] ?? false) ? 'Shopify rate limit' : 'Failed to increase inventory in Shopify',
                'details' => 'Could not increase stock for TO SKU: ' . $toSku,
                'is_rate_limit' => $increase['is_rate_limit'] ?? false,
            ], ($increase['is_rate_limit'] ?? false) ? 429 : 500);
        }

        try {
            DB::beginTransaction();
            $toParent = $request->to_parent_name;
            $toDilPercent = $normalizeDil($request->to_dil_percent) ?? 0;
            $toAvailableQty = (int) $request->to_available_qty;
            $transferredAt = Carbon::now('America/New_York');
            $transferredBy = Auth::user()->name ?? 'N/A';

            foreach ($fromInfos as $from) {
                StockBalance::create([
                    'from_parent_name' => $from['parent_name'],
                    'from_sku' => $from['sku'],
                    'from_dil_percent' => $from['dil_percent'],
                    'from_available_qty' => $from['available_qty'],
                    'from_adjust_qty' => $from['adjust_qty'],
                    'to_parent_name' => $toParent,
                    'to_sku' => $toSku,
                    'to_dil_percent' => $toDilPercent,
                    'to_available_qty' => $toAvailableQty,
                    'to_adjust_qty' => $from['adjust_qty'],
                    'transferred_by' => $transferredBy,
                    'transferred_at' => $transferredAt,
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Combo TRF database save failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Shopify updated but failed to save to database',
                'details' => $e->getMessage()
            ], 500);
        }

        return response()->json(['message' => '✓ Combo transfer completed. ' . count($fromInfos) . ' FROM SKU(s) → 1 TO SKU, saved to database.']);
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
        $data = StockBalance::latest()->get()->map(function ($item) {
            return [
                'from_parent_name'    => $item->from_parent_name,
                'from_sku'            => $item->from_sku,
                'from_dil_percent'    => $item->from_dil_percent,
                'from_available_qty'  => $item->from_available_qty,
                'from_adjust_qty'     => $item->from_adjust_qty,

                'to_parent_name'      => $item->to_parent_name,
                'to_sku'              => $item->to_sku,
                'to_dil_percent'      => $item->to_dil_percent,
                'to_available_qty'    => $item->to_available_qty,
                'to_adjust_qty'       => $item->to_adjust_qty,

                'transferred_by'      => $item->transferred_by,
                'transferred_at'      => $item->transferred_at
                    ? Carbon::parse($item->transferred_at)->timezone('America/New_York')->format('m-d-Y')
                    : '',
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Get inventory data for the inventory table
     */
    public function getInventoryData()
    {
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return $sku;
        };

        // Fetch product master
        $productMasterData = ProductMaster::all();

        // Get SKUs
        $skus = $productMasterData->pluck('sku')
            ->filter()
            ->unique()
            ->map(fn($sku) => $normalizeSku($sku))
            ->toArray();

        $shopifyByPm = ShopifySku::mapByProductSkus($productMasterData->pluck('sku')->filter()->unique()->values()->all());

        // Fetch inventory action data
        // Get all inventory records and normalize their SKUs for matching
        // Since there might be multiple records per SKU, get the latest one for each normalized SKU
        $allInventoryActions = Inventory::all();
        $inventoryActions = collect();
        
        foreach ($allInventoryActions as $inv) {
            $normalizedInvSku = $normalizeSku($inv->sku);
            // Only include if this normalized SKU is in our list
            if (in_array($normalizedInvSku, $skus)) {
                // If we already have this normalized SKU, keep the one with the latest update
                if (!$inventoryActions->has($normalizedInvSku)) {
                    $inventoryActions[$normalizedInvSku] = $inv;
                } else {
                    $existing = $inventoryActions[$normalizedInvSku];
                    if ($inv->updated_at > $existing->updated_at) {
                        $inventoryActions[$normalizedInvSku] = $inv;
                    }
                }
            }
        }
        
        // Fetch last stock balance history for each SKU
        $lastStockBalances = collect();
        foreach ($skus as $sku) {
            // Get the latest stock balance where this SKU is either the FROM or TO SKU
            $lastBalance = StockBalance::where(function($query) use ($sku) {
                $query->where('from_sku', $sku)
                      ->orWhere('to_sku', $sku);
            })
            ->orderBy('transferred_at', 'desc')
            ->first();
            
            if ($lastBalance) {
                $lastStockBalances[$sku] = $lastBalance;
            }
        }

        // Merge everything
        $data = $productMasterData->map(function ($item) use ($shopifyByPm, $inventoryActions, $lastStockBalances, $normalizeSku) {
            $sku = $normalizeSku($item->sku ?? '');
            $shopify = $shopifyByPm->get($item->sku ?? '');
            $inventory = $inventoryActions[$sku] ?? null;
            $lastBalance = $lastStockBalances[$sku] ?? null;

            $inv = $shopify->inv ?? 0;
            $l30 = $shopify->quantity ?? 0;
            // Calculate DIL following verification-adjustment pattern:
            // If INV > 0 and L30 === 0, then DIL = 0
            // If INV is negative or zero, set DIL to 0 to prevent extreme values
            // Otherwise, if INV > 0, then DIL = L30 / INV
            if ($inv > 0 && $l30 === 0) {
                $dil = 0;
            } else if ($inv > 0) {
                $dil = $l30 / $inv;
                // Cap DIL to prevent extreme values (max 100x or -100x ratio)
                if ($dil > 100) {
                    $dil = 100;
                } else if ($dil < -100) {
                    $dil = -100;
                }
            } else {
                // If inventory is 0 or negative, set DIL to 0
                $dil = 0;
            }
            
            // Format last update history
            $lastUpdate = null;
            if ($lastBalance) {
                $direction = '';
                $otherSku = '';
                $qty = 0;
                
                if ($lastBalance->from_sku === $item->sku) {
                    $direction = 'OUT';
                    $otherSku = $lastBalance->to_sku;
                    $qty = $lastBalance->from_adjust_qty;
                } else {
                    $direction = 'IN';
                    $otherSku = $lastBalance->from_sku;
                    $qty = $lastBalance->to_adjust_qty;
                }
                
                $transferredAt = $lastBalance->transferred_at 
                    ? Carbon::parse($lastBalance->transferred_at)->timezone('America/New_York')->format('m/d/y H:i')
                    : '';
                
                $lastUpdate = [
                    'direction' => $direction,
                    'other_sku' => $otherSku,
                    'qty' => $qty,
                    'date' => $transferredAt,
                    'by' => $lastBalance->transferred_by
                ];
            }

            return [
                'IMAGE_URL' => $shopify->image_src ?? null,
                'Parent' => $item->parent ?? '(No Parent)',
                'SKU' => $item->sku ?? '',
                'INV' => $inv,
                'SOLD' => $l30,
                'DIL' => $dil,
                'ACTION' => $inventory->action ?? null,
                'LAST_UPDATE' => $lastUpdate,
            ];
        })->filter(function ($item) {
            // Filter out items with no SKU
            return !empty($item['SKU']);
        });

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $data->values(),
            'status' => 200
        ]);
    }

    /**
     * Normalize SKU for matching (same rules as getInventoryData).
     */
    private function normalizeSkuKey(?string $sku): string
    {
        $sku = strtoupper(trim((string) $sku));
        $sku = preg_replace('/\s+/u', ' ', $sku);
        $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);

        return $sku;
    }

    /**
     * Build INV / SOLD / DIL payload from a shopify_skus row (stock-balance view fields).
     *
     * @return array{INV: int, SOLD: float|int, DIL: float}
     */
    private function stockBalanceInventoryPayloadFromShopifyRow(ShopifySku $row): array
    {
        $inv = (int) ($row->inv ?? $row->available_to_sell ?? 0);
        $sold = (float) ($row->quantity ?? 0);

        if ($inv > 0 && $sold === 0.0) {
            $dil = 0.0;
        } elseif ($inv > 0) {
            $dil = $sold / $inv;
            if ($dil > 100) {
                $dil = 100.0;
            } elseif ($dil < -100) {
                $dil = -100.0;
            }
        } else {
            $dil = 0.0;
        }

        return [
            'INV' => $inv,
            'SOLD' => $sold,
            'DIL' => round($dil, 4),
        ];
    }

    /**
     * Pull latest inventory from Shopify for a single SKU (row-wise refresh).
     */
    public function refreshShopifyInventoryForSku(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
        ]);

        $sku = trim($validated['sku']);
        $normalized = $this->normalizeSkuKey($sku);

        if (str_starts_with($normalized, 'PARENT')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot refresh inventory for a parent row.',
            ], 422);
        }

        $shopifyController = app(ShopifyApiInventoryController::class);
        if (! $shopifyController->syncLiveInventoryForSku($sku, 0)) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh from Shopify. Ensure this SKU exists in shopify_skus with a variant_id.',
            ], 422);
        }

        $row = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])->first();
        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'SKU not found after Shopify sync.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inventory refreshed from Shopify.',
            'data' => $this->stockBalanceInventoryPayloadFromShopifyRow($row),
        ]);
    }

    /**
     * Pull latest inventory from Shopify for multiple selected SKUs.
     */
    public function refreshShopifyInventoryBulk(Request $request)
    {
        $validated = $request->validate([
            'skus' => 'required|array|min:1|max:150',
            'skus.*' => 'required|string|max:255',
        ]);

        $skus = [];
        foreach ($validated['skus'] as $rawSku) {
            $sku = trim((string) $rawSku);
            if ($sku === '') {
                continue;
            }
            $normalized = $this->normalizeSkuKey($sku);
            if (str_starts_with($normalized, 'PARENT')) {
                continue;
            }
            $skus[$normalized] = $sku;
        }

        $skus = array_values($skus);
        if ($skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'No valid SKUs to refresh.',
            ], 422);
        }

        $shopifyController = app(ShopifyApiInventoryController::class);
        $synced = $shopifyController->syncLiveInventoryForSkuList($skus, 0);

        $items = [];
        $failed = [];
        $updatedCount = 0;
        foreach ($skus as $sku) {
            $normalized = $this->normalizeSkuKey($sku);
            $row = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])->first();
            if (! $row) {
                $failed[] = $sku;
                continue;
            }
            $payload = $this->stockBalanceInventoryPayloadFromShopifyRow($row);
            $items[$sku] = $payload;
            if ($row->sku && $row->sku !== $sku) {
                $items[$row->sku] = $payload;
            }
            $updatedCount++;
        }

        if (! $synced && $updatedCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh selected SKUs from Shopify.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $synced
                ? ('Inventory refreshed from Shopify for '.$updatedCount.' SKU(s).')
                : ('Partial refresh: returned data for '.$updatedCount.' SKU(s); some SKUs could not sync.'),
            'data' => [
                'items' => $items,
                'failed' => $failed,
                'synced' => (bool) $synced,
            ],
        ]);
    }

    /**
     * Update action for a SKU
     */
    public function updateAction(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'action' => 'nullable|string|in:NRB,RB',
        ]);

        try {
            // Normalize SKU to match the same normalization used in getInventoryData
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $sku = $normalizeSku($request->sku);
            $action = $request->action;

            // inventories.sku can exist in mixed case / spacing variants (e.g. "1Pc" vs "1PC").
            // Read APIs normalize SKUs, so update every normalized match to keep UI values consistent.
            $matchedRows = Inventory::select('id', 'sku')->get()->filter(function ($inv) use ($normalizeSku, $sku) {
                return $normalizeSku($inv->sku ?? '') === $sku;
            });

            if ($matchedRows->isEmpty()) {
                $inventory = new Inventory();
                $inventory->sku = trim((string) $request->sku);
                $inventory->action = $action;
                $inventory->save();
            } else {
                $matchedIds = $matchedRows->pluck('id')->all();
                Inventory::whereIn('id', $matchedIds)->update([
                    'action' => $action,
                    'updated_at' => now(),
                ]);
            }

            Log::info("Action updated successfully", [
                'sku' => $sku,
                'action' => $action
            ]);

            return response()->json([
                'message' => 'Action updated successfully',
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update action: " . $e->getMessage(), [
                'sku' => $request->sku ?? 'N/A',
                'action' => $request->action ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error updating action',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory data for combo transfer table (ACTION from inventories.combo_action)
     */
    public function getComboTrfInventoryData()
    {
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return $sku;
        };

        $productMasterData = ProductMaster::all();
        $skus = $productMasterData->pluck('sku')
            ->filter()
            ->unique()
            ->map(fn($sku) => $normalizeSku($sku))
            ->toArray();

        $shopifyByPm = ShopifySku::mapByProductSkus($productMasterData->pluck('sku')->filter()->unique()->values()->all());

        // ACTION from inventories.combo_action (same table as stock balance, different column)
        $allInventoryActions = Inventory::all();
        $inventoryActions = collect();
        foreach ($allInventoryActions as $inv) {
            $normalizedInvSku = $normalizeSku($inv->sku);
            if (in_array($normalizedInvSku, $skus)) {
                if (!$inventoryActions->has($normalizedInvSku)) {
                    $inventoryActions[$normalizedInvSku] = $inv;
                } else {
                    $existing = $inventoryActions[$normalizedInvSku];
                    if ($inv->updated_at > $existing->updated_at) {
                        $inventoryActions[$normalizedInvSku] = $inv;
                    }
                }
            }
        }

        $lastStockBalances = collect();
        foreach ($skus as $sku) {
            $lastBalance = StockBalance::where(function($query) use ($sku) {
                $query->where('from_sku', $sku)->orWhere('to_sku', $sku);
            })
            ->orderBy('transferred_at', 'desc')
            ->first();
            if ($lastBalance) {
                $lastStockBalances[$sku] = $lastBalance;
            }
        }

        $data = $productMasterData->map(function ($item) use ($shopifyByPm, $inventoryActions, $lastStockBalances, $normalizeSku) {
            $sku = $normalizeSku($item->sku ?? '');
            $shopify = $shopifyByPm->get($item->sku ?? '');
            $inventory = $inventoryActions[$sku] ?? null;
            $lastBalance = $lastStockBalances[$sku] ?? null;

            $inv = $shopify->inv ?? 0;
            $l30 = $shopify->quantity ?? 0;
            if ($inv > 0 && $l30 === 0) {
                $dil = 0;
            } else if ($inv > 0) {
                $dil = $l30 / $inv;
                if ($dil > 100) {
                    $dil = 100;
                } else if ($dil < -100) {
                    $dil = -100;
                }
            } else {
                $dil = 0;
            }

            $lastUpdate = null;
            if ($lastBalance) {
                if ($lastBalance->from_sku === $item->sku) {
                    $direction = 'OUT';
                    $otherSku = $lastBalance->to_sku;
                    $qty = $lastBalance->from_adjust_qty;
                } else {
                    $direction = 'IN';
                    $otherSku = $lastBalance->from_sku;
                    $qty = $lastBalance->to_adjust_qty;
                }
                $transferredAt = $lastBalance->transferred_at
                    ? Carbon::parse($lastBalance->transferred_at)->timezone('America/New_York')->format('m/d/y H:i')
                    : '';
                $lastUpdate = [
                    'direction' => $direction,
                    'other_sku' => $otherSku,
                    'qty' => $qty,
                    'date' => $transferredAt,
                    'by' => $lastBalance->transferred_by
                ];
            }

            return [
                'IMAGE_URL' => $shopify->image_src ?? null,
                'Parent' => $item->parent ?? '(No Parent)',
                'SKU' => $item->sku ?? '',
                'INV' => $inv,
                'SOLD' => $l30,
                'DIL' => $dil,
                'ACTION' => $inventory->combo_action ?? null,
                'LAST_UPDATE' => $lastUpdate,
            ];
        })->filter(function ($item) {
            return !empty($item['SKU']);
        });

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $data->values(),
            'status' => 200
        ]);
    }

    /**
     * Update combo transfer ACTION (writes to inventories.combo_action)
     */
    public function updateComboTrfAction(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'action' => 'nullable|string|in:NRB,RB',
        ]);

        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $sku = $normalizeSku($request->sku);
            $action = $request->action;

            // inventories.sku can exist in mixed case / spacing variants (e.g. "1Pc" vs "1PC").
            // Read APIs normalize SKUs, so update every normalized match to keep UI values consistent.
            $matchedRows = Inventory::select('id', 'sku')->get()->filter(function ($inv) use ($normalizeSku, $sku) {
                return $normalizeSku($inv->sku ?? '') === $sku;
            });

            if ($matchedRows->isEmpty()) {
                $inventory = new Inventory();
                $inventory->sku = trim((string) $request->sku);
                $inventory->combo_action = $action;
                $inventory->save();
            } else {
                $matchedIds = $matchedRows->pluck('id')->all();
                Inventory::whereIn('id', $matchedIds)->update([
                    'combo_action' => $action,
                    'updated_at' => now(),
                ]);
            }

            Log::info("Combo TRF action updated", ['sku' => $sku, 'action' => $action]);

            return response()->json([
                'message' => 'Action updated successfully',
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update combo TRF action: " . $e->getMessage(), [
                'sku' => $request->sku ?? 'N/A',
                'action' => $request->action ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error updating action',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get relationships for a SKU
     */
    public function getRelationships(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
        ]);

        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $sku = $normalizeSku($request->sku);

            $relationships = SkuRelationship::where('source_sku', $sku)
                ->pluck('related_sku')
                ->toArray();

            return response()->json([
                'message' => 'Relationships fetched successfully',
                'data' => $relationships,
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get relationships: " . $e->getMessage(), [
                'sku' => $request->sku ?? 'N/A',
            ]);
            
            return response()->json([
                'error' => 'Error fetching relationships',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add relationships for a SKU
     */
    public function addRelationships(Request $request)
    {
        $request->validate([
            'source_sku' => 'required|string',
            'related_skus' => 'required|array',
            'related_skus.*' => 'required|string',
        ]);

        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $sourceSku = $normalizeSku($request->source_sku);
            $relatedSkus = array_map($normalizeSku, $request->related_skus);

            // Remove duplicates and the source SKU itself
            $relatedSkus = array_unique($relatedSkus);
            $relatedSkus = array_filter($relatedSkus, function($sku) use ($sourceSku) {
                return $sku !== $sourceSku;
            });

            $added = 0;
            foreach ($relatedSkus as $relatedSku) {
                try {
                    SkuRelationship::firstOrCreate([
                        'source_sku' => $sourceSku,
                        'related_sku' => $relatedSku,
                    ]);
                    $added++;
                } catch (\Exception $e) {
                    // Skip if duplicate or other error
                    Log::warning("Failed to add relationship", [
                        'source_sku' => $sourceSku,
                        'related_sku' => $relatedSku,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'message' => "Successfully added {$added} relationship(s)",
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to add relationships: " . $e->getMessage(), [
                'source_sku' => $request->source_sku ?? 'N/A',
            ]);
            
            return response()->json([
                'error' => 'Error adding relationships',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a relationship
     */
    public function deleteRelationship(Request $request)
    {
        $request->validate([
            'source_sku' => 'required|string',
            'related_sku' => 'required|string',
        ]);

        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $sourceSku = $normalizeSku($request->source_sku);
            $relatedSku = $normalizeSku($request->related_sku);

            $deleted = SkuRelationship::where('source_sku', $sourceSku)
                ->where('related_sku', $relatedSku)
                ->delete();

            return response()->json([
                'message' => 'Relationship deleted successfully',
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete relationship: " . $e->getMessage(), [
                'source_sku' => $request->source_sku ?? 'N/A',
                'related_sku' => $request->related_sku ?? 'N/A',
            ]);
            
            return response()->json([
                'error' => 'Error deleting relationship',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all SKUs for autocomplete
     */
    public function getSkusForAutocomplete(Request $request)
    {
        // Accept both 'search' and 'term' parameters (Select2 uses 'term')
        $search = $request->get('search', $request->get('term', ''));
        
        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim($sku));
            $sku = preg_replace('/\s+/u', ' ', $sku);
            $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
            return $sku;
        };

        $query = ProductMaster::select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if ($search) {
            $normalizedSearch = $normalizeSku($search);
            $query->whereRaw("UPPER(TRIM(REPLACE(REPLACE(sku, '\n', ' '), '\r', ' '))) LIKE ?", ['%' . $normalizedSearch . '%']);
        }

        $skus = $query->distinct()
            ->orderBy('sku', 'asc')
            ->limit(100)
            ->pluck('sku')
            ->map(function($sku) use ($normalizeSku) {
                return [
                    'id' => $sku,
                    'text' => $sku,
                    'normalized' => $normalizeSku($sku)
                ];
            })
            ->values();

        return response()->json([
            'results' => $skus,
            'status' => 200
        ]);
    }

    /**
     * Get the most recent history for a from_sku to auto-fill the form
     */
    public function getRecentHistory(Request $request)
    {
        $request->validate([
            'from_sku' => 'required|string',
        ]);

        try {
            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim($sku));
                $sku = preg_replace('/\s+/u', ' ', $sku);
                $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);
                return $sku;
            };

            $fromSku = $normalizeSku($request->from_sku);

            // Get the most recent history for this from_sku
            $history = StockBalance::where('from_sku', $fromSku)
                ->orderBy('transferred_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$history) {
                return response()->json([
                    'message' => 'No history found for this SKU',
                    'data' => null,
                    'status' => 200
                ]);
            }

            return response()->json([
                'message' => 'History fetched successfully',
                'data' => [
                    'from_parent_name' => $history->from_parent_name,
                    'from_sku' => $history->from_sku,
                    'from_dil_percent' => $history->from_dil_percent,
                    'from_available_qty' => $history->from_available_qty,
                    'from_adjust_qty' => $history->from_adjust_qty,
                    'to_parent_name' => $history->to_parent_name,
                    'to_sku' => $history->to_sku,
                    'to_dil_percent' => $history->to_dil_percent,
                    'to_available_qty' => $history->to_available_qty,
                    'to_adjust_qty' => $history->to_adjust_qty,
                ],
                'status' => 200
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get recent history: " . $e->getMessage(), [
                'from_sku' => $request->from_sku ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error fetching history',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search transfer history with optional filters (from_sku, to_sku, date_from, date_to, transferred_by).
     */
    public function searchHistory(Request $request)
    {
        $query = StockBalance::query();

        if ($request->filled('from_sku')) {
            $query->where('from_sku', 'like', '%' . trim($request->from_sku) . '%');
        }
        if ($request->filled('to_sku')) {
            $query->where('to_sku', 'like', '%' . trim($request->to_sku) . '%');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('transferred_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transferred_at', '<=', $request->date_to);
        }
        if ($request->filled('transferred_by')) {
            $query->where('transferred_by', 'like', '%' . trim($request->transferred_by) . '%');
        }

        $data = $query->orderBy('transferred_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'from_parent_name'    => $item->from_parent_name,
                    'from_sku'            => $item->from_sku,
                    'from_dil_percent'    => $item->from_dil_percent,
                    'from_available_qty'  => $item->from_available_qty,
                    'from_adjust_qty'     => $item->from_adjust_qty,
                    'to_parent_name'      => $item->to_parent_name,
                    'to_sku'              => $item->to_sku,
                    'to_dil_percent'      => $item->to_dil_percent,
                    'to_available_qty'    => $item->to_available_qty,
                    'to_adjust_qty'       => $item->to_adjust_qty,
                    'transferred_by'      => $item->transferred_by,
                    'transferred_at'      => $item->transferred_at
                        ? Carbon::parse($item->transferred_at)->timezone('America/New_York')->format('m-d-Y H:i')
                        : '',
                ];
            });

        return response()->json(['data' => $data]);
    }

    /**
     * Get transfer preferences (FROM SKU, ratio) keyed by to_sku.
     * Latest save from any user wins so the table stays shared.
     */
    public function getTransferPreferences()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['preferences' => []]);
        }
        $rows = StockBalanceTransferPreference::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
        $preferences = [];
        foreach ($rows as $row) {
            if (isset($preferences[$row->to_sku])) {
                continue;
            }
            $preferences[$row->to_sku] = [
                'fromSku' => $row->from_sku,
                'ratio' => $row->ratio ?? '1:1',
            ];
        }
        return response()->json(['preferences' => $preferences]);
    }

    /**
     * Save transfer preference for one to_sku (FROM SKU and ratio).
     * Any user's save is visible to everyone (latest updated_at wins).
     */
    public function saveTransferPreference(Request $request)
    {
        $request->validate([
            'to_sku' => 'required|string',
            'from_sku' => 'nullable|string',
            'ratio' => 'nullable|string|max:20',
        ]);
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        StockBalanceTransferPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'to_sku' => trim($request->to_sku),
            ],
            [
                'from_sku' => $request->filled('from_sku') ? trim($request->from_sku) : null,
                'ratio' => $request->input('ratio', '1:1'),
            ]
        );
        return response()->json(['success' => true]);
    }
}
