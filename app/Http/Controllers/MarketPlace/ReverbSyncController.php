<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ReverbOrderMetric;
use App\Models\ReverbProduct;
use App\Models\ReverbSyncSettings;
use App\Services\ShopifyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReverbSyncController extends Controller
{
    public function __construct(
        protected ShopifyApiService $shopifyApi
    ) {}

    /**
     * Products: Reverb listed products with state tabs, search by name/SKU, and Shopify enrichment.
     */
    public function syncProducts(Request $request): View
    {
        Log::info('=== Reverb Sync Started ===', [
            'search_name' => $request->input('search_name'),
            'search_sku' => $request->input('search_sku'),
            'state_tab' => $request->input('state', 'all'),
        ]);

        try {
            // Database connection check
            try {
                DB::connection()->getPdo();
                Log::info('Database connected: ' . DB::connection()->getDatabaseName());
            } catch (\Exception $e) {
                Log::error('Database connection failed: ' . $e->getMessage());
                throw $e;
            }

            // Table existence
            $tables = ['reverb_products', 'reverb_order_metrics', 'reverb_sync_settings'];
            foreach ($tables as $table) {
                $exists = Schema::hasTable($table);
                Log::info("Table {$table} exists: " . ($exists ? 'YES' : 'NO'));
            }
            if (! Schema::hasTable('reverb_products')) {
                throw new \RuntimeException('Table reverb_products does not exist. Run: php artisan migrate');
            }

            // Column existence (defensive: avoid 500 if migrations not run on server)
            $columns = Schema::getColumnListing('reverb_products');
            Log::info('reverb_products columns: ' . implode(', ', $columns));
            $hasListingState = in_array('listing_state', $columns);
            $hasProductTitle = in_array('product_title', $columns);
            $hasReverbListingId = in_array('reverb_listing_id', $columns);
            Log::info('Column listing_state: ' . ($hasListingState ? 'YES' : 'NO'));
            Log::info('Column product_title: ' . ($hasProductTitle ? 'YES' : 'NO'));
            Log::info('Column reverb_listing_id: ' . ($hasReverbListingId ? 'YES' : 'NO'));

            $searchName = $request->input('search_name');
            $searchSku = $request->input('search_sku');
            $stateTab = $request->input('state', 'all');

            $baseQuery = ReverbProduct::query()
                ->whereNotNull('sku')
                ->where('sku', 'not like', '%Parent%');

            Log::info('Base query count: ' . $baseQuery->count());

            // Tab filter (only if listing_state column exists)
            if ($hasListingState) {
                if ($stateTab === 'drafts') {
                    $baseQuery->where('listing_state', 'draft');
                } elseif ($stateTab === 'active') {
                    $baseQuery->whereIn('listing_state', ['live', 'active']);
                } elseif ($stateTab === 'ended') {
                    $baseQuery->where('listing_state', 'ended');
                } elseif ($stateTab === 'sold') {
                    $baseQuery->where('listing_state', 'sold');
                }
            }

            Log::info('After tab filter count: ' . $baseQuery->count());

            if ($searchSku !== null && $searchSku !== '') {
                $baseQuery->where('sku', 'like', '%' . trim($searchSku) . '%');
            }
            if ($searchName !== null && $searchName !== '') {
                if ($hasProductTitle) {
                    $baseQuery->where(function ($q) use ($searchName) {
                        $q->where('product_title', 'like', '%' . trim($searchName) . '%')
                            ->orWhere('sku', 'like', '%' . trim($searchName) . '%');
                    });
                } else {
                    $baseQuery->where('sku', 'like', '%' . trim($searchName) . '%');
                }
            }

            Log::info('After search filter count: ' . $baseQuery->count());

            $products = $baseQuery->orderBy('sku')->paginate(50)->withQueryString();
            Log::info('Products pagination: ', ['total' => $products->total(), 'per_page' => $products->perPage()]);

            // Counts for tabs (only if listing_state exists)
            $countBase = ReverbProduct::query()
                ->whereNotNull('sku')
                ->where('sku', 'not like', '%Parent%');
            if ($hasListingState) {
                $counts = [
                    'all' => (clone $countBase)->count(),
                    'drafts' => (clone $countBase)->where('listing_state', 'draft')->count(),
                    'active' => (clone $countBase)->whereIn('listing_state', ['live', 'active'])->count(),
                    'ended' => (clone $countBase)->where('listing_state', 'ended')->count(),
                    'sold' => (clone $countBase)->where('listing_state', 'sold')->count(),
                ];
            } else {
                $allCount = (clone $countBase)->count();
                $counts = [
                    'all' => $allCount,
                    'drafts' => 0,
                    'active' => $allCount,
                    'ended' => 0,
                    'sold' => 0,
                ];
            }

            $skus = $products->pluck('sku')->filter()->values()->all();
            Log::info('Fetching Shopify details for SKUs: ' . count($skus));
            $shopifyDetails = $skus ? $this->shopifyApi->getProductDetailsBySkuMap($skus) : [];

            $enriched = $products->getCollection()->map(function ($p) use ($shopifyDetails, $hasProductTitle) {
                $d = $shopifyDetails[$p->sku] ?? null;
                $title = $d['title'] ?? ($hasProductTitle ? ($p->product_title ?? $p->sku) : $p->sku);
                return (object) [
                    'sku' => $p->sku,
                    'reverb_listing_id' => $p->reverb_listing_id ?? null,
                    'image_src' => $d['image_src'] ?? null,
                    'title' => $title,
                    'description' => $d['description'] ?? '',
                    'upc' => $d['upc'] ?? '',
                    'quantity' => (int) ($p->remaining_inventory ?? 0),
                    'price' => $p->price,
                    'brand' => $d['brand'] ?? '',
                    'model' => $d['model'] ?? '',
                    'status_has_link' => ! empty($p->reverb_listing_id),
                    'status_has_alert' => empty($p->reverb_listing_id) || (int) ($p->remaining_inventory ?? 0) <= 0,
                ];
            });
            $products->setCollection($enriched);

            // Cache product_title from Shopify (only if column exists)
            if ($hasProductTitle) {
                foreach ($shopifyDetails as $sku => $d) {
                    if (isset($d['title']) && $d['title'] !== '') {
                        ReverbProduct::where('sku', $sku)->update(['product_title' => $d['title']]);
                    }
                }
            }

            Log::info('=== Reverb Sync Completed Successfully ===');
            return view('marketplace.reverb.products', [
                'products' => $products,
                'title' => 'Reverb - Products (Listed)',
                'counts' => $counts,
                'stateTab' => $stateTab,
                'searchName' => $searchName,
                'searchSku' => $searchSku,
            ]);
        } catch (\Exception $e) {
            Log::error('!!! EXCEPTION IN syncProducts !!!', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Orders: all Reverb orders with option to push to Shopify.
     */
    public function syncOrders(Request $request): View
    {
        $orders = ReverbOrderMetric::query()
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(50);

        return view('marketplace.reverb.orders', [
            'orders' => $orders,
            'title' => 'Reverb - Orders',
        ]);
    }

    /**
     * Settings page matching the Reverb ↔ Shopify sync UI.
     */
    public function syncSettings(Request $request): View
    {
        $settings = ReverbSyncSettings::getForReverb();
        $locations = $this->getShopifyLocations();

        return view('marketplace.reverb.settings', [
            'settings' => $settings,
            'locations' => $locations,
            'title' => 'Reverb - Settings',
        ]);
    }

    /**
     * Save settings (POST).
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'pricing' => 'sometimes|array',
            'pricing.price_sync' => 'sometimes',
            'pricing.use_sale_price' => 'sometimes',
            'pricing.currency_conversion' => 'sometimes',
            'inventory' => 'sometimes|array',
            'inventory.inventory_sync' => 'sometimes',
            'inventory.keep_listing_active' => 'sometimes',
            'inventory.auto_relist' => 'sometimes',
            'inventory.quantity_calc_percent' => 'sometimes|numeric',
            'inventory.max_quantity' => 'sometimes|integer',
            'inventory.min_quantity' => 'sometimes|integer',
            'inventory.out_of_stock_threshold' => 'sometimes|integer',
            'inventory.shopify_location_ids' => 'sometimes|array',
            'order' => 'sometimes|array',
            'order.skip_shipped_orders' => 'sometimes',
            'order.import_orders_to_main_store' => 'sometimes',
            'order.import_orders_with_unlisted_products' => 'sometimes',
            'order.keep_order_number_from_channel' => 'sometimes',
            'order.tax_rules' => 'sometimes|string',
            'order.import_orders_without_tax' => 'sometimes',
            'order.order_receipt_email' => 'sometimes',
            'order.fulfillment_receipt_email' => 'sometimes',
            'order.marketing_emails' => 'sometimes',
            'order.import_sales_with_by' => 'sometimes|string',
            'order.sort_order' => 'sometimes|array',
        ]);

        $current = ReverbSyncSettings::getForReverb();
        $merged = $this->normalizeAndMergeSettings($current, $request->only(['pricing', 'inventory', 'order']));
        ReverbSyncSettings::setForReverb($merged);

        return response()->json(['success' => true, 'message' => 'Settings saved.']);
    }

    protected function normalizeAndMergeSettings(array $current, array $input): array
    {
        $boolKeys = [
            'pricing' => ['price_sync', 'use_sale_price', 'currency_conversion'],
            'inventory' => ['inventory_sync', 'keep_listing_active', 'auto_relist'],
            'order' => ['skip_shipped_orders', 'import_orders_to_main_store', 'import_orders_with_unlisted_products', 'keep_order_number_from_channel', 'import_orders_without_tax', 'order_receipt_email', 'fulfillment_receipt_email', 'marketing_emails'],
        ];
        foreach (['pricing', 'inventory', 'order'] as $section) {
            if (!isset($input[$section]) || !is_array($input[$section])) {
                continue;
            }
            foreach ($input[$section] as $key => $value) {
                if (in_array($key, $boolKeys[$section] ?? [], true)) {
                    $current[$section][$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } else {
                    $current[$section][$key] = $value;
                }
            }
        }
        if (!empty($input['order']['sort_order_1'])) {
            $current['order']['sort_order'] = [$input['order']['sort_order_1'], $input['order']['sort_order_2'] ?? 'lowest'];
        }
        return $current;
    }

    /**
     * Push a single Reverb order to Shopify with tags.
     */
    public function pushOrderToShopify(Request $request): JsonResponse
    {
        $id = $request->validate(['id' => 'required|integer'])['id'];
        $order = ReverbOrderMetric::findOrFail($id);

        if ($order->shopify_order_id) {
            return response()->json([
                'success' => false,
                'message' => 'Order already pushed to Shopify.',
            ], 422);
        }

        $tags = array_filter([
            'reverb',
            'reverb-' . ($order->order_number ?? $order->id),
        ]);
        $settings = ReverbSyncSettings::getForReverb();
        $orderTags = $settings['order']['shopify_order_tags'] ?? ['reverb'];
        $tags = array_values(array_unique(array_merge($tags, $orderTags)));

        try {
            $shopifyOrderId = $this->createShopifyOrderFromReverb($order, $tags);
            $order->update([
                'shopify_order_id' => (string) $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Order pushed to Shopify.',
                'shopify_order_id' => $shopifyOrderId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Reverb push to Shopify failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function createShopifyOrderFromReverb(ReverbOrderMetric $order, array $tags): string
    {
        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');

        // Resolve variant ID by SKU (from Shopify product/variant)
        $variantId = $this->findShopifyVariantIdBySku($order->sku);
        if (!$variantId) {
            throw new \RuntimeException("SKU \"{$order->sku}\" not found in Shopify. Add the product to Shopify first.");
        }

        $payload = [
            'order' => [
                'line_items' => [
                    [
                        'variant_id' => $variantId,
                        'quantity' => (int) ($order->quantity ?: 1),
                    ],
                ],
                'tags' => implode(', ', $tags),
                'note' => 'Imported from Reverb. Order #' . ($order->order_number ?? $order->id),
                'source_name' => 'reverb',
            ],
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->post("https://{$storeUrl}/admin/api/2024-01/orders.json", $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('Shopify API error: ' . $response->body());
        }

        $data = $response->json();
        return (string) ($data['order']['id'] ?? '');
    }

    protected function findShopifyVariantIdBySku(?string $sku): ?string
    {
        if (!$sku) {
            return null;
        }
        $sku = trim($sku);
        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');
        $url = "https://{$storeUrl}/admin/api/2024-01/products.json";
        $pageInfo = null;
        for ($i = 0; $i < 20; $i++) {
            $query = ['limit' => 250, 'fields' => 'id,variants'];
            if ($pageInfo) {
                $query['page_info'] = $pageInfo;
            }
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->get($url, $query);
            if (!$response->successful()) {
                return null;
            }
            $products = $response->json()['products'] ?? [];
            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $v) {
                    if (isset($v['sku']) && trim((string) $v['sku']) === $sku) {
                        return (string) $v['id'];
                    }
                }
            }
            $link = $response->header('Link');
            if (!$link || strpos($link, 'rel="next"') === false) {
                break;
            }
            if (preg_match('/<[^>]+page_info=([^&>]+)[^>]*>;\s*rel="next"/', $link, $m)) {
                $pageInfo = $m[1];
            } else {
                break;
            }
        }
        return null;
    }

    protected function getShopifyLocations(): array
    {
        $storeUrl = str_replace(['https://', 'http://'], '', config('services.shopify.store_url'));
        $token = config('services.shopify.password') ?: env('SHOPIFY_PASSWORD');
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->get("https://{$storeUrl}/admin/api/2024-01/locations.json", ['limit' => 100]);
        if (!$response->successful()) {
            return [];
        }
        $locations = $response->json()['locations'] ?? [];
        return array_map(fn ($l) => ['id' => $l['id'], 'name' => $l['name'] ?? 'Location ' . $l['id']], $locations);
    }
}
