<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Jobs\RunShopifyImagePullJob;
use App\Models\ProductImage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ShopifyVariant;
use App\Services\AmazonSpApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\BestBuyApiService;
use App\Services\MacysApiService;
use App\Services\ReverbApiService;
use App\Services\ShopifyApiService;
use App\Services\ShopifyPLSApiService;
use App\Services\ShopifyPlsTokenService;
use App\Services\Support\ShopifyImagePullJobStore;
use App\Services\Support\EbaySellInventoryListingResolver;
use App\Services\Support\EbayTradingReviseItem;
use App\Services\WayfairApiService;
use Illuminate\Support\Facades\Storage;
use App\Services\TemuApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImageMasterController extends Controller
{
    private const PM_MAX_IMAGES = 20;

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

            $mainBySku = $this->loadImageMainByMarketplaceForSkus(
                array_map(fn ($row) => $this->normalizeSku($row['SKU'] ?? null), $products)
            );

            foreach ($products as &$row) {
                $sku = $this->normalizeSku($row['SKU'] ?? null);
                $im = [];
                foreach (array_keys($marketTables) as $mp) {
                    $im[$mp] = $metricsByMarketplace[$mp][$sku] ?? '';
                }
                $row['image_master'] = $im;
                $row['image_main_by_marketplace'] = $mainBySku[$sku] ?? [];
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
            'main_by_marketplace'    => 'nullable|array',
            'main_by_marketplace.*'  => 'integer|min:0|max:'.(self::PM_MAX_IMAGES - 1),
            'dry_run'                => 'nullable|boolean',
        ]);

        $sku     = $this->normalizeSku($validated['sku']);
        $mode    = $validated['mode'] ?? 'replace';   // 'replace' | 'add'
        $dryRun  = (bool) ($validated['dry_run'] ?? false);
        $allowed = array_keys($this->marketplaceTableMap());
        $results = [];

        $maxImageCount = 0;
        foreach ($validated['updates'] as $u) {
            $maxImageCount = max($maxImageCount, count(array_values(array_filter(array_map('trim', $u['images'] ?? []), fn ($s) => $s !== ''))));
        }
        $mainMap = $this->resolveMainByMarketplaceForPush(
            $sku,
            $validated['main_by_marketplace'] ?? null,
            $maxImageCount
        );

        foreach ($validated['updates'] as $u) {
            $mp     = strtolower(trim($u['marketplace']));
            // Preserve original order — do NOT sort, trim only
            $images = array_values(array_filter(array_map('trim', $u['images'] ?? []), fn ($s) => $s !== ''));
            $originalCount = count($images);

            if (! in_array($mp, $allowed, true)) {
                $results[$mp] = ['success' => false, 'message' => 'Unknown marketplace'];
                continue;
            }

            // Empty images + add mode = nothing to do
            if ($images === [] && $mode !== 'replace') {
                $results[$mp] = ['success' => true, 'message' => 'No images to add; skipped.'];
                continue;
            }

            // Clear-all is only implemented for Shopify stores
            if ($images === [] && $mode === 'replace' && ! in_array($mp, ['shopify_main', 'shopify_pls'], true)) {
                $results[$mp] = [
                    'success' => false,
                    'message' => 'Clear-all images is only supported for Shopify Main and Shopify PLS.',
                ];
                continue;
            }

            if ($images !== [] && $mode !== 'add') {
                $mainIndex = $this->mainImageIndexFromMap($mainMap, $mp, $originalCount);
                if ($mainIndex > 0) {
                    $images = $this->reorderImagesWithMainFirst($images, $mainIndex);
                }
            }

            $mainNote = '';
            if ($images !== [] && $mode !== 'add') {
                $mainIdx = $this->mainImageIndexFromMap($mainMap, $mp, $originalCount);
                $mainNote = ' Main image: Image '.($mainIdx + 1).'.';
            }

            $limit = $this->marketplaceImageLimit($mp);
            $images = array_slice($images, 0, $limit);
            $truncatedNote = $originalCount > $limit
                ? " Truncated from {$originalCount} to {$limit} image(s) ({$mp} limit)."
                : '';

            $effectiveMode = $mode;
            $addModeNote = '';
            if ($mode === 'add' && ! in_array($mp, $this->marketplacesSupportingAddMode(), true)) {
                $effectiveMode = 'replace';
                $addModeNote = ' Add mode is not supported for this marketplace; used replace instead.';
            }

            // Rewrite localhost/127.0.0.1 storage URLs for marketplaces that fetch by URL.
            // Shopify must keep local storage URLs so its service can upload the file as base64.
            $imagesForPush = in_array($mp, ['shopify_main', 'shopify_pls'], true)
                ? $images
                : $this->rewriteLocalStorageUrlsToPublic($images);

            if ($dryRun) {
                $remote   = $this->dryRunPushToRemote($mp, $sku, $imagesForPush, $effectiveMode);
                $remoteOk = (bool) ($remote['success'] ?? false);
                $dryNote  = ' (dry run — no marketplace write).';
                $message  = trim(($remote['message'] ?? '').$mainNote.$truncatedNote.$addModeNote.$dryNote);
                $results[$mp] = array_merge([
                    'success'       => $remoteOk,
                    'dry_run'       => true,
                    'metrics_saved' => false,
                    'message'       => $message,
                    'images_count'  => count($imagesForPush),
                ], array_diff_key($remote, ['success' => 1, 'message' => 1]));

                continue;
            }

            $remote   = $this->pushImagesToRemote($mp, $sku, $imagesForPush, $effectiveMode);
            $remoteOk = (bool) ($remote['success'] ?? false);

            $urlsForMetrics = $imagesForPush;
            if ($remoteOk && ! empty($remote['normalized_urls']) && is_array($remote['normalized_urls'])) {
                $urlsForMetrics = array_values($remote['normalized_urls']);
            } elseif ($remoteOk && $imagesForPush !== []) {
                $urlsForMetrics = $imagesForPush;
            } elseif ($remoteOk && $imagesForPush === []) {
                $urlsForMetrics = [];
            }

            $saved = false;
            if ($remoteOk) {
                $saved = $this->saveImageMetricsToTable($mp, $sku, $urlsForMetrics);
                if (in_array($mp, ['shopify_main', 'shopify_pls'], true)) {
                    $saved = $this->saveShopifyCatalogImages($sku, $mp, $urlsForMetrics) || $saved;
                }
            }

            $message = trim(($remote['message'] ?? '').$mainNote.$truncatedNote.$addModeNote);
            if ($remoteOk && ! $saved) {
                $message .= ' Metrics not saved.';
            }

            $results[$mp] = [
                'success'        => $remoteOk,
                'metrics_saved'  => $saved,
                'message'        => $message,
            ];
        }

        $totalSuccess = collect($results)->where('success', true)->count();
        $totalFailed = collect($results)->where('success', false)->count();
        $totalMetricsFailed = collect($results)->filter(
            fn ($r) => ($r['success'] ?? false) && ! ($r['metrics_saved'] ?? false)
        )->count();

        return response()->json([
            'success' => $totalFailed === 0,
            'dry_run' => $dryRun,
            'results' => $results,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'total_metrics_failed' => $totalMetricsFailed,
            'message' => "Updated {$totalSuccess} marketplace(s).".($totalFailed > 0 ? " {$totalFailed} failed." : '').($totalMetricsFailed > 0 ? " {$totalMetricsFailed} metrics save failed." : ''),
        ]);
    }

    /**
     * Validate push readiness without calling marketplace write APIs (where supported).
     *
     * @return array<string, mixed>
     */
    private function dryRunPushToRemote(string $marketplace, string $sku, array $imageUrls, string $mode = 'replace'): array
    {
        if ($imageUrls === [] && $mode === 'replace' && in_array($marketplace, ['shopify_main', 'shopify_pls'], true)) {
            return ['success' => true, 'message' => 'Dry run OK: would clear all Shopify images.'];
        }

        if ($imageUrls === []) {
            return ['success' => false, 'message' => 'No images to push.'];
        }

        try {
            switch ($marketplace) {
                case 'amazon':
                    return app(AmazonSpApiService::class)->dryRunUpdateImages($sku, $imageUrls);
                case 'ebay':
                case 'ebay2':
                case 'ebay3':
                    return $this->dryRunEbayPush($marketplace, $sku, $imageUrls);
                default:
                    return $this->dryRunGenericPush($marketplace, $sku, $imageUrls);
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'dry_run' => true];
        }
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, dry_run?: bool}
     */
    private function dryRunGenericPush(string $marketplace, string $sku, array $imageUrls): array
    {
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if ($table && Schema::hasTable($table) && Schema::hasColumn($table, 'sku')) {
            $hasRow = DB::table($table)->where('sku', $sku)->exists();
            if (! $hasRow) {
                return [
                    'success' => false,
                    'message' => "Dry run: no {$marketplace} metrics row for SKU (listing may be missing).",
                    'dry_run' => true,
                ];
            }
        }

        foreach ($imageUrls as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Dry run: invalid image URL (must be http/https).', 'dry_run' => true];
            }
        }

        return [
            'success' => true,
            'dry_run' => true,
            'message' => 'Dry run OK: would push '.count($imageUrls).' image(s) to '.($marketplace).'.',
        ];
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, dry_run?: bool}
     */
    private function dryRunEbayPush(string $marketplace, string $sku, array $imageUrls): array
    {
        $tableMap = [
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
        ];
        $table = $tableMap[$marketplace] ?? null;
        if (! $table || ! Schema::hasTable($table)) {
            return ['success' => false, 'message' => 'Dry run: metrics table missing.', 'dry_run' => true];
        }

        $row = DB::table($table)->where('sku', $sku)->first();
        if (! $row) {
            return ['success' => false, 'message' => 'Dry run: no eBay listing row for SKU.', 'dry_run' => true];
        }

        foreach ($imageUrls as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Dry run: invalid image URL.', 'dry_run' => true];
            }
        }

        return [
            'success' => true,
            'dry_run' => true,
            'message' => 'Dry run OK: would push '.count($imageUrls).' image(s) to '.$marketplace.'.',
        ];
    }

    /**
     * Save ordered URLs to Product Master image1–image20 and main_image.
     */
    public function saveProductMasterImages(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'images' => 'present|array|max:'.self::PM_MAX_IMAGES,
            'images.*' => 'nullable|string|max:2048',
            'main_by_marketplace' => 'nullable|array',
            'main_by_marketplace.*' => 'integer|min:0|max:'.(self::PM_MAX_IMAGES - 1),
        ]);
        $sku = $this->normalizeSku($validated['sku']);
        $images = $this->normalizeStorageUrlsForImageMasterMetrics(
            array_values(array_slice($validated['images'], 0, self::PM_MAX_IMAGES))
        );
        $mainByMarketplace = $this->sanitizeMainByMarketplace(
            $validated['main_by_marketplace'] ?? [],
            count($images)
        );

        $product = ProductMaster::query()->where('sku', $sku)->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        for ($i = 0; $i < self::PM_MAX_IMAGES; $i++) {
            $col = 'image'.($i + 1);
            $product->{$col} = $images[$i] ?? null;
        }
        $product->main_image = $images[0] ?? null;
        if (Schema::hasColumn('product_master', 'image_main_by_marketplace_json')) {
            $product->image_main_by_marketplace_json = $mainByMarketplace === []
                ? null
                : json_encode($mainByMarketplace, JSON_UNESCAPED_SLASHES);
        }

        try {
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Product Master images saved.',
                'image_main_by_marketplace' => $mainByMarketplace,
            ]);
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
            'files'  => 'required|array|min:1|max:'.self::PM_MAX_IMAGES,
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

            $url     = $this->normalizeStorageUrlsForImageMasterMetrics([asset('storage/'.$path)])[0] ?? asset('storage/'.$path);
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
                'url'  => $this->normalizeStorageUrlsForImageMasterMetrics([asset('storage/'.$img->image_path)])[0] ?? asset('storage/'.$img->image_path),
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
                case 'wayfair':
                    return app(WayfairApiService::class)->updateImages($sku, $imageUrls);
                case 'bestbuy':
                    return app(BestBuyApiService::class)->updateImages($sku, $imageUrls);
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

        $isClear = $imageUrls === [];

        try {
            $now = now();
            $updatable = [];
            if ($isClear) {
                if (Schema::hasColumn($table, 'image_master_json')) {
                    $updatable['image_master_json'] = null;
                }
                if (Schema::hasColumn($table, 'image_urls')) {
                    $updatable['image_urls'] = null;
                }
            } else {
                $payload = json_encode(array_values($imageUrls), JSON_UNESCAPED_SLASHES);
                if ($payload === false) {
                    return false;
                }
                if (Schema::hasColumn($table, 'image_master_json')) {
                    $updatable['image_master_json'] = $payload;
                }
                if (Schema::hasColumn($table, 'image_urls')) {
                    $updatable['image_urls'] = $payload;
                }
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
            'wayfair' => 'wayfair_metrics',
            'bestbuy' => 'bestbuy_metrics',
            'macy' => 'macy_metrics',
            'reverb' => 'reverb_products', // reverb_metrics may not exist; reverb_products has image_urls + unique sku
            'shopify_main' => 'shopify_metrics',
            'shopify_pls' => 'shopify_pls_metrics',
        ];
    }

    /**
     * @return list<string>
     */
    private function marketplacesSupportingAddMode(): array
    {
        return ['shopify_main', 'shopify_pls', 'reverb'];
    }

    private function marketplaceImageLimit(string $marketplace): int
    {
        return match ($marketplace) {
            'amazon' => 9,
            'reverb' => 25,
            'shopify_main', 'shopify_pls' => 20,
            default => 12,
        };
    }

    /**
     * @param  list<string|null>  $skus
     * @return array<string, array<string, int>>
     */
    private function loadImageMainByMarketplaceForSkus(array $skus): array
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'image_main_by_marketplace_json')) {
            return [];
        }

        $skus = array_values(array_unique(array_filter(array_map(fn ($s) => $this->normalizeSku($s), $skus))));
        if ($skus === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('product_master')->whereIn('sku', $skus)->get(['sku', 'image_main_by_marketplace_json']) as $row) {
            $sku = $this->normalizeSku($row->sku ?? null);
            if ($sku === '') {
                continue;
            }
            $out[$sku] = $this->decodeImageMainByMarketplaceJson((string) ($row->image_main_by_marketplace_json ?? ''));
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function loadImageMainByMarketplace(string $sku): array
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'image_main_by_marketplace_json')) {
            return [];
        }

        $raw = DB::table('product_master')->where('sku', $sku)->value('image_main_by_marketplace_json');

        return $this->decodeImageMainByMarketplaceJson((string) ($raw ?? ''));
    }

    /**
     * @return array<string, int>
     */
    private function decodeImageMainByMarketplaceJson(string $raw): array
    {
        $trim = trim($raw);
        if ($trim === '' || $trim === '{}') {
            return [];
        }

        $decoded = json_decode($trim, true);
        if (! is_array($decoded)) {
            return [];
        }

        $allowed = array_keys($this->marketplaceTableMap());
        $out = [];
        foreach ($decoded as $mp => $idx) {
            $mp = strtolower(trim((string) $mp));
            if (! in_array($mp, $allowed, true)) {
                continue;
            }
            $out[$mp] = max(0, min(self::PM_MAX_IMAGES - 1, (int) $idx));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, int>  Only non-zero overrides are stored (0 = default first image).
     */
    private function sanitizeMainByMarketplace(array $input, int $imageCount): array
    {
        if ($imageCount <= 0) {
            return [];
        }

        $allowed = array_keys($this->marketplaceTableMap());
        $maxIdx = min(self::PM_MAX_IMAGES - 1, $imageCount - 1);
        $out = [];
        foreach ($input as $mp => $idx) {
            $mp = strtolower(trim((string) $mp));
            if (! in_array($mp, $allowed, true)) {
                continue;
            }
            $idx = max(0, min($maxIdx, (int) $idx));
            if ($idx > 0) {
                $out[$mp] = $idx;
            }
        }

        return $out;
    }

    private function mainImageIndexForMarketplace(string $sku, string $marketplace, int $imageCount): int
    {
        return $this->mainImageIndexFromMap($this->loadImageMainByMarketplace($sku), $marketplace, $imageCount);
    }

    /**
     * @param  array<string, int>  $map
     */
    private function mainImageIndexFromMap(array $map, string $marketplace, int $imageCount): int
    {
        if ($imageCount <= 0) {
            return 0;
        }

        $idx = (int) ($map[$marketplace] ?? 0);

        return max(0, min($imageCount - 1, $idx));
    }

    /**
     * DB settings merged with optional request override (request wins per marketplace).
     *
     * @return array<string, int>
     */
    private function resolveMainByMarketplaceForPush(string $sku, ?array $requestMain, int $imageCount): array
    {
        $fromDb = $this->loadImageMainByMarketplace($sku);
        if ($requestMain === null) {
            return $fromDb;
        }

        $fromRequest = $this->sanitizeMainByMarketplace($requestMain, $imageCount);

        return array_merge($fromDb, $fromRequest);
    }

    /**
     * @param  list<string>  $images
     * @return list<string>
     */
    private function reorderImagesWithMainFirst(array $images, int $mainIndex): array
    {
        $count = count($images);
        if ($count <= 1) {
            return $images;
        }

        $mainIndex = max(0, min($mainIndex, $count - 1));
        if ($mainIndex === 0) {
            return $images;
        }

        $picked = $images[$mainIndex];
        $rest = [];
        foreach ($images as $i => $url) {
            if ($i !== $mainIndex) {
                $rest[] = $url;
            }
        }

        return array_merge([$picked], $rest);
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
                    if ($trim === '' || $trim === '[]') {
                        return [$this->normalizeSku($row->sku) => ''];
                    }
                    $decoded = json_decode($trim, true);
                    if (is_array($decoded) && $decoded === []) {
                        return [$this->normalizeSku($row->sku) => ''];
                    }

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
     * Convert localhost / 127.0.0.1 storage URLs to the publicly accessible URL so that
     * external marketplace APIs (eBay, Amazon, Temu, Macy's, Reverb, etc.) can download
     * the images. Shopify handles local files via base64, but all other APIs need a real URL.
     *
     * Public base URL precedence:
     *   1. REVERB_SKU_IMAGE_PUBLIC_BASE_URL (already set to https://inventory.5coremanagement.com)
     *   2. ASSET_URL
     *   3. APP_URL
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function rewriteLocalStorageUrlsToPublic(array $urls): array
    {
        $publicBase = rtrim((string) (
            config('services.reverb.sku_image_public_base_url') ?:
            config('app.asset_url') ?:
            config('app.url')
        ), '/');

        $appBase = rtrim((string) config('app.url'), '/');

        // Nothing to rewrite when public == local
        if ($publicBase === '' || $publicBase === $appBase) {
            return $urls;
        }

        return array_values(array_map(function (string $u) use ($publicBase, $appBase) {
            // Match both http://localhost/... and http://127.0.0.1:PORT/...
            if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?(/storage/.+)$#i', $u, $m)) {
                return $publicBase . $m[3];
            }
            // Also rewrite APP_URL-based localhost paths
            if ($appBase !== '' && str_starts_with($u, $appBase . '/storage/')) {
                return $publicBase . '/storage/' . substr($u, strlen($appBase) + 9);
            }

            return $u;
        }, $urls));
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
        foreach (['main_image', 'image1', 'image2', 'image3', 'image4', 'image5', 'image6'] as $k) {
            $v = $row[$k] ?? null;
            if (is_string($v) && trim($v) !== '') {
                $v = trim($v);
                if (str_starts_with($v, 'http') || str_starts_with($v, '//')) {
                    return $v;
                }

                return '/'.ltrim($v, '/');
            }
        }

        // Fall back to legacy/Shopify preview when Product Master image slots are empty.
        $fallback = $row['image_path'] ?? null;
        if (is_string($fallback) && trim($fallback) !== '') {
            $fallback = trim($fallback);
            if (str_starts_with($fallback, 'http') || str_starts_with($fallback, '//')) {
                return $fallback;
            }

            return '/'.ltrim($fallback, '/');
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

            if ($imageUrls === []) {
                $update = [
                    'image_src' => null,
                    'images' => null,
                ];
                if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
                    $update['image_urls'] = null;
                }
                if (Schema::hasColumn('shopify_catalog_products', 'image_master_json')) {
                    $update['image_master_json'] = null;
                }
            } else {
                $payload = json_encode(array_values($imageUrls), JSON_UNESCAPED_SLASHES);
                if ($payload === false) {
                    return false;
                }
                $first = (string) ($imageUrls[0] ?? '');
                if ($first === '') {
                    return false;
                }
                $update = [
                    'image_src' => $first,
                    'images' => $payload,
                ];
                if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
                    $update['image_urls'] = $payload;
                }
                if (Schema::hasColumn('shopify_catalog_products', 'image_master_json')) {
                    $update['image_master_json'] = $payload;
                }
            }

            if (Schema::hasColumn('shopify_catalog_products', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('shopify_catalog_products')
                ->where('id', $productId)
                ->update($update);

            return true;
        } catch (\Throwable $e) {
            Log::warning('ImageMaster: failed saving shopify_catalog_products images', ['sku' => $sku, 'marketplace' => $marketplace, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Pull current Shopify product images into Product Master image1–image12 and main_image.
     */
    public function pullShopifyImagesToMaster(Request $request)
    {
        $pullLog = $this->shopifyImagePullLogger();
        $sku = '';
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'dry_run' => 'nullable|boolean',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $dryRun = (bool) ($validated['dry_run'] ?? false);
            $pullLog->info('Shopify image pull started', ['sku' => $sku]);
            $product = $this->findProductMasterBySku($sku);
            if (! $product) {
                $pullLog->warning('Product Master row not found', ['sku' => $sku]);

                return response()->json([
                    'success' => false,
                    'sku' => $sku,
                    'status' => 'product_not_found',
                    'message' => 'Product Master row not found.',
                ], 404);
            }

            $currentImages = $this->productMasterImageArray($product);
            $shopify = $this->fetchShopifyImagesForSku($sku);
            if (! ($shopify['success'] ?? false)) {
                $pullLog->warning('Shopify image fetch failed', [
                    'sku' => $sku,
                    'message' => $shopify['message'] ?? 'Unable to fetch Shopify product.',
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'product_id' => $shopify['product_id'] ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'sku' => $sku,
                    'status' => 'shopify_fetch_failed',
                    'message' => $shopify['message'] ?? 'Unable to fetch Shopify product.',
                    'current_images' => $currentImages,
                ], 422);
            }

            $shopifyImages = array_slice($shopify['images'] ?? [], 0, self::PM_MAX_IMAGES);
            if ($shopifyImages === []) {
                $pullLog->warning('No Shopify images detected', [
                    'sku' => $sku,
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'source' => $shopify['source'] ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'sku' => $sku,
                    'status' => 'no_images_detected',
                    'message' => 'No Shopify product images detected.',
                    'current_images' => $currentImages,
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                ], 422);
            }

            $matchedBefore = $this->normalizedImageArray($currentImages) === $this->normalizedImageArray($shopifyImages);

            if ($dryRun) {
                $pullLog->info('Shopify image pull dry run', [
                    'sku' => $sku,
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'source' => $shopify['source'] ?? null,
                    'matched_before' => $matchedBefore,
                    'before_count' => count($currentImages),
                    'shopify_count' => count($shopifyImages),
                ]);

                return response()->json([
                    'success' => true,
                    'dry_run' => true,
                    'sku' => $sku,
                    'status' => $matchedBefore ? 'already_matched' : 'would_import',
                    'message' => $matchedBefore
                        ? 'Dry run: already matched Shopify images (no change).'
                        : 'Dry run: would import '.count($shopifyImages).' image(s) to Product Master.',
                    'source' => $shopify['source'] ?? 'shopify_admin',
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'before_images' => $currentImages,
                    'shopify_images' => $shopifyImages,
                    'after_images' => $shopifyImages,
                ]);
            }

            for ($i = 1; $i <= self::PM_MAX_IMAGES; $i++) {
                $product->{'image'.$i} = $shopifyImages[$i - 1] ?? null;
            }
            $product->main_image = $shopifyImages[0] ?? null;
            $product->save();

            $newImages = $this->productMasterImageArray($product->fresh());
            Log::info('ImageMaster: pulled Shopify images to Product Master', [
                'sku' => $sku,
                'shopify_product_id' => $shopify['product_id'] ?? null,
                'variant_id' => $shopify['variant_id'] ?? null,
                'source' => $shopify['source'] ?? null,
                'matched_before' => $matchedBefore,
                'image_count' => count($shopifyImages),
            ]);
            $pullLog->info('Shopify images saved to Product Master', [
                'sku' => $sku,
                'shopify_product_id' => $shopify['product_id'] ?? null,
                'variant_id' => $shopify['variant_id'] ?? null,
                'source' => $shopify['source'] ?? null,
                'matched_before' => $matchedBefore,
                'before_count' => count($currentImages),
                'shopify_count' => count($shopifyImages),
                'after_count' => count($newImages),
            ]);

            return response()->json([
                'success' => true,
                'dry_run' => false,
                'sku' => $sku,
                'status' => $matchedBefore ? 'already_matched' : 'imported_to_product_master',
                'message' => $matchedBefore ? 'Already matched Shopify images.' : 'Imported Shopify images to Product Master.',
                'source' => $shopify['source'] ?? 'shopify_admin',
                'shopify_product_id' => $shopify['product_id'] ?? null,
                'variant_id' => $shopify['variant_id'] ?? null,
                'before_images' => $currentImages,
                'shopify_images' => $shopifyImages,
                'after_images' => $newImages,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImageMaster pullShopifyImagesToMaster failed', ['error' => $e->getMessage()]);
            $pullLog->error('Shopify image pull exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function startShopifyPullJob(Request $request, ShopifyImagePullJobStore $store)
    {
        $validated = $request->validate([
            'skus' => 'required|array|min:1',
            'skus.*' => 'required|string',
        ]);

        $current = $store->load();
        if ($store->isActive($current)) {
            return response()->json([
                'success' => false,
                'message' => 'A Shopify image pull is already running or paused.',
                'job' => $current,
            ], 409);
        }

        $job = $store->create($validated['skus'], 6);
        try {
            $this->dispatchShopifyImagePullJob();
        } catch (\Throwable $e) {
            $store->markFailed('Could not queue worker: '.$e->getMessage());
            $this->shopifyImagePullLogger()->error('Failed to queue Shopify image pull', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not queue Shopify image pull worker. Is the queue worker running?',
                'job' => $store->load(),
            ], 500);
        }
        $this->shopifyImagePullLogger()->info('Shopify image pull queued', [
            'total' => $job['total'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Background Shopify image pull started.',
            'job' => $job,
        ]);
    }

    public function shopifyPullJobStatus(ShopifyImagePullJobStore $store)
    {
        return response()->json([
            'success' => true,
            'job' => $store->load(),
        ]);
    }

    public function pauseShopifyPullJob(ShopifyImagePullJobStore $store)
    {
        $job = $store->update(function (array $state) {
            if (($state['status'] ?? 'idle') === 'running') {
                $state['status'] = 'paused';
                $state['last_message'] = 'Pause requested. Current SKU will finish first.';
            }

            return $state;
        });
        $store->appendMessage('Pause requested. Current SKU will finish first.', false);

        return response()->json(['success' => true, 'job' => $job]);
    }

    public function resumeShopifyPullJob(ShopifyImagePullJobStore $store)
    {
        $job = $store->update(function (array $state) {
            if (($state['status'] ?? 'idle') === 'paused') {
                $state['status'] = 'running';
                $state['last_message'] = 'Resumed Shopify image pull.';
            }

            return $state;
        });
        $store->appendMessage('Resumed Shopify image pull.', true);
        try {
            $this->dispatchShopifyImagePullJob();
        } catch (\Throwable $e) {
            $this->shopifyImagePullLogger()->warning('Resume could not re-queue Shopify image pull', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true, 'job' => $job]);
    }

    public function stopShopifyPullJob(ShopifyImagePullJobStore $store)
    {
        $job = $store->update(function (array $state) {
            if (in_array($state['status'] ?? 'idle', ['running', 'paused'], true)) {
                $state['status'] = 'stopping';
                $state['last_message'] = 'Stop requested. Current SKU will finish first.';
            }

            return $state;
        });
        $store->appendMessage('Stop requested. Current SKU will finish first.', false);

        return response()->json(['success' => true, 'job' => $job]);
    }

    private function shopifyImagePullLogger(): \Psr\Log\LoggerInterface
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/shopify-image-pull.log'),
            'level' => 'debug',
        ]);
    }

    private function findProductMasterBySku(string $sku): ?ProductMaster
    {
        $normalizedSku = $this->normalizeSku($sku);
        $skuWithNbsp = str_replace(' ', "\u{00a0}", $normalizedSku);

        return ProductMaster::query()
            ->where('sku', $normalizedSku)
            ->orWhere('sku', strtoupper($normalizedSku))
            ->orWhere('sku', strtolower($normalizedSku))
            ->orWhere('sku', $skuWithNbsp)
            ->first();
    }

    /**
     * @return array{success: bool, message?: string, images?: list<string>, product_id?: string, variant_id?: string, source?: string}
     */
    private function fetchShopifyImagesForSku(string $sku): array
    {
        $mapping = $this->resolveShopifyMappingForSku($sku);
        $store = (string) ($mapping['store'] ?? 'main');
        if ($store === 'pls') {
            $plsTokenService = app(ShopifyPlsTokenService::class);
            $domain = $plsTokenService->getDomain();
            $token = $plsTokenService->getAccessToken();
        } else {
            $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');
        }

        if (! $domain || ! $token) {
            return ['success' => false, 'message' => strtoupper($store).' Shopify credentials not configured.'];
        }

        $domain = rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
        $variantId = $mapping['variant_id'] ?? null;
        $productId = $mapping['product_id'] ?? null;
        if (! $variantId) {
            return ['success' => false, 'message' => 'Shopify variant mapping not found.'];
        }

        if (! $productId) {
            $variantUrl = "https://{$domain}/admin/api/2024-01/variants/{$variantId}.json";
            $variantRes = $this->shopifyPullAdminGet($variantUrl, (string) $token);
            if (! $variantRes->successful()) {
                return ['success' => false, 'message' => 'Variant lookup failed: '.$variantRes->body(), 'variant_id' => (string) $variantId];
            }
            $productId = $variantRes->json('variant.product_id');
        }
        if (! $productId) {
            return ['success' => false, 'message' => 'Product ID missing from Shopify variant.', 'variant_id' => (string) $variantId];
        }

        $productUrl = "https://{$domain}/admin/api/2024-01/products/{$productId}.json";
        $productRes = $this->shopifyPullAdminGet($productUrl, (string) $token);
        if (! $productRes->successful()) {
            return [
                'success' => false,
                'message' => 'Product fetch failed: '.$productRes->body(),
                'variant_id' => (string) $variantId,
                'product_id' => (string) $productId,
            ];
        }

        $images = $this->extractShopifyImageUrls($productRes->json('product.images') ?? []);
        $source = 'shopify_admin';

        if ($images === []) {
            $publicImages = $this->fetchPublicShopifyProductImagesForSku($sku);
            if ($publicImages !== []) {
                $images = $publicImages;
                $source = 'shopify_storefront';
            }
        }

        if ($images === []) {
            $cachedImages = $this->fetchCachedShopifyImagesForSku($sku);
            if ($cachedImages !== []) {
                $images = $cachedImages;
                $source = 'shopify_catalog_cache';
            }
        }

        if ($images === []) {
            return [
                'success' => false,
                'message' => 'No Shopify product images found.',
                'variant_id' => (string) $variantId,
                'product_id' => (string) $productId,
            ];
        }

        return [
            'success' => true,
            'images' => $images,
            'variant_id' => (string) $variantId,
            'product_id' => (string) $productId,
            'store' => $store,
            'source' => $source,
        ];
    }

    /**
     * @param  mixed  $images
     * @return list<string>
     */
    private function extractShopifyImageUrls($images): array
    {
        if (! is_array($images)) {
            return [];
        }

        usort($images, static function ($a, $b) {
            return ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0));
        });

        $urls = [];
        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }
            $src = trim((string) ($image['src'] ?? ''));
            if ($src !== '') {
                $urls[] = $src;
            }
        }

        return $this->dedupeImageUrls($urls);
    }

    /**
     * @return list<string>
     */
    private function fetchPublicShopifyProductImagesForSku(string $sku): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }

        $row = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->whereRaw('LOWER(TRIM(COALESCE(v.sku, \'\'))) = ?', [mb_strtolower(trim($sku))])
            ->orderByDesc('v.synced_at')
            ->orderByDesc('v.id')
            ->select('p.handle')
            ->first();

        $handle = trim((string) ($row->handle ?? ''));
        if ($handle === '') {
            return [];
        }

        $domains = array_values(array_unique(array_filter([
            config('services.shopify_5core.domain'),
            'www.5core.com',
        ])));

        foreach ($domains as $domain) {
            $domain = rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
            if ($domain === '') {
                continue;
            }

            $url = "https://{$domain}/products/{$handle}.js";
            try {
                $response = Http::timeout(30)->connectTimeout(15)->get($url);
            } catch (\Throwable $e) {
                Log::warning('ImageMaster: public Shopify product fetch exception', [
                    'sku' => $sku,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $images = $this->extractShopifyImageUrls($response->json('images') ?? []);
            if ($images !== []) {
                return $images;
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function fetchCachedShopifyImagesForSku(string $sku): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }

        $row = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->whereRaw('LOWER(TRIM(COALESCE(v.sku, \'\'))) = ?', [mb_strtolower(trim($sku))])
            ->orderByDesc('v.synced_at')
            ->orderByDesc('v.id')
            ->select('p.image_src', 'p.images', 'p.image_urls')
            ->first();

        if (! $row) {
            return [];
        }

        $urls = [];
        if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
            $decoded = json_decode((string) ($row->image_urls ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $urls[] = trim($item);
                    } elseif (is_array($item) && ! empty($item['src'])) {
                        $urls[] = trim((string) $item['src']);
                    }
                }
            }
        }

        if ($urls === [] && Schema::hasColumn('shopify_catalog_products', 'images')) {
            $decoded = json_decode((string) ($row->images ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $urls[] = trim($item);
                    } elseif (is_array($item) && ! empty($item['src'])) {
                        $urls[] = trim((string) $item['src']);
                    }
                }
            }
        }

        if ($urls === [] && ! empty($row->image_src)) {
            $urls[] = trim((string) $row->image_src);
        }

        return $this->dedupeImageUrls($urls);
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function dedupeImageUrls(array $urls): array
    {
        $seen = [];
        $out = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $out[] = $url;
        }

        return array_slice($out, 0, self::PM_MAX_IMAGES);
    }

    /**
     * @return array{variant_id?: string, product_id?: string, store?: string}
     */
    private function resolveShopifyMappingForSku(string $sku): array
    {
        $trim = $this->normalizeSku($sku);
        if ($trim === '') {
            return [];
        }

        $lowerSku = mb_strtolower($trim);
        if (Schema::hasTable('shopify_catalog_variants')) {
            $catalogRow = DB::table('shopify_catalog_variants')
                ->whereRaw('LOWER(TRIM(COALESCE(sku, \'\'))) = ?', [$lowerSku])
                ->orderByDesc('synced_at')
                ->orderByDesc('id')
                ->first();
            if ($catalogRow && $catalogRow->shopify_variant_id) {
                return array_filter([
                    'variant_id' => (string) $catalogRow->shopify_variant_id,
                    'product_id' => $catalogRow->shopify_product_id ? (string) $catalogRow->shopify_product_id : null,
                    'store' => (string) ($catalogRow->store ?? 'main'),
                ]);
            }

            $cat = ShopifyVariant::query()
                ->whereRaw('LOWER(TRIM(COALESCE(sku, \'\'))) = ?', [$lowerSku])
                ->orderByDesc('synced_at')
                ->orderByDesc('id')
                ->first();
            if ($cat && $cat->shopify_variant_id) {
                return array_filter([
                    'variant_id' => (string) $cat->shopify_variant_id,
                    'product_id' => $cat->shopify_product_id ? (string) $cat->shopify_product_id : null,
                    'store' => (string) ($cat->store ?? 'main'),
                ]);
            }
        }

        $row = ShopifySku::query()
            ->where('sku', $trim)
            ->orWhereRaw('LOWER(TRIM(COALESCE(sku, \'\'))) = ?', [$lowerSku])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        return $row && $row->variant_id ? ['variant_id' => (string) $row->variant_id, 'store' => 'main'] : [];
    }

    /**
     * @return list<string>
     */
    private function productMasterImageArray(?ProductMaster $product): array
    {
        if (! $product) {
            return [];
        }

        $urls = [];
        for ($i = 1; $i <= self::PM_MAX_IMAGES; $i++) {
            $value = trim((string) ($product->{'image'.$i} ?? ''));
            if ($value !== '') {
                $urls[] = $value;
            }
        }

        if ($urls === [] && ! empty($product->main_image)) {
            $urls[] = trim((string) $product->main_image);
        }

        return $urls;
    }

    /**
     * @param  list<string>  $images
     * @return list<string>
     */
    private function normalizedImageArray(array $images): array
    {
        return array_values(array_map(function ($url) {
            $url = trim((string) $url);
            $path = parse_url($url, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $url = rawurldecode($path);
            }

            return mb_strtolower(preg_replace('/\s+/u', '', $url) ?? $url);
        }, $images));
    }

    private function dispatchShopifyImagePullJob(): void
    {
        RunShopifyImagePullJob::dispatch();
    }

    private function shopifyPullAdminGet(string $url, string $token): \Illuminate\Http\Client\Response
    {
        $last = null;
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $last = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->connectTimeout(15)->get($url);

            if ($last->status() !== 429 || $attempt >= 6) {
                return $last;
            }

            $retryAfter = $last->header('Retry-After');
            $waitMs = is_numeric($retryAfter)
                ? (int) ((float) $retryAfter * 1000000)
                : 2000000 * $attempt;
            usleep(max(2000000, min(10000000, $waitMs)));
        }

        return $last;
    }
}
