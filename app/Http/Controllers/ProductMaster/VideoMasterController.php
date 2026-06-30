<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Http\Controllers\ProductMaster\Concerns\GuardsMarketplaceApiConfiguration;
use App\Jobs\RunVideoMasterPushJob;
use App\Jobs\RunShopifyVideoPullJob;
use App\Models\ProductVideo;
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
use App\Services\Support\VideoMasterPushJobStore;
use App\Services\Support\ShopifyVideoPullJobStore;
use App\Services\Support\EbaySellInventoryListingResolver;
use App\Services\Support\EbayTradingReviseItem;
use App\Services\WayfairApiService;
use Illuminate\Support\Facades\Storage;
use App\Services\DobaApiService;
use App\Services\TemuApiService;
use App\Services\Temu2ApiService;
use App\Services\WalmartService;
use App\Services\FaireService;
use App\Services\SheinApiService;
use App\Services\AliExpressApiService;
use App\Services\NeweggApiService;
use App\Services\TopDawgApiService;
use App\Services\TikTokShopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class VideoMasterController extends Controller
{
    use GuardsMarketplaceApiConfiguration;

    private const PM_MAX_VIDEOS = 10;

    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('video-master', compact('mode', 'demo'));
    }

    /**
     * Product rows from Product Master + per-marketplace video_master_json (push state / last URLs).
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
                $metricsByMarketplace[$marketplace] = $this->loadVideoMetricsBySku($table);
            }

            $mainBySku = $this->loadVideoMainByMarketplaceForSkus(
                array_map(fn ($row) => $this->normalizeSku($row['SKU'] ?? null), $products)
            );
            $videoSlotsBySku = $this->loadProductMasterVideoSlotsForSkus(
                array_map(fn ($row) => $this->normalizeSku($row['SKU'] ?? null), $products)
            );

            foreach ($products as &$row) {
                $sku = $this->normalizeSku($row['SKU'] ?? null);
                $im = [];
                foreach (array_keys($marketTables) as $mp) {
                    $im[$mp] = $metricsByMarketplace[$mp][$sku] ?? '';
                }
                $row['video_master'] = $im;
                $row['video_main_by_marketplace'] = $mainBySku[$sku] ?? [];
                foreach ($videoSlotsBySku[$sku] ?? [] as $column => $value) {
                    $row[$column] = $value;
                }
                $row['preview_thumb'] = $this->firstPreviewUrl($row);
            }

            return response()->json([
                'message' => 'Data loaded from database',
                'data' => $products,
                'status' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('VideoMaster getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load video master data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * Amazon listing images via Listings Items API (same media path as catalog enrichment).
     */
    public function getAmazonVideos(Request $request)
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
                'videos' => $this->normalizeVideoList($res['videos'] ?? []),
                'message' => $res['message'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'videos' => [],
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * eBay gallery URLs from GetItem (Trading API). account: ebay | ebay2 | ebay3
     */
    public function getEbayVideos(Request $request)
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
            return response()->json(['success' => false, 'videos' => [], 'message' => 'Metrics table missing.'], 422);
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
                    'videos' => [],
                    'message' => 'No eBay listing found for this SKU (metrics item_id empty and Inventory/GetSellerList lookup failed).',
                ], 422);
            }

            $getItem = $svc->getItem((string) $itemId);
            if (! $getItem) {
                return response()->json(['success' => false, 'videos' => [], 'message' => 'GetItem failed.'], 502);
            }
            $urls = EbayTradingReviseItem::extractPictureUrlsFromGetItem($getItem);

            return response()->json([
                'success' => true,
                'videos' => $urls,
                'item_id' => (string) $itemId,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'videos' => [], 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Push ordered image URLs to marketplace and persist video_master_json on success (or local-only for unsupported APIs).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pushToMarketplace(Request $request)
    {
        $validated = $request->validate([
            'sku'                    => 'required|string|max:255',
            'updates'                => 'required|array|min:1',
            'updates.*.marketplace'  => 'required|string',
            'updates.*.videos'       => 'required|array',          // allow empty for "clear all"
            'updates.*.videos.*'     => 'nullable|string|max:2048',
            'mode'                   => 'nullable|string|in:replace,add',
            'main_by_marketplace'    => 'nullable|array',
            'main_by_marketplace.*'  => 'integer|min:0|max:'.(self::PM_MAX_VIDEOS - 1),
            'dry_run'                => 'nullable|boolean',
        ]);

        $sku     = $this->normalizeSku($validated['sku']);
        $mode    = $validated['mode'] ?? 'replace';   // 'replace' | 'add'
        $dryRun  = (bool) ($validated['dry_run'] ?? false);
        $results = [];

        $maxVideoCount = 0;
        foreach ($validated['updates'] as $u) {
            $maxVideoCount = max($maxVideoCount, count(array_values(array_filter(array_map('trim', $u['videos'] ?? []), fn ($s) => $s !== ''))));
        }
        $mainMap = $this->resolveMainByMarketplaceForPush(
            $sku,
            $validated['main_by_marketplace'] ?? null,
            $maxVideoCount
        );

        if ($dryRun) {
            foreach ($validated['updates'] as $u) {
                $mp = strtolower(trim($u['marketplace']));
                $videos = array_values(array_filter(array_map('trim', $u['videos'] ?? []), fn ($s) => $s !== ''));
                $results[$mp] = $this->runQueuedMarketplacePush(
                    $sku,
                    $mp,
                    $videos,
                    $mode,
                    $mainMap,
                    true
                );
            }

            $totalSuccess = collect($results)->where('success', true)->count();
            $totalFailed = collect($results)->where('success', false)->count();
            $totalMetricsFailed = collect($results)->filter(
                fn ($r) => ($r['success'] ?? false) && ! ($r['metrics_saved'] ?? false)
            )->count();

            return response()->json([
                'success' => $totalFailed === 0,
                'dry_run' => true,
                'queued' => false,
                'results' => $results,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'total_metrics_failed' => $totalMetricsFailed,
                'message' => "Updated {$totalSuccess} marketplace(s).".($totalFailed > 0 ? " {$totalFailed} failed." : '').($totalMetricsFailed > 0 ? " {$totalMetricsFailed} metrics save failed." : ''),
            ]);
        }

        /** @var VideoMasterPushJobStore $pushStore */
        $pushStore = app(VideoMasterPushJobStore::class);
        $currentJob = $pushStore->load();
        if ($pushStore->isActive($currentJob) && ! $pushStore->isStale($currentJob)) {
            return response()->json(array_merge($pushStore->toApiResponse($currentJob), [
                'success' => false,
                'message' => 'A video push is already running. Wait for it to finish or check progress below.',
            ]), 409);
        }
        // A stale "running" job (worker died/never ran) is auto-cleared so it can't block forever.
        if ($pushStore->isActive($currentJob)) {
            $pushStore->forceStop('Cleared a stale push job (no worker was processing it).');
            $this->releaseUniqueJobLock(RunVideoMasterPushJob::class, 'video-master-push');
        }

        $tasks = [];
        foreach ($validated['updates'] as $u) {
            $tasks[] = [
                'marketplace' => strtolower(trim($u['marketplace'])),
                'videos' => array_values(array_filter(array_map('trim', $u['videos'] ?? []), fn ($s) => $s !== '')),
            ];
        }

        $job = $pushStore->create($sku, $mode, $tasks, $mainMap);
        try {
            $this->dispatchVideoMasterPushJob();
        } catch (\Throwable $e) {
            $pushStore->markFailed('Could not queue worker: '.$e->getMessage());
            Log::warning('VideoMaster push queue dispatch failed', ['error' => $e->getMessage()]);

            return response()->json(array_merge($pushStore->toApiResponse($pushStore->load()), [
                'success' => false,
                'message' => 'Could not queue video push worker. Is the video-master-push queue worker running?',
            ]), 500);
        }

        return response()->json(array_merge($pushStore->toApiResponse($job), [
            'message' => 'Video push queued ('.count($tasks).' marketplace(s)). Processing in background…',
        ]));
    }

    /**
     * Push one SKU to one marketplace (used by queue worker and dry-run).
     *
     * @param  list<string>  $images
     * @param  array<string, int>|null  $mainMap
     * @return array{success: bool, message: string, metrics_saved?: bool, dry_run?: bool, images_count?: int}
     */
    public function runQueuedMarketplacePush(
        string $sku,
        string $marketplace,
        array $videos,
        string $mode = 'replace',
        ?array $mainMap = null,
        bool $dryRun = false
    ): array {
        @set_time_limit(0);

        $mp = strtolower(trim($marketplace));
        $allowed = array_keys($this->marketplaceTableMap());
        $videos = array_values(array_filter(array_map('trim', $videos), fn ($s) => $s !== ''));
        $originalCount = count($videos);
        $mainMap = $mainMap ?? [];

        if (! in_array($mp, $allowed, true)) {
            return ['success' => false, 'message' => 'Unknown marketplace'];
        }

        if ($blocked = $this->marketplaceApiNotConfiguredResult($mp)) {
            return $blocked;
        }

        if ($videos === [] && $mode !== 'replace') {
            return ['success' => true, 'message' => 'No videos to add; skipped.'];
        }

        if ($videos === [] && $mode === 'replace' && ! in_array($mp, ['shopify_main', 'shopify_pls'], true)) {
            return [
                'success' => false,
                'message' => 'Clear-all videos is only supported for Shopify Main and Shopify PLS.',
            ];
        }

        $limit = $this->marketplaceVideoLimit($mp);
        $videos = array_slice($videos, 0, $limit);
        $truncatedNote = $originalCount > $limit
            ? " Truncated from {$originalCount} to {$limit} video(s) ({$mp} limit)."
            : '';

        $effectiveMode = $mode;
        $addModeNote = '';
        if ($mode === 'add' && ! in_array($mp, $this->marketplacesSupportingAddMode(), true)) {
            $effectiveMode = 'replace';
            $addModeNote = ' Add mode is not supported for this marketplace; used replace instead.';
        }

        $videosForPush = in_array($mp, ['shopify_main', 'shopify_pls'], true)
            ? $videos
            : $this->rewriteLocalStorageUrlsToPublic($videos);

        if ($dryRun) {
            $remote = $this->dryRunPushToRemote($mp, $sku, $videosForPush, $effectiveMode);
            $remoteOk = (bool) ($remote['success'] ?? false);
            $dryNote = ' (dry run — no marketplace write).';
            $message = trim(($remote['message'] ?? '').$truncatedNote.$addModeNote.$dryNote);

            return array_merge([
                'success' => $remoteOk,
                'dry_run' => true,
                'metrics_saved' => false,
                'message' => $message,
                'videos_count' => count($videosForPush),
            ], array_diff_key($remote, ['success' => 1, 'message' => 1]));
        }

        $remote = $this->pushVideosToRemote($mp, $sku, $videosForPush, $effectiveMode);
        $remoteOk = (bool) ($remote['success'] ?? false);

        if (! $remoteOk) {
            Log::warning('VideoMaster marketplace push failed', [
                'marketplace' => $mp,
                'sku' => $sku,
                'message' => $remote['message'] ?? null,
                'video_count' => count($videosForPush),
                'first_video' => isset($videosForPush[0]) ? mb_substr((string) $videosForPush[0], 0, 300) : null,
                'listing_id' => $remote['listing_id'] ?? null,
            ]);
        }

        $urlsForMetrics = $videosForPush;
        if ($remoteOk && ! empty($remote['normalized_urls']) && is_array($remote['normalized_urls'])) {
            $urlsForMetrics = array_values($remote['normalized_urls']);
        } elseif ($remoteOk && $videosForPush !== []) {
            $urlsForMetrics = $videosForPush;
        } elseif ($remoteOk && $videosForPush === []) {
            $urlsForMetrics = [];
        }

        $saved = false;
        if ($remoteOk) {
            $saved = $this->saveVideoMetricsToTable($mp, $sku, $urlsForMetrics);
            if (in_array($mp, ['shopify_main', 'shopify_pls'], true)) {
                $saved = $this->saveShopifyCatalogVideos($sku, $mp, $urlsForMetrics) || $saved;
            }
        }

        $message = trim(($remote['message'] ?? '').$truncatedNote.$addModeNote);
        if ($message === '' && ! $remoteOk) {
            $message = ucfirst($mp).' push failed (no error detail returned). Check storage/logs/laravel.log.';
        }
        if ($remoteOk && ! $saved) {
            $message .= ' Metrics not saved.';
        }

        return [
            'success' => $remoteOk,
            'metrics_saved' => $saved,
            'message' => $message,
        ];
    }

    public function pushJobStatus(VideoMasterPushJobStore $store)
    {
        $job = $store->load();

        return response()->json($store->toApiResponse($job));
    }

    /**
     * Validate push readiness without calling marketplace write APIs (where supported).
     *
     * @return array<string, mixed>
     */
    private function dryRunPushToRemote(string $marketplace, string $sku, array $imageUrls, string $mode = 'replace'): array
    {
        if ($imageUrls === [] && $mode === 'replace' && in_array($marketplace, ['shopify_main', 'shopify_pls'], true)) {
            return ['success' => true, 'message' => 'Dry run OK: would clear all Shopify videos.'];
        }

        if ($imageUrls === []) {
            return ['success' => false, 'message' => 'No videos to push.'];
        }

        try {
            switch ($marketplace) {
                case 'amazon':
                    return app(AmazonSpApiService::class)->dryRunUpdateVideos($sku, $imageUrls);
                case 'shopify_main':
                    return app(ShopifyApiService::class)->dryRunUpdateVideos($sku, $imageUrls);
                case 'shopify_pls':
                    return app(ShopifyPLSApiService::class)->dryRunUpdateVideos($sku, $imageUrls);
                case 'reverb':
                    return app(ReverbApiService::class)->dryRunUpdateVideos($sku, $imageUrls);
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
                return ['success' => false, 'message' => 'Dry run: invalid video URL (must be http/https).', 'dry_run' => true];
            }
        }

        return [
            'success' => true,
            'dry_run' => true,
            'message' => 'Dry run OK: would push '.count($imageUrls).' video(s) to '.($marketplace).'.',
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
                return ['success' => false, 'message' => 'Dry run: invalid video URL.', 'dry_run' => true];
            }
        }

        return [
            'success' => true,
            'dry_run' => true,
            'message' => 'Dry run OK: would push '.count($imageUrls).' video(s) to '.$marketplace.'.',
        ];
    }

    /**
     * Save ordered URLs to Product Master video1–video20 and main_video.
     */
    public function saveProductMasterVideos(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'videos' => 'present|array|max:'.self::PM_MAX_VIDEOS,
            'videos.*' => 'nullable|string|max:2048',
            'main_by_marketplace' => 'nullable|array',
            'main_by_marketplace.*' => 'integer|min:0|max:'.(self::PM_MAX_VIDEOS - 1),
            'removed_urls' => 'nullable|array|max:100',
            'removed_urls.*' => 'nullable|string|max:2048',
        ]);
        $sku = $this->normalizeSku($validated['sku']);
        $videos = $this->normalizeStorageUrlsForVideoMasterMetrics(
            array_values(array_slice($validated['videos'], 0, self::PM_MAX_VIDEOS))
        );
        $mainByMarketplace = $this->sanitizeMainByMarketplace(
            $validated['main_by_marketplace'] ?? [],
            count($videos)
        );

        $product = ProductMaster::query()->where('sku', $sku)->first();
        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        for ($i = 0; $i < self::PM_MAX_VIDEOS; $i++) {
            $col = 'video'.($i + 1);
            $product->{$col} = $videos[$i] ?? null;
        }
        $product->main_video = $videos[0] ?? null;
        if (Schema::hasColumn('product_master', 'video_main_by_marketplace_json')) {
            $product->video_main_by_marketplace_json = null;
        }

        try {
            $product->save();

            // Clean up images the user removed in the modal — local file, DB row, and Shopify CDN
            // file. Heavily guarded (see purgeRemovedSkuVideos) so a kept image can never be deleted.
            $purged = $this->purgeRemovedSkuVideos($sku, $validated['removed_urls'] ?? [], $videos);

            return response()->json([
                'success' => true,
                'message' => 'Product Master videos saved.'.($purged > 0 ? " Removed {$purged} video(s)." : ''),
                'video_main_by_marketplace' => [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload files to public disk under products/{sku}/, persist to product_videos, return URLs.
     */
    public function uploadVideos(Request $request)
    {
        $validated = $request->validate([
            'sku'    => 'required|string|max:255',
            'files'  => 'required|array|min:1|max:'.self::PM_MAX_VIDEOS,
            'files.*' => 'file|mimes:mp4,webm,mov,quicktime,m4v|max:102400',
        ]);

        $sku     = $this->normalizeSku($validated['sku']);
        $safeSku = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', $sku);
        $folder  = "products/{$safeSku}/videos";

        $urls    = [];
        $records = [];
        $now     = now();

        foreach ($request->file('files', []) as $file) {
            if (! $file) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $baseName     = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'video';
            $mime         = $file->getClientMimeType() ?: 'video/mp4';
            $extByMime    = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/x-m4v' => 'm4v'];
            $extension    = $extByMime[strtolower($mime)] ?? (strtolower($file->getClientOriginalExtension()) ?: 'mp4');
            $uniqueName   = $baseName.'_'.uniqid().'.'.$extension;
            $path         = $folder.'/'.$uniqueName;
            $bytes        = (string) @file_get_contents($file->getRealPath());
            if ($bytes === '') {
                Log::warning('Video Master upload: skipped empty file', ['sku' => $sku, 'file' => $originalName]);

                continue;
            }

            Storage::disk('public')->put($path, $bytes);
            $localUrl = $this->normalizeStorageUrlsForVideoMasterMetrics([asset('storage/'.$path)])[0] ?? asset('storage/'.$path);

            $record = ProductVideo::create([
                'sku'           => $sku,
                'video_path'    => $path,
                'cdn_url'       => null,
                'cdn_file_id'   => null,
                'original_name' => $originalName,
                'file_size'     => strlen($bytes),
                'mime_type'     => $mime,
                'created_at'    => $now,
            ]);

            $urls[] = $localUrl;
            $records[] = [
                'id'   => $record->id,
                'url'  => $localUrl,
                'name' => $originalName,
            ];
        }

        return response()->json([
            'success' => true,
            'urls'    => $urls,
            'videos'  => $records,
        ]);
    }

    /**
     * Return all locally stored images for a SKU (from product_videos table).
     */
    public function getSkuVideos(Request $request)
    {
        $sku = $this->normalizeSku($request->get('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'videos' => []]);
        }

        $videos = ProductVideo::where('sku', $sku)
            ->orderBy('id')
            ->get()
            ->map(fn (ProductVideo $video) => [
                'id'   => $video->id,
                'url'  => $this->normalizeStorageUrlsForVideoMasterMetrics([asset('storage/'.$video->video_path)])[0] ?? asset('storage/'.$video->video_path),
                'name' => $video->original_name ?? basename($video->video_path),
                'path' => $video->video_path,
            ])
            ->values();

        return response()->json(['success' => true, 'videos' => $videos]);
    }

    /**
     * Delete a stored SKU image from DB and disk.
     */
    public function deleteSkuVideo(Request $request, int $id)
    {
        $image = ProductVideo::find($id);
        if (! $image) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Remove the CDN copy too (by stored file id, or fall back to resolving from the URL).
        $cdnRef = $image->cdn_file_id ?: $image->cdn_url;
        if (! empty($cdnRef)) {
            try {
                if (! app(ShopifyApiService::class)->deleteCdnFile((string) $cdnRef)) {
                    Log::warning('Video Master delete: CDN file not removed', ['id' => $id, 'cdn' => $cdnRef]);
                }
            } catch (\Throwable $e) {
                Log::warning('Video Master delete: CDN delete threw', ['id' => $id, 'error' => $e->getMessage()]);
            }
        }

        if (! empty($image->video_path)) {
            Storage::disk('public')->delete($image->video_path);
        }
        $image->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Delete images the user removed in the modal (called from Save) — local file + DB row +
     * Shopify CDN file. A wrong delete is unrecoverable, so EVERY condition must hold before
     * deleting any row:
     *   1. the product_videos row belongs to THIS sku;
     *   2. the row matches a URL the user actually removed (by cdn_url, or local file basename);
     *   3. the row's image is NOT in the final saved set.
     * Anything failing all three is skipped (logged), never deleted.
     *
     * @param  list<string>  $removedUrls  URLs the user removed this session
     * @param  list<string>  $keptImages   the final saved image URLs (must never be deleted)
     */
    private function purgeRemovedSkuVideos(string $sku, array $removedUrls, array $keptImages): int
    {
        $removedUrls = array_values(array_filter(array_map('trim', $removedUrls), fn ($s) => $s !== ''));
        if ($removedUrls === [] || ! Schema::hasTable('product_videos')) {
            return 0;
        }

        $kept = [];
        foreach ($keptImages as $u) {
            $kept[strtok((string) $u, '?')] = true;
        }
        $removed = [];
        foreach ($removedUrls as $u) {
            $removed[strtok((string) $u, '?')] = true;
        }

        $deleted = 0;
        foreach (ProductVideo::where('sku', $sku)->get() as $img) {
            $cdn  = $img->cdn_url ? strtok((string) $img->cdn_url, '?') : '';
            $base = $img->video_path ? basename((string) $img->video_path) : '';

            // (2) must match a removed URL (by cdn_url or local basename)
            $matchesRemoved = ($cdn !== '' && isset($removed[$cdn]));
            if (! $matchesRemoved && $base !== '') {
                foreach (array_keys($removed) as $r) {
                    if (str_contains($r, $base)) {
                        $matchesRemoved = true;
                        break;
                    }
                }
            }
            if (! $matchesRemoved) {
                continue;
            }

            // (3) NEVER delete a row whose image is still in the saved/kept set
            $stillKept = ($cdn !== '' && isset($kept[$cdn]));
            if (! $stillKept && $base !== '') {
                foreach (array_keys($kept) as $k) {
                    if (str_contains($k, $base)) {
                        $stillKept = true;
                        break;
                    }
                }
            }
            if ($stillKept) {
                Log::warning('Video Master purge: skipped (image still in saved set)', ['sku' => $sku, 'id' => $img->id]);
                continue;
            }

            $cdnRef = $img->cdn_file_id ?: $img->cdn_url;
            $cdnResult = 'none'; // none = no CDN ref to delete
            if (! empty($cdnRef)) {
                try {
                    $cdnResult = app(ShopifyApiService::class)->deleteCdnFile((string) $cdnRef) ? 'deleted' : 'not_found';
                } catch (\Throwable $e) {
                    $cdnResult = 'error';
                    Log::warning('Video Master purge: CDN delete threw', ['id' => $img->id, 'ref' => (string) $cdnRef, 'error' => $e->getMessage()]);
                }
                if ($cdnResult === 'not_found') {
                    Log::warning('Video Master purge: CDN file NOT deleted (not found / no deletedFileIds)', ['id' => $img->id, 'ref' => (string) $cdnRef]);
                }
            }
            if (! empty($img->video_path)) {
                Storage::disk('public')->delete($img->video_path);
            }
            $imgId = $img->id;
            $imgCdn = $img->cdn_url;
            $img->delete();
            $deleted++;
            Log::info('Video Master purge: removed image', ['sku' => $sku, 'id' => $imgId, 'cdn' => $imgCdn, 'cdn_delete' => $cdnResult]);
        }

        return $deleted;
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function pushVideosToRemote(string $marketplace, string $sku, array $imageUrls, string $mode = 'replace'): array
    {
        try {
            switch ($marketplace) {
                case 'ebay':
                    return app(EbayApiService::class)->updateListingVideos($sku, $imageUrls);
                case 'ebay2':
                    return app(Ebay2ApiService::class)->updateListingVideos($sku, $imageUrls);
                case 'ebay3':
                    return app(EbayThreeApiService::class)->updateListingVideos($sku, $imageUrls);
                case 'amazon':
                    return app(AmazonSpApiService::class)->updateVideos($sku, $imageUrls);
                case 'temu':
                    return app(TemuApiService::class)->updateVideos($sku, $imageUrls);
                case 'wayfair':
                    return app(WayfairApiService::class)->updateVideos($sku, $imageUrls);
                case 'bestbuy':
                    return app(BestBuyApiService::class)->updateVideos($sku, $imageUrls);
                case 'shopify_main':
                    return app(ShopifyApiService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'shopify_pls':
                    return app(ShopifyPLSApiService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'macy':
                    return app(MacysApiService::class)->updateVideos($sku, $imageUrls);
                case 'reverb':
                    return app(ReverbApiService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'doba':
                    return app(DobaApiService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'temu2':
                    return app(Temu2ApiService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'walmart':
                    return app(WalmartService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'faire':
                    return app(FaireService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'shein':
                    return app(SheinApiService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'aliexpress':
                    return app(AliExpressApiService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'newegg':
                    return app(NeweggApiService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'topdawg':
                    return app(TopDawgApiService::class)->updateVideos($sku, $imageUrls, $mode);
                case 'tiktok':
                case 'tiktok2':
                    return app(TikTokShopService::class)->updateVideos($sku, $imageUrls, $mode);
                default:
                    return [
                        'success' => false,
                        'message' => 'Video push is not implemented for '.$marketplace.' yet.',
                    ];
            }
        } catch (\Throwable $e) {
            Log::warning('VideoMaster pushVideosToRemote failed', ['mp' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function saveVideoMetricsToTable(string $marketplace, string $sku, array $videoUrls): bool
    {
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if (! $table || ! Schema::hasTable($table)) {
            return false;
        }
        if (! Schema::hasColumn($table, 'sku')) {
            return false;
        }

        $isClear = $videoUrls === [];

        try {
            $now = now();
            $updatable = [];
            if ($isClear) {
                if (Schema::hasColumn($table, 'video_master_json')) {
                    $updatable['video_master_json'] = null;
                }
                if (Schema::hasColumn($table, 'video_urls')) {
                    $updatable['video_urls'] = null;
                }
            } else {
                $payload = json_encode(array_values($videoUrls), JSON_UNESCAPED_SLASHES);
                if ($payload === false) {
                    return false;
                }
                if (Schema::hasColumn($table, 'video_master_json')) {
                    $updatable['video_master_json'] = $payload;
                }
                if (Schema::hasColumn($table, 'video_urls')) {
                    $updatable['video_urls'] = $payload;
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
            Log::warning("VideoMaster: could not save {$table}", ['sku' => $sku, 'error' => $e->getMessage()]);

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
            'doba' => 'doba_metrics',
            'temu2' => 'temu2_metrics',
            'walmart' => 'walmart_metrics',
            'faire' => 'faire_metrics',
            'shein' => 'shein_metrics',
            'aliexpress' => 'aliexpress_metrics',
            'newegg' => 'newegg_metrics',
            'topdawg' => 'topdawg_metrics',
            'tiktok' => 'tiktok_metrics',
            'tiktok2' => 'tiktok_metrics',
        ];
    }

    /**
     * @return list<string>
     */
    private function marketplacesSupportingAddMode(): array
    {
        return ['shopify_main', 'shopify_pls', 'reverb'];
    }

    private function marketplaceVideoLimit(string $marketplace): int
    {
        return match ($marketplace) {
            'amazon' => 3,
            'doba' => 1,
            'walmart' => 1,
            'reverb' => 5,
            'shopify_main', 'shopify_pls' => self::PM_MAX_VIDEOS,
            default => 5,
        };
    }

    /**
     * @param  list<string|null>  $skus
     * @return array<string, array<string, string|null>>
     */
    private function loadProductMasterVideoSlotsForSkus(array $skus): array
    {
        if (! Schema::hasTable('product_master')) {
            return [];
        }

        $columns = ['sku'];
        for ($i = 1; $i <= self::PM_MAX_VIDEOS; $i++) {
            $col = 'video'.$i;
            if (Schema::hasColumn('product_master', $col)) {
                $columns[] = $col;
            }
        }
        if (Schema::hasColumn('product_master', 'main_video')) {
            $columns[] = 'main_video';
        }
        if (count($columns) === 1) {
            return [];
        }

        $skus = array_values(array_unique(array_filter(array_map(fn ($s) => $this->normalizeSku($s), $skus))));
        if ($skus === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('product_master')->whereIn('sku', $skus)->get($columns) as $row) {
            $sku = $this->normalizeSku($row->sku ?? null);
            if ($sku === '') {
                continue;
            }
            $slots = [];
            for ($i = 1; $i <= self::PM_MAX_VIDEOS; $i++) {
                $col = 'video'.$i;
                if (in_array($col, $columns, true)) {
                    $value = trim((string) ($row->{$col} ?? ''));
                    $slots[$col] = $value !== '' ? $value : null;
                }
            }
            if (in_array('main_video', $columns, true)) {
                $value = trim((string) ($row->main_video ?? ''));
                $slots['main_video'] = $value !== '' ? $value : null;
            }
            $out[$sku] = $slots;
        }

        return $out;
    }

    /**
     * @param  list<string|null>  $skus
     * @return array<string, array<string, int>>
     */
    private function loadVideoMainByMarketplaceForSkus(array $skus): array
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'video_main_by_marketplace_json')) {
            return [];
        }

        $skus = array_values(array_unique(array_filter(array_map(fn ($s) => $this->normalizeSku($s), $skus))));
        if ($skus === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('product_master')->whereIn('sku', $skus)->get(['sku', 'video_main_by_marketplace_json']) as $row) {
            $sku = $this->normalizeSku($row->sku ?? null);
            if ($sku === '') {
                continue;
            }
            $out[$sku] = $this->decodeVideoMainByMarketplaceJson((string) ($row->video_main_by_marketplace_json ?? ''));
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function loadVideoMainByMarketplace(string $sku): array
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'video_main_by_marketplace_json')) {
            return [];
        }

        $raw = DB::table('product_master')->where('sku', $sku)->value('video_main_by_marketplace_json');

        return $this->decodeVideoMainByMarketplaceJson((string) ($raw ?? ''));
    }

    /**
     * @return array<string, int>
     */
    private function decodeVideoMainByMarketplaceJson(string $raw): array
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
            $out[$mp] = max(0, min(self::PM_MAX_VIDEOS - 1, (int) $idx));
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
        $maxIdx = min(self::PM_MAX_VIDEOS - 1, $imageCount - 1);
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

    private function mainVideoIndexForMarketplace(string $sku, string $marketplace, int $imageCount): int
    {
        return $this->mainVideoIndexFromMap($this->loadVideoMainByMarketplace($sku), $marketplace, $imageCount);
    }

    /**
     * @param  array<string, int>  $map
     */
    private function mainVideoIndexFromMap(array $map, string $marketplace, int $imageCount): int
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
        $fromDb = $this->loadVideoMainByMarketplace($sku);
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
    private function reorderVideosWithMainFirst(array $images, int $mainIndex): array
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
     * @return array<string, string> sku => video_master_json raw or ''
     */
    private function loadVideoMetricsBySku(string $table): array
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return [];
            }
            $hasMasterJson = Schema::hasColumn($table, 'video_master_json');
            $hasVideoUrls = Schema::hasColumn($table, 'video_urls');
            if (! $hasMasterJson && ! $hasVideoUrls && ! Schema::hasColumn($table, 'image_urls')) {
                return [];
            }
            $valueColumn = $hasMasterJson ? 'video_master_json' : ($hasVideoUrls ? 'video_urls' : 'image_urls');

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
            Log::warning("VideoMaster: load metrics failed for {$table}", ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Load the last-pushed image URLs for a marketplace/sku from the metrics table.
     * Used in "add" mode to append new images to whatever is already on the marketplace.
     *
     * @return list<string>
     */
    private function loadExistingMarketplaceVideos(string $marketplace, string $sku): array
    {
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if (! $table || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
            return [];
        }

        $hasMasterJson = Schema::hasColumn($table, 'video_master_json');
        $hasImageUrls  = Schema::hasColumn($table, 'image_urls');
        if (! $hasMasterJson && ! $hasImageUrls) {
            return [];
        }

        $col = $hasMasterJson ? 'video_master_json' : 'image_urls';
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
    private function normalizeStorageUrlsForVideoMasterMetrics(array $urls): array
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
        foreach (['main_video', 'video1', 'video2', 'video3', 'video4', 'video5', 'video6'] as $k) {
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
        $fallback = $row['video_path'] ?? null;
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
    private function saveShopifyCatalogVideos(string $sku, string $marketplace, array $imageUrls): bool
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
                if (Schema::hasColumn('shopify_catalog_products', 'video_master_json')) {
                    $update['video_master_json'] = null;
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
                if (Schema::hasColumn('shopify_catalog_products', 'video_master_json')) {
                    $update['video_master_json'] = $payload;
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
            Log::warning('VideoMaster: failed saving shopify_catalog_products images', ['sku' => $sku, 'marketplace' => $marketplace, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Pull current Shopify product images into Product Master video1–video12 and main_video.
     */
    public function pullShopifyVideosToMaster(Request $request)
    {
        $pullLog = $this->shopifyVideoPullLogger();
        $sku = '';
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'dry_run' => 'nullable|boolean',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $dryRun = (bool) ($validated['dry_run'] ?? false);
            $pullLog->info('Shopify video pull started', ['sku' => $sku]);
            Log::info('VideoMaster: Shopify video pull started', ['sku' => $sku]);
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

            $currentImages = $this->productMasterVideoArray($product);
            $shopify = $this->fetchShopifyVideosForSku($sku);
            if (! ($shopify['success'] ?? false)) {
                $pullLog->warning('Shopify video fetch failed', [
                    'sku' => $sku,
                    'message' => $shopify['message'] ?? 'Unable to fetch Shopify product.',
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'product_id' => $shopify['product_id'] ?? null,
                ]);
                Log::warning('VideoMaster: Shopify video fetch failed', [
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

            $shopifyVideos = array_slice($shopify['videos'] ?? [], 0, self::PM_MAX_VIDEOS);
            if ($shopifyVideos === []) {
                $pullLog->warning('No Shopify videos detected', [
                    'sku' => $sku,
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'source' => $shopify['source'] ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'sku' => $sku,
                    'status' => 'no_images_detected',
                    'message' => 'No Shopify product videos detected.',
                    'current_images' => $currentImages,
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                ], 422);
            }

            $matchedBefore = $this->normalizedVideoArray($currentImages) === $this->normalizedVideoArray($shopifyVideos);

            if ($dryRun) {
                $pullLog->info('Shopify video pull dry run', [
                    'sku' => $sku,
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'source' => $shopify['source'] ?? null,
                    'matched_before' => $matchedBefore,
                    'before_count' => count($currentImages),
                    'shopify_count' => count($shopifyVideos),
                ]);

                return response()->json([
                    'success' => true,
                    'dry_run' => true,
                    'sku' => $sku,
                    'status' => $matchedBefore ? 'already_matched' : 'would_import',
                    'message' => $matchedBefore
                        ? 'Dry run: already matched Shopify videos (no change).'
                        : 'Dry run: would import '.count($shopifyVideos).' video(s) to Product Master.',
                    'source' => $shopify['source'] ?? 'shopify_admin',
                    'shopify_product_id' => $shopify['product_id'] ?? null,
                    'variant_id' => $shopify['variant_id'] ?? null,
                    'before_videos' => $currentImages,
                    'shopify_videos' => $shopifyVideos,
                    'after_videos' => $shopifyVideos,
                ]);
            }

            for ($i = 1; $i <= self::PM_MAX_VIDEOS; $i++) {
                $product->{'video'.$i} = $shopifyVideos[$i - 1] ?? null;
            }
            $product->main_video = $shopifyVideos[0] ?? null;
            $product->save();

            $newVideos = $this->productMasterVideoArray($product->fresh());
            Log::info('VideoMaster: pulled Shopify videos to Product Master', [
                'sku' => $sku,
                'shopify_product_id' => $shopify['product_id'] ?? null,
                'variant_id' => $shopify['variant_id'] ?? null,
                'source' => $shopify['source'] ?? null,
                'matched_before' => $matchedBefore,
                'video_count' => count($shopifyVideos),
            ]);
            $pullLog->info('Shopify videos saved to Product Master', [
                'sku' => $sku,
                'shopify_product_id' => $shopify['product_id'] ?? null,
                'variant_id' => $shopify['variant_id'] ?? null,
                'source' => $shopify['source'] ?? null,
                'matched_before' => $matchedBefore,
                'before_count' => count($currentImages),
                'shopify_count' => count($shopifyVideos),
                'after_count' => count($newVideos),
            ]);

            return response()->json([
                'success' => true,
                'dry_run' => false,
                'sku' => $sku,
                'status' => $matchedBefore ? 'already_matched' : 'imported_to_product_master',
                'message' => $matchedBefore ? 'Already matched Shopify videos.' : 'Imported Shopify videos to Product Master.',
                'source' => $shopify['source'] ?? 'shopify_admin',
                'shopify_product_id' => $shopify['product_id'] ?? null,
                'variant_id' => $shopify['variant_id'] ?? null,
                'before_videos' => $currentImages,
                'shopify_videos' => $shopifyVideos,
                'after_videos' => $newVideos,
            ]);
        } catch (\Throwable $e) {
            Log::error('VideoMaster pullShopifyVideosToMaster failed', ['error' => $e->getMessage()]);
            $pullLog->error('Shopify video pull exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function startShopifyPullJob(Request $request, ShopifyVideoPullJobStore $store)
    {
        $validated = $request->validate([
            'skus' => 'required|array|min:1',
            'skus.*' => 'required|string',
        ]);

        $current = $store->load();
        if ($store->isActive($current) && ! $store->isStale($current)) {
            return response()->json([
                'success' => false,
                'message' => 'A Shopify video pull is already running or paused. Stop it first to start a new one.',
                'job' => $current,
            ], 409);
        }
        // A stale "active" job (worker died/never ran) is auto-cleared so it can't block forever.
        if ($store->isActive($current)) {
            $store->forceStop('Cleared a stale pull job (no worker was processing it).');
            $this->releaseUniqueJobLock(RunShopifyVideoPullJob::class, 'shopify-video-pull');
        }

        $job = $store->create($validated['skus'], 6);
        try {
            $this->dispatchShopifyVideoPullJob();
        } catch (\Throwable $e) {
            $store->markFailed('Could not queue worker: '.$e->getMessage());
            $this->shopifyVideoPullLogger()->error('Failed to queue Shopify video pull', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not queue Shopify video pull worker. Is the queue worker running?',
                'job' => $store->load(),
            ], 500);
        }
        $this->shopifyVideoPullLogger()->info('Shopify video pull queued', [
            'total' => $job['total'] ?? 0,
        ]);
        Log::info('VideoMaster: Shopify video pull queued', [
            'total' => $job['total'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Background Shopify video pull started.',
            'job' => $job,
        ]);
    }

    public function shopifyPullJobStatus(ShopifyVideoPullJobStore $store)
    {
        return response()->json([
            'success' => true,
            'job' => $store->load(),
        ]);
    }

    public function pauseShopifyPullJob(ShopifyVideoPullJobStore $store)
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

    public function resumeShopifyPullJob(ShopifyVideoPullJobStore $store)
    {
        $job = $store->update(function (array $state) {
            if (($state['status'] ?? 'idle') === 'paused') {
                $state['status'] = 'running';
                $state['last_message'] = 'Resumed Shopify video pull.';
            }

            return $state;
        });
        $store->appendMessage('Resumed Shopify video pull.', true);
        try {
            $this->dispatchShopifyVideoPullJob();
        } catch (\Throwable $e) {
            $this->shopifyVideoPullLogger()->warning('Resume could not re-queue Shopify video pull', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true, 'job' => $job]);
    }

    public function stopShopifyPullJob(ShopifyVideoPullJobStore $store)
    {
        // Force the job inactive immediately so Stop/Cancel always works — even if the worker is
        // gone and the job is stuck in "stopping"/"running" (which used to block new pulls forever).
        // A live worker checks isActive() each SKU and exits cleanly on its next iteration.
        $job = $store->forceStop('Stopped by user.');
        // Also release the ShouldBeUnique lock — otherwise it stays held (up to uniqueFor) and the
        // next pull dispatch is silently dropped even though the store is clear.
        $this->releaseUniqueJobLock(RunShopifyVideoPullJob::class, 'shopify-video-pull');

        return response()->json(['success' => true, 'job' => $job]);
    }

    /**
     * Release a ShouldBeUnique job's cache lock so a stale/cleared job does not block new
     * dispatches. The lock is normally released only when a job finishes through the queue, so
     * clearing a stuck job's store leaves it held — this clears it explicitly.
     */
    private function releaseUniqueJobLock(string $jobClass, string $uniqueId): void
    {
        try {
            \Illuminate\Support\Facades\Cache::lock('laravel_unique_job:'.$jobClass.':'.$uniqueId)->forceRelease();
        } catch (\Throwable) {
            // best-effort
        }
    }

    private function shopifyVideoPullLogger(): \Psr\Log\LoggerInterface
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/shopify-video-pull.log'),
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
    private function fetchShopifyVideosForSku(string $sku): array
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

        $videos = $this->fetchShopifyProductVideosViaGraphql($domain, (string) $token, (string) $productId);
        $source = 'shopify_admin_graphql';

        if ($videos === []) {
            $adminHandle = trim((string) ($productRes->json('product.handle') ?? ''));
            $publicVideos = $this->fetchPublicShopifyProductVideosForSku($sku, $adminHandle !== '' ? $adminHandle : null);
            if ($publicVideos !== []) {
                $videos = $publicVideos;
                $source = 'shopify_storefront';
            }
        }

        if ($videos === []) {
            $cachedVideos = $this->fetchCachedShopifyVideosForSku($sku);
            if ($cachedVideos !== []) {
                $videos = $cachedVideos;
                $source = 'shopify_catalog_cache';
            }
        }

        if ($videos === []) {
            Log::info('VideoMaster: no Shopify videos for SKU', [
                'sku' => $sku,
                'variant_id' => (string) $variantId,
                'product_id' => (string) $productId,
                'store' => $store,
            ]);

            return [
                'success' => false,
                'message' => 'No Shopify product videos found. This product may only have gallery images in Shopify Admin — add VIDEO or EXTERNAL_VIDEO media there first.',
                'variant_id' => (string) $variantId,
                'product_id' => (string) $productId,
            ];
        }

        return [
            'success' => true,
            'videos' => $videos,
            'variant_id' => (string) $variantId,
            'product_id' => (string) $productId,
            'store' => $store,
            'source' => $source,
        ];
    }

    /**
     * Fetch VIDEO / EXTERNAL_VIDEO media from Shopify Admin GraphQL (not product.images).
     *
     * @return list<string>
     */
    private function fetchShopifyProductVideosViaGraphql(string $domain, string $token, string $productId): array
    {
        $version = config('services.shopify.api_version', '2025-01');
        $gql = "https://{$domain}/admin/api/{$version}/graphql.json";
        $pgid = 'gid://shopify/Product/'.$productId;
        $query = <<<'GQL'
query($id: ID!) {
  product(id: $id) {
    media(first: 20) {
      nodes {
        mediaContentType
        ... on Video {
          sources { url mimeType }
          originalSource { url }
        }
        ... on ExternalVideo {
          originUrl
          embedUrl
        }
      }
    }
  }
}
GQL;

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(40)->post($gql, [
                'query' => $query,
                'variables' => ['id' => $pgid],
            ]);
        } catch (\Throwable $e) {
            Log::warning('VideoMaster: Shopify GraphQL video fetch failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('VideoMaster: Shopify GraphQL video fetch HTTP error', [
                'product_id' => $productId,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return [];
        }

        $gqlErrors = $response->json('errors') ?? [];
        if (is_array($gqlErrors) && $gqlErrors !== []) {
            Log::warning('VideoMaster: Shopify GraphQL video fetch returned errors', [
                'product_id' => $productId,
                'errors' => $gqlErrors,
            ]);
        }

        $nodes = $response->json('data.product.media.nodes') ?? [];
        if (! is_array($nodes)) {
            return [];
        }

        $urls = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $type = strtoupper((string) ($node['mediaContentType'] ?? ''));
            if ($type === 'VIDEO') {
                $added = false;
                foreach ($node['sources'] ?? [] as $source) {
                    if (! is_array($source)) {
                        continue;
                    }
                    $url = trim((string) ($source['url'] ?? ''));
                    if ($url !== '') {
                        $urls[] = $url;
                        $added = true;
                        break;
                    }
                }
                if (! $added) {
                    $orig = trim((string) ($node['originalSource']['url'] ?? ''));
                    if ($orig !== '') {
                        $urls[] = $orig;
                    }
                }
            } elseif ($type === 'EXTERNAL_VIDEO') {
                $origin = trim((string) ($node['originUrl'] ?? ''));
                if ($origin !== '') {
                    $urls[] = $origin;
                } else {
                    $embed = trim((string) ($node['embedUrl'] ?? ''));
                    if ($embed !== '') {
                        $urls[] = $embed;
                    }
                }
            }
        }

        return $this->dedupeVideoUrls($this->filterLikelyVideoUrls($urls));
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function filterLikelyVideoUrls(array $urls): array
    {
        return array_values(array_filter($urls, fn ($url) => $this->isLikelyVideoUrl((string) $url)));
    }

    private function isLikelyVideoUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (preg_match('#cdn\.shopify\.com/videos/#i', $url)) {
            return true;
        }
        if (preg_match('#\.(mp4|webm|mov|m4v|ogv)(\?|#|$)#i', $url)) {
            return true;
        }
        if (preg_match('#/(files|videos)/#i', $url) && preg_match('#\.(mp4|webm|mov|m4v)(\?|#|$)#i', $url)) {
            return true;
        }
        // YouTube / Vimeo external product videos
        if (preg_match('#(?:youtube\.com|youtu\.be|vimeo\.com)#i', $url)) {
            return true;
        }

        return false;
    }

    /**
     * @param  mixed  $images  Legacy — unused; kept for signature compatibility.
     * @return list<string>
     */
    private function extractShopifyVideoUrls($images): array
    {
        if (! is_array($images)) {
            return [];
        }

        $urls = [];
        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }
            $src = trim((string) ($image['src'] ?? ''));
            if ($src !== '' && $this->isLikelyVideoUrl($src)) {
                $urls[] = $src;
            }
        }

        return $this->dedupeVideoUrls($urls);
    }

    /**
     * @param  mixed  $media  Storefront product.js "media" array.
     * @return list<string>
     */
    private function extractShopifyStorefrontMediaVideoUrls($media): array
    {
        if (! is_array($media)) {
            return [];
        }

        $urls = [];
        foreach ($media as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (strtolower((string) ($item['media_type'] ?? '')) !== 'video') {
                continue;
            }

            $bestUrl = null;
            $bestHeight = -1;
            foreach ($item['sources'] ?? [] as $source) {
                if (! is_array($source)) {
                    continue;
                }
                $url = trim((string) ($source['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $mime = strtolower((string) ($source['mime_type'] ?? ''));
                if (str_contains($mime, 'mpegurl') || str_ends_with(strtolower($url), '.m3u8')) {
                    continue;
                }
                $height = (int) ($source['height'] ?? 0);
                if ($height >= $bestHeight) {
                    $bestHeight = $height;
                    $bestUrl = $url;
                }
            }

            if ($bestUrl !== null) {
                $urls[] = $bestUrl;
                continue;
            }

            $src = trim((string) ($item['src'] ?? ''));
            if ($src !== '' && $this->isLikelyVideoUrl($src)) {
                $urls[] = $src;
            }
        }

        return $this->dedupeVideoUrls($urls);
    }

    /**
     * @return list<string>
     */
    private function fetchPublicShopifyProductVideosForSku(string $sku, ?string $handleOverride = null): array
    {
        $handles = $this->resolveShopifyStorefrontHandlesForSku($sku, $handleOverride);

        if ($handles === []) {
            return [];
        }

        $domains = array_values(array_unique(array_filter([
            config('services.shopify_5core.domain'),
            'www.5core.com',
        ])));

        foreach ($handles as $handle) {
            foreach ($domains as $domain) {
                $domain = rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
                if ($domain === '') {
                    continue;
                }

                $url = "https://{$domain}/products/{$handle}.js";
                try {
                    $response = Http::timeout(30)->connectTimeout(15)->get($url);
                } catch (\Throwable $e) {
                    Log::warning('VideoMaster: public Shopify product fetch exception', [
                        'sku' => $sku,
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                if (! $response->successful()) {
                    continue;
                }

                $payload = $response->json();
                $videos = $this->extractShopifyStorefrontMediaVideoUrls($payload['media'] ?? []);
                if ($videos === []) {
                    $videos = $this->extractShopifyVideoUrls($payload['images'] ?? []);
                }
                $videos = $this->filterLikelyVideoUrls($videos);
                if ($videos !== []) {
                    Log::info('VideoMaster: found Shopify videos via storefront', [
                        'sku' => $sku,
                        'handle' => $handle,
                        'domain' => $domain,
                        'video_count' => count($videos),
                    ]);

                    return $videos;
                }
            }
        }

        return [];
    }

    private function resolveShopifyCatalogHandleForSku(string $sku): ?string
    {
        foreach ($this->resolveShopifyCatalogHandlesForSku($sku) as $handle) {
            return $handle;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function resolveShopifyCatalogHandlesForSku(string $sku): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }

        $rows = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->whereRaw('LOWER(TRIM(COALESCE(v.sku, \'\'))) = ?', [mb_strtolower(trim($sku))])
            ->orderByDesc('v.synced_at')
            ->orderByDesc('v.id')
            ->select('p.handle')
            ->get();

        $handles = [];
        foreach ($rows as $row) {
            $handle = trim((string) ($row->handle ?? ''));
            if ($handle === '' || in_array($handle, $handles, true)) {
                continue;
            }
            $handles[] = $handle;
            $stripped = preg_replace('/-\d+$/', '', $handle);
            if (is_string($stripped) && $stripped !== '' && $stripped !== $handle && ! in_array($stripped, $handles, true)) {
                $handles[] = $stripped;
            }
        }

        usort($handles, static function (string $a, string $b): int {
            $aSuffix = (int) preg_match('/-\d+$/', $a);
            $bSuffix = (int) preg_match('/-\d+$/', $b);
            if ($aSuffix !== $bSuffix) {
                return $aSuffix <=> $bSuffix;
            }

            return strlen($a) <=> strlen($b);
        });

        return $handles;
    }

    /**
     * @return list<string>
     */
    private function resolveShopifyStorefrontHandlesForSku(string $sku, ?string $handleOverride = null): array
    {
        $handles = [];
        foreach ([$handleOverride, ...$this->resolveShopifyCatalogHandlesForSku($sku)] as $candidate) {
            $candidate = trim((string) ($candidate ?? ''));
            if ($candidate !== '' && ! in_array($candidate, $handles, true)) {
                $handles[] = $candidate;
            }
            $stripped = preg_replace('/-\d+$/', '', $candidate);
            if (is_string($stripped) && $stripped !== '' && $stripped !== $candidate && ! in_array($stripped, $handles, true)) {
                $handles[] = $stripped;
            }
        }

        foreach ($this->fetchShopifyStorefrontHandlesBySkuSearch($sku) as $searchHandle) {
            if (! in_array($searchHandle, $handles, true)) {
                $handles[] = $searchHandle;
            }
        }

        return $handles;
    }

    /**
     * @return list<string>
     */
    private function fetchShopifyStorefrontHandlesBySkuSearch(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $domains = array_values(array_unique(array_filter([
            config('services.shopify_5core.domain'),
            'www.5core.com',
        ])));

        $lowerSku = mb_strtolower($sku);
        foreach ($domains as $domain) {
            $domain = rtrim(preg_replace('#^https?://#', '', (string) $domain), '/');
            if ($domain === '') {
                continue;
            }

            $url = 'https://'.$domain.'/search/suggest.json?'.http_build_query([
                'q' => $sku,
                'resources' => [
                    'type' => 'product',
                    'limit' => 8,
                    'options' => [
                        'unavailable_products' => 'last',
                        'fields' => 'title,product_type,variants.title,variants.sku,vendor',
                    ],
                ],
            ]);

            try {
                $response = Http::timeout(20)->connectTimeout(10)->get($url);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $products = $response->json('resources.results.products') ?? [];
            if (! is_array($products)) {
                continue;
            }

            $handles = [];
            foreach ($products as $product) {
                if (! is_array($product)) {
                    continue;
                }
                $handle = trim((string) ($product['handle'] ?? ''));
                if ($handle === '') {
                    continue;
                }
                $matched = false;
                foreach ($product['variants'] ?? [] as $variant) {
                    if (! is_array($variant)) {
                        continue;
                    }
                    if (mb_strtolower(trim((string) ($variant['sku'] ?? ''))) === $lowerSku) {
                        $matched = true;
                        break;
                    }
                }
                if ($matched && ! in_array($handle, $handles, true)) {
                    $handles[] = $handle;
                }
            }

            if ($handles !== []) {
                return $handles;
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function fetchCachedShopifyVideosForSku(string $sku): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }

        $select = [];
        foreach (['image_src', 'images', 'image_urls'] as $col) {
            if (Schema::hasColumn('shopify_catalog_products', $col)) {
                $select[] = 'p.'.$col;
            }
        }
        if ($select === []) {
            return [];
        }

        try {
            $row = DB::table('shopify_catalog_variants as v')
                ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
                ->whereRaw('LOWER(TRIM(COALESCE(v.sku, \'\'))) = ?', [mb_strtolower(trim($sku))])
                ->orderByDesc('v.synced_at')
                ->orderByDesc('v.id')
                ->select($select)
                ->first();
        } catch (\Throwable $e) {
            Log::warning('VideoMaster: cached Shopify video lookup failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $row) {
            return [];
        }

        $urls = [];
        if (Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
            $decoded = json_decode((string) ($row->image_urls ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $candidate = trim($item);
                        if ($this->isLikelyVideoUrl($candidate)) {
                            $urls[] = $candidate;
                        }
                    } elseif (is_array($item) && ! empty($item['src'])) {
                        $candidate = trim((string) $item['src']);
                        if ($this->isLikelyVideoUrl($candidate)) {
                            $urls[] = $candidate;
                        }
                    }
                }
            }
        }

        if ($urls === [] && Schema::hasColumn('shopify_catalog_products', 'images')) {
            $decoded = json_decode((string) ($row->images ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $candidate = trim($item);
                        if ($this->isLikelyVideoUrl($candidate)) {
                            $urls[] = $candidate;
                        }
                    } elseif (is_array($item) && ! empty($item['src'])) {
                        $candidate = trim((string) $item['src']);
                        if ($this->isLikelyVideoUrl($candidate)) {
                            $urls[] = $candidate;
                        }
                    }
                }
            }
        }

        if ($urls === [] && Schema::hasColumn('shopify_catalog_products', 'image_src') && ! empty($row->image_src)) {
            $candidate = trim((string) $row->image_src);
            if ($this->isLikelyVideoUrl($candidate)) {
                $urls[] = $candidate;
            }
        }

        return $this->filterLikelyVideoUrls($this->dedupeVideoUrls($urls));
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    private function dedupeVideoUrls(array $urls): array
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

        return array_slice($out, 0, self::PM_MAX_VIDEOS);
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
    private function productMasterVideoArray(?ProductMaster $product): array
    {
        if (! $product) {
            return [];
        }

        $urls = [];
        for ($i = 1; $i <= self::PM_MAX_VIDEOS; $i++) {
            $value = trim((string) ($product->{'video'.$i} ?? ''));
            if ($value !== '') {
                $urls[] = $value;
            }
        }

        if ($urls === [] && ! empty($product->main_video)) {
            $urls[] = trim((string) $product->main_video);
        }

        return $urls;
    }

    /**
     * @param  list<string>  $images
     * @return list<string>
     */
    private function normalizedVideoArray(array $images): array
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

    private function dispatchShopifyVideoPullJob(): void
    {
        RunShopifyVideoPullJob::dispatch();
    }

    private function dispatchVideoMasterPushJob(): void
    {
        RunVideoMasterPushJob::dispatch();
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

    /**
     * @param  list<mixed>  $videos
     * @return list<string>
     */
    private function normalizeVideoList(array $videos): array
    {
        $urls = [];
        foreach ($videos as $video) {
            if (is_string($video) && trim($video) !== '') {
                $urls[] = trim($video);
            } elseif (is_array($video)) {
                foreach (['url', 'locator', 'video_url', 'src'] as $key) {
                    if (! empty($video[$key]) && is_string($video[$key])) {
                        $urls[] = trim($video[$key]);
                        break;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }
}
