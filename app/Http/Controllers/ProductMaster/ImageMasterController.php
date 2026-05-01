<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Models\ProductImage;
use App\Models\ProductMaster;
use App\Services\AmazonSpApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\MacysApiService;
use App\Services\ReverbApiService;
use App\Services\ShopifyApiService;
use App\Services\ShopifyPLSApiService;
use App\Services\Support\EbaySellInventoryListingResolver;
use App\Services\Support\EbayTradingReviseItem;
use Illuminate\Support\Facades\Storage;
use App\Services\TemuApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImageMasterController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('image-master', compact('mode', 'demo'));
    }

    /**
     * Product rows from Product Master + per-marketplace image_master_json (push state / last URLs).
     */
    public function getData(Request $request)
    {
        try {
            $baseResponse = app(PMController::class)->getViewProductData($request);
            $baseData = $baseResponse->getData(true);
            $products = $baseData['data'] ?? [];

            $marketTables = $this->marketplaceTableMap();
            $metricsByMarketplace = [];
            foreach ($marketTables as $marketplace => $table) {
                $metricsByMarketplace[$marketplace] = $this->loadImageMetricsBySku($table);
            }

            foreach ($products as &$row) {
                $sku = $this->normalizeSku($row['SKU'] ?? null);
                $im = [];
                foreach (array_keys($marketTables) as $mp) {
                    $im[$mp] = $metricsByMarketplace[$mp][$sku] ?? '';
                }
                $row['image_master'] = $im;
                $row['preview_thumb'] = $this->firstPreviewUrl($row);
            }

            return response()->json([
                'message' => 'Data loaded from database',
                'data' => $products,
                'status' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImageMaster getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load image master data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * Amazon listing images via Listings Items API (same media path as catalog enrichment).
     */
    public function getAmazonImages(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
        ]);
        $sku = $this->normalizeSku($validated['sku']);

        try {
            $service = app(AmazonSpApiService::class);
            $res = $service->getListingsItemMedia($sku);

            return response()->json([
                'success' => (bool) ($res['success'] ?? false),
                'images' => $res['images'] ?? [],
                'videos' => $res['videos'] ?? [],
                'message' => $res['message'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'images' => [],
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * eBay gallery URLs from GetItem (Trading API). account: ebay | ebay2 | ebay3
     */
    public function getEbayImages(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'account' => 'nullable|string|in:ebay,ebay2,ebay3',
        ]);
        $sku = $this->normalizeSku($validated['sku']);
        $account = $validated['account'] ?? 'ebay';

        $tableMap = [
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
        ];
        $serviceMap = [
            'ebay' => EbayApiService::class,
            'ebay2' => Ebay2ApiService::class,
            'ebay3' => EbayThreeApiService::class,
        ];

        $table = $tableMap[$account];
        if (! Schema::hasTable($table)) {
            return response()->json(['success' => false, 'images' => [], 'message' => 'Metrics table missing.'], 422);
        }

        $row = DB::table($table)->where(function ($q) use ($sku) {
            $q->where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku));
        })->first();
        if (! $row && Schema::hasColumn($table, 'item_id')) {
            $row = DB::table($table)->where('item_id', $sku)->first();
        }
        $itemId = ($row && ! empty($row->item_id)) ? trim((string) $row->item_id) : null;

        try {
            $svc = app($serviceMap[$account]);
            if (! $itemId) {
                $token = $svc->generateBearerToken();
                $itemId = EbaySellInventoryListingResolver::resolveWithTradingFallback(
                    $token,
                    $svc->getTradingEndpoint(),
                    $svc->getTradingHeadersForResolver(),
                    $sku
                );
            }
            if (! $itemId) {
                return response()->json([
                    'success' => false,
                    'images' => [],
                    'message' => 'No eBay listing found for this SKU (metrics item_id empty and Inventory/GetSellerList lookup failed).',
                ], 422);
            }

            $getItem = $svc->getItem((string) $itemId);
            if (! $getItem) {
                return response()->json(['success' => false, 'images' => [], 'message' => 'GetItem failed.'], 502);
            }
            $urls = EbayTradingReviseItem::extractPictureUrlsFromGetItem($getItem);

            return response()->json([
                'success' => true,
                'images' => $urls,
                'item_id' => (string) $itemId,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'images' => [], 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Push ordered image URLs to marketplace and persist image_master_json on success (or local-only for unsupported APIs).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pushToMarketplace(Request $request)
    {
        // Remove PHP execution time limit and keep running even if browser disconnects.
        // Critical: without ignore_user_abort the DELETE loop gets killed mid-way when the
        // browser's AbortController fires, leaving old Shopify images un-deleted.
        set_time_limit(0);
        ignore_user_abort(true);

        $validated = $request->validate([
            'sku'                    => 'required|string|max:255',
            'updates'                => 'required|array|min:1',
            'updates.*.marketplace'  => 'required|string',
            'updates.*.images'       => 'required|array',          // allow empty for "clear all"
            'updates.*.images.*'     => 'nullable|string|max:2048',
            'mode'                   => 'nullable|string|in:replace,add',
        ]);

        $sku     = $this->normalizeSku($validated['sku']);
        $mode    = $validated['mode'] ?? 'replace';   // 'replace' | 'add'
        $allowed = array_keys($this->marketplaceTableMap());
        $results = [];

        foreach ($validated['updates'] as $u) {
            $mp     = strtolower(trim($u['marketplace']));
            // Preserve original order — do NOT sort, trim only
            $images = array_values(array_filter(array_map('trim', $u['images'] ?? []), fn ($s) => $s !== ''));
            $images = array_slice($images, 0, 20);

            if (! in_array($mp, $allowed, true)) {
                $results[$mp] = ['success' => false, 'message' => 'Unknown marketplace'];
                continue;
            }

            // Empty images + add mode = nothing to do
            if ($images === [] && $mode !== 'replace') {
                $results[$mp] = ['success' => true, 'message' => 'No images to add; skipped.'];
                continue;
            }

            $remote   = $this->pushImagesToRemote($mp, $sku, $images, $mode);
            $remoteOk = (bool) ($remote['success'] ?? false);

            $urlsForMetrics = $images;
            if ($remoteOk && ! empty($remote['normalized_urls']) && is_array($remote['normalized_urls'])) {
                $urlsForMetrics = array_values($remote['normalized_urls']);
            } elseif ($remoteOk && $images !== []) {
                // eBay, Amazon, etc. return no normalized_urls — rewrite localhost /storage/ to APP_URL/ASSET_URL for metrics
                $urlsForMetrics = $this->normalizeStorageUrlsForImageMasterMetrics($images);
            }

            // Only persist metrics when we have actual image URLs
            $saved = false;
            if ($remoteOk && $images !== []) {
                $saved = $this->saveImageMetricsToTable($mp, $sku, $urlsForMetrics);
                if (in_array($mp, ['shopify_main', 'shopify_pls'], true)) {
                    $saved = $this->saveShopifyCatalogImages($sku, $mp, $urlsForMetrics) || $saved;
                }
            }

            $results[$mp] = [
                'success'        => $remoteOk,
                'metrics_saved'  => $saved,
                'message'        => ($remote['message'] ?? '').($saved ? '' : ($images !== [] ? ' Metrics not saved.' : '')),
            ];
        }

        $totalSuccess = collect($results)->where('success', true)->count();
        $totalFailed = collect($results)->where('success', false)->count();
        $totalMetricsFailed = collect($results)->where('metrics_saved', false)->count();

        return response()->json([
            'success' => $totalFailed === 0,
            'results' => $results,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'total_metrics_failed' => $totalMetricsFailed,
            'message' => "Updated {$totalSuccess} marketplace(s).".($totalFailed > 0 ? " {$totalFailed} failed." : '').($totalMetricsFailed > 0 ? " {$totalMetricsFailed} metrics save failed." : ''),
        ]);
    }

    /**
     * Save ordered URLs to Product Master image1–image12 and main_image.
     */
    public function saveProductMasterImages(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'images' => 'required|array|max:12',
            'images.*' => 'nullable|string|max:2048',
        ]);
        $sku = $this->normalizeSku($validated['sku']);
        $images = array_values(array_slice($validated['images'], 0, 12));

        $product = ProductMaster::query()->where('sku', $sku)->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        for ($i = 0; $i < 12; $i++) {
            $col = 'image'.($i + 1);
            $product->{$col} = $images[$i] ?? null;
        }
        $product->main_image = $images[0] ?? $product->main_image;

        try {
            $product->save();

            return response()->json(['success' => true, 'message' => 'Product Master images saved.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload files to public disk under products/{sku}/, persist to product_images, return URLs.
     */
    public function uploadImages(Request $request)
    {
        $validated = $request->validate([
            'sku'    => 'required|string|max:255',
            'files'  => 'required|array|min:1|max:12',
            'files.*' => 'file|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $sku     = $this->normalizeSku($validated['sku']);
        $safeSku = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', $sku);
        $folder  = "products/{$safeSku}";

        $urls    = [];
        $records = [];
        $now     = now();

        foreach ($request->file('files', []) as $file) {
            if (! $file) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $extension    = strtolower($file->getClientOriginalExtension());
            $baseName     = pathinfo($originalName, PATHINFO_FILENAME);
            $uniqueName   = $baseName.'_'.uniqid().'.'.$extension;

            $path = $file->storeAs($folder, $uniqueName, 'public');

            $record = ProductImage::create([
                'sku'           => $sku,
                'image_path'    => $path,
                'original_name' => $originalName,
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getClientMimeType(),
                'created_at'    => $now,
            ]);

            $url     = asset('storage/'.$path);
            $urls[]  = $url;
            $records[] = [
                'id'    => $record->id,
                'url'   => $url,
                'name'  => $originalName,
            ];
        }

        return response()->json([
            'success' => true,
            'urls'    => $urls,
            'images'  => $records,
        ]);
    }

    /**
     * Return all locally stored images for a SKU (from product_images table).
     */
    public function getSkuImages(Request $request)
    {
        $sku = $this->normalizeSku($request->get('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'images' => []]);
        }

        $images = ProductImage::where('sku', $sku)
            ->orderBy('id')
            ->get()
            ->map(fn (ProductImage $img) => [
                'id'   => $img->id,
                'url'  => asset('storage/'.$img->image_path),
                'name' => $img->original_name ?? basename($img->image_path),
                'path' => $img->image_path,
            ])
            ->values();

        return response()->json(['success' => true, 'images' => $images]);
    }

    /**
     * Delete a stored SKU image from DB and disk.
     */
    public function deleteSkuImage(Request $request, int $id)
    {
        $image = ProductImage::find($id);
        if (! $image) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function pushImagesToRemote(string $marketplace, string $sku, array $imageUrls, string $mode = 'replace'): array
    {
        try {
            switch ($marketplace) {
                case 'ebay':
                    return app(EbayApiService::class)->updateListingImages($sku, $imageUrls);
                case 'ebay2':
                    return app(Ebay2ApiService::class)->updateListingImages($sku, $imageUrls);
                case 'ebay3':
                    return app(EbayThreeApiService::class)->updateListingImages($sku, $imageUrls);
                case 'amazon':
                    return app(AmazonSpApiService::class)->updateImages($sku, $imageUrls);
                case 'temu':
                    return app(TemuApiService::class)->updateImages($sku, $imageUrls);
                case 'shopify_main':
                    return app(ShopifyApiService::class)->updateImages($sku, $imageUrls, $mode);
                case 'shopify_pls':
                    return app(ShopifyPLSApiService::class)->updateImages($sku, $imageUrls, $mode);
                case 'macy':
                    return app(MacysApiService::class)->updateImages($sku, $imageUrls);
                case 'reverb':
                    return app(ReverbApiService::class)->updateImages($sku, $imageUrls, $mode);
                default:
                    return [
                        'success' => false,
                        'message' => 'Image push is not implemented for '.$marketplace.' yet.',
                    ];
            }
        } catch (\Throwable $e) {
            Log::warning('ImageMaster pushImagesToRemote failed', ['mp' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function saveImageMetricsToTable(string $marketplace, string $sku, array $imageUrls): bool
    {
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if (! $table || ! Schema::hasTable($table)) {
            return false;
        }
        if (! Schema::hasColumn($table, 'sku')) {
            return false;
        }

        $payload = json_encode(array_values($imageUrls), JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return false;
        }

        try {
            $now = now();
            $updatable = [];
            if (Schema::hasColumn($table, 'image_master_json')) {
                $updatable['image_master_json'] = $payload;
            }
            if (Schema::hasColumn($table, 'image_urls')) {
                $updatable['image_urls'] = $payload;
            }
            if ($updatable === []) {
                return false;
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $updatable['updated_at'] = $now;
            }

            DB::table($table)->updateOrInsert(
                ['sku' => $sku],
                $updatable
            );

            // Ensure created_at on first insert when column exists and was null
            if (Schema::hasColumn($table, 'created_at')) {
                DB::table($table)->where('sku', $sku)->whereNull('created_at')->update(['created_at' => $now]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning("ImageMaster: could not save {$table}", ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Marketplace key => metrics table name (same pattern as Description / Bullet masters).
     */
    private function marketplaceTableMap(): array
    {
        return [
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
            'amazon' => 'amazon_metrics',
            'temu' => 'temu_metrics',
            'macy' => 'macy_metrics',
            'reverb' => 'reverb_products', // reverb_metrics may not exist; reverb_products has image_urls + unique sku
            'shopify_main' => 'shopify_metrics',
            'shopify_pls' => 'shopify_pls_metrics',
        ];
    }

    /**
     * @return array<string, string> sku => image_master_json raw or ''
     */
    private function loadImageMetricsBySku(string $table): array
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return [];
            }
            $hasMasterJson = Schema::hasColumn($table, 'image_master_json');
            $hasImageUrls = Schema::hasColumn($table, 'image_urls');
            if (! $hasMasterJson && ! $hasImageUrls) {
                return [];
            }
            $valueColumn = $hasMasterJson ? 'image_master_json' : 'image_urls';

            return DB::table($table)
                ->select('sku', $valueColumn)
                ->whereNotNull('sku')
                ->get()
                ->mapWithKeys(function ($row) use ($valueColumn) {
                    $raw = (string) ($row->{$valueColumn} ?? '');
                    $trim = trim($raw);

                    return [$this->normalizeSku($row->sku) => $trim];
                })
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning("ImageMaster: load metrics failed for {$table}", ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Load the last-pushed image URLs for a marketplace/sku from the metrics table.
     * Used in "add" mode to append new images to whatever is already on the marketplace.
     *
     * @return list<string>
     */
    private function loadExistingMarketplaceImages(string $marketplace, string $sku): array
    {
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if (! $table || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
            return [];
        }

        $hasMasterJson = Schema::hasColumn($table, 'image_master_json');
        $hasImageUrls  = Schema::hasColumn($table, 'image_urls');
        if (! $hasMasterJson && ! $hasImageUrls) {
            return [];
        }

        $col = $hasMasterJson ? 'image_master_json' : 'image_urls';
        $row = DB::table($table)->where('sku', $sku)->first();
        if (! $row) {
            return [];
        }

        $json    = trim((string) ($row->{$col} ?? ''));
        $decoded = $json !== '' ? json_decode($json, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $decoded), fn ($s) => $s !== ''));
    }

    private function normalizeSku(?string $sku): string
    {
        if (! $sku) {
            return '';
        }

        return str_replace("\u{00a0}", ' ', trim((string) $sku));
    }

    /**
     * Rebuild /storage/… URLs using APP_URL / ASSET_URL so metrics JSON stores publicly reachable
     * links (marketplaces and teammates see the same URLs as Reverb/Shopify ingest).
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function normalizeStorageUrlsForImageMasterMetrics(array $urls): array
    {
        $base = rtrim((string) (config('app.asset_url') ?: config('app.url')), '/');
        $out  = [];
        foreach ($urls as $u) {
            $u = trim((string) $u);
            if ($u === '') {
                continue;
            }
            $path = parse_url($u, PHP_URL_PATH);
            if (! is_string($path) || ! preg_match('#/storage/(.+)$#', $path, $m)) {
                $out[] = $u;

                continue;
            }
            $rel      = str_replace('\\', '/', rawurldecode($m[1]));
            $rel      = ltrim($rel, '/');
            $segments = array_values(array_filter(explode('/', $rel), fn ($s) => $s !== ''));
            if ($segments === []) {
                $out[] = $u;

                continue;
            }
            $out[] = $base.'/storage/'.implode('/', array_map('rawurlencode', $segments));
        }

        return array_values($out);
    }

    /**
     * First displayable image URL for table preview column.
     *
     * @param  array<string, mixed>  $row
     */
    private function firstPreviewUrl(array $row): ?string
    {
        foreach (['image_path', 'main_image', 'image1', 'image2', 'image3'] as $k) {
            $v = $row[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $v = trim($v);
                if (str_starts_with($v, 'http') || str_starts_with($v, '//')) {
                    return $v;
                }

                return '/'.ltrim($v, '/');
            }
        }

        return null;
    }

    /**
     * Save image URLs to shopify_catalog_products when available.
     */
    private function saveShopifyCatalogImages(string $sku, string $marketplace, array $imageUrls): bool
    {
        try {
            if (! Schema::hasTable('shopify_catalog_products') || ! Schema::hasTable('shopify_catalog_variants')) {
                return false;
            }
            if (! Schema::hasColumn('shopify_catalog_products', 'images') || ! Schema::hasColumn('shopify_catalog_products', 'image_src')) {
                return false;
            }

            $store = $marketplace === 'shopify_pls' ? 'pls' : 'main';
            $payload = json_encode(array_values($imageUrls), JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }
            $first = (string) ($imageUrls[0] ?? '');
            if ($first === '') {
                return false;
            }

            $productId = DB::table('shopify_catalog_variants')
                ->where('store', $store)
                ->where(function ($q) use ($sku) {
                    $q->where('sku', $sku)
                        ->orWhere('sku', strtoupper($sku))
                        ->orWhere('sku', strtolower($sku));
                })
                ->value('shopify_catalog_product_id');
            if (! $productId) {
                return false;
            }

            $update = [
                'image_src' => $first,
                'images' => $payload,
            ];
            if (Schema::hasColumn('shopify_catalog_products', 'updated_at')) {
                $update['updated_at'] = now();
            }

            return DB::table('shopify_catalog_products')
                ->where('id', $productId)
                ->update($update) > 0;
        } catch (\Throwable $e) {
            Log::warning('ImageMaster: failed saving shopify_catalog_products images', ['sku' => $sku, 'marketplace' => $marketplace, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
