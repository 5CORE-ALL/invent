<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Channels\ChannelMasterController;
use App\Http\Controllers\Controller;
use App\Jobs\RunPricingErrorsFixPushJob;
use App\Models\ChannelMasterCalculatedData;
use App\Models\PricingErrorsFixCalculatedData;
use App\Services\PricingErrorsFixCvrCacheBuilder;
use App\Services\Support\PricingErrorsFixPushJobStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Pricing Errors Fix — unified Tabulator across all marketplace channels.
 *
 * Cache filled from /price-increase CVR bulk (not channel pricing tabulators).
 * Page reads pricing_errors_fix_calculated_data (instant).
 */
class PricingErrorsFixController extends Controller
{
    private const LOW_GROI_SKU_SIDEBAR_CACHE_KEY = 'pef_low_groi_sku_sidebar_count';

    /** @var array<string, float> */
    private array $adsPctCache = [];

    /** @var array<string, float> */
    private array $takeHomeCache = [];

    /**
     * Unique listed SKUs with GROI% &lt; 40 (same as page SKU badge, Listed default).
     */
    public static function lowGroiSkuCountForSidebar(): int
    {
        try {
            return (int) Cache::remember(self::LOW_GROI_SKU_SIDEBAR_CACHE_KEY, 300, function () {
                if (! Schema::hasTable('pricing_errors_fix_calculated_data')) {
                    return 0;
                }

                return (int) DB::table('pricing_errors_fix_calculated_data')
                    ->whereNotNull('groi')
                    ->where('groi', '<', 40)
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->where(function ($w) {
                        $w->where('price', '>', 0)->orWhere('sprice', '>', 0);
                    })
                    ->selectRaw('COUNT(DISTINCT sku) as c')
                    ->value('c');
            });
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function forgetLowGroiSkuSidebarCountCache(): void
    {
        try {
            Cache::forget(self::LOW_GROI_SKU_SIDEBAR_CACHE_KEY);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function index(Request $request): View
    {
        $channels = [];
        foreach ($this->channelRegistry() as $key => $cfg) {
            $channels[] = ['key' => $key, 'label' => $cfg['label']];
        }

        $lastCalc = null;
        $initialRows = [];
        try {
            if (Schema::hasTable('pricing_errors_fix_calculated_data')
                && PricingErrorsFixCalculatedData::hasData()) {
                $lastCalc = PricingErrorsFixCalculatedData::lastCalculatedAt();
                // Embed cache in HTML — no Ajax "load" on first paint
                $initialRows = $this->fetchCacheRows(
                    array_keys($this->channelRegistry()),
                    filter_var($request->query('listed_only', true), FILTER_VALIDATE_BOOLEAN)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('PricingErrorsFix index cache embed failed: '.$e->getMessage());
        }

        return view('market-places.pricing_errors_fix_view', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
            'channels' => $channels,
            'cache_calculated_at' => $lastCalc,
            'initial_rows' => $initialRows,
        ]);
    }

    /**
     * Aggregated rows: one row per SKU × channel.
     * Default = from pre-calculated table (instant).
     * Optional ?live=1 = live channel fan-out (slow).
     * Optional ?channel=amazon,ebay filters to a subset.
     */
    public function dataJson(Request $request): JsonResponse
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $live = filter_var($request->query('live', false), FILTER_VALIDATE_BOOLEAN);
            if (! $live && Schema::hasTable('pricing_errors_fix_calculated_data')
                && PricingErrorsFixCalculatedData::hasData()) {
                return $this->dataJsonFromCache($request);
            }

            // Empty-cache fallback: /price-increase CVR bulk (not channel pricing pages)
            $listedOnly = filter_var($request->query('listed_only', true), FILTER_VALIDATE_BOOLEAN);
            $wanted = $this->wantedChannelKeys($request);
            $built = app(PricingErrorsFixCvrCacheBuilder::class)->build($wanted, null, $listedOnly);

            return response()->json([
                'data' => $built['rows'],
                'meta' => [
                    'total' => count($built['rows']),
                    'channels' => $wanted,
                    'errors' => $built['errors'],
                    'source' => 'cvr-price-increase',
                    'calculated_at' => null,
                ],
            ])->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            Log::error('PricingErrorsFix dataJson: '.$e->getMessage());

            return response()->json(['error' => 'Failed to fetch pricing errors data', 'message' => $e->getMessage()], 500);
        }
    }

    private function dataJsonFromCache(Request $request): JsonResponse
    {
        $wanted = $this->wantedChannelKeys($request);
        $listedOnly = filter_var($request->query('listed_only', true), FILTER_VALIDATE_BOOLEAN);
        $out = $this->fetchCacheRows($wanted, $listedOnly);

        return response()->json([
            'data' => $out,
            'meta' => [
                'total' => count($out),
                'channels' => $wanted,
                'errors' => [],
                'source' => 'cache',
                'calculated_at' => PricingErrorsFixCalculatedData::lastCalculatedAt(),
            ],
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Plain DB read from pricing_errors_fix_calculated_data (no joins).
     *
     * @param  array<int, string>  $wantedKeys
     * @return array<int, array<string, mixed>>
     */
    private function fetchCacheRows(array $wantedKeys, bool $listedOnly = true): array
    {
        $registry = $this->channelRegistry();
        $mps = [];
        foreach ($wantedKeys as $key) {
            if (isset($registry[$key])) {
                $mps[] = $registry[$key]['marketplace'];
            }
        }

        $q = \Illuminate\Support\Facades\DB::table('pricing_errors_fix_calculated_data')
            ->select([
                'sku', 'marketplace', 'pull_key', 'channel_label', 'parent',
                'inv', 'ov_l30', 'l30', 'dil', 'price', 'groi', 'nroi', 'gpft', 'npft',
                'sprice', 'sroi', 'sgpft', 'snroi', 'snpft', 'success',
                'lp', 'ship', 'margin', 'ads_pct',
            ]);

        if ($mps !== []) {
            $q->whereIn('marketplace', $mps);
        }
        if ($listedOnly) {
            $q->where(function ($w) {
                $w->where('price', '>', 0)->orWhere('sprice', '>', 0);
            });
        }

        $out = [];
        foreach ($q->get() as $r) {
            $price = $r->price !== null ? (float) $r->price : 0.0;
            $sprice = $r->sprice !== null ? (float) $r->sprice : 0.0;
            $out[] = [
                'id' => $r->marketplace.'|'.$r->sku,
                'channel' => $r->channel_label,
                'channel_key' => $r->marketplace,
                'pull_key' => $r->pull_key ?: $r->marketplace,
                'marketplace' => $r->marketplace,
                'image_path' => null,
                'parent' => $r->parent,
                'sku' => $r->sku,
                'inv' => (float) $r->inv,
                'ov_l30' => (float) $r->ov_l30,
                'l30' => (float) ($r->l30 ?? 0),
                'dil' => $r->dil !== null ? (float) $r->dil : null,
                'price' => $price > 0 ? round($price, 2) : null,
                'groi' => $r->groi !== null ? (float) $r->groi : null,
                'nroi' => $r->nroi !== null ? (float) $r->nroi : null,
                'gpft' => $r->gpft !== null ? (float) $r->gpft : null,
                'npft' => $r->npft !== null ? (float) $r->npft : null,
                'sprice' => $sprice > 0 ? round($sprice, 2) : null,
                'sroi' => $r->sroi !== null ? (float) $r->sroi : null,
                'sgpft' => $r->sgpft !== null ? (float) $r->sgpft : null,
                'snroi' => $r->snroi !== null ? (float) $r->snroi : null,
                'snpft' => $r->snpft !== null ? (float) $r->snpft : null,
                'success' => $r->success,
                'lp' => (float) $r->lp,
                'ship' => (float) $r->ship,
                'margin' => (float) $r->margin,
                'ads_pct' => (float) $r->ads_pct,
                'goods_id' => null,
                'sku_id' => null,
                '_selected' => false,
            ];
        }

        return $this->enrichTemuPushIds($out);
    }

    /**
     * Attach Temu/Temu2 goods_id + sku_id for price push (cache table has no ID columns).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function enrichTemuPushIds(array $rows): array
    {
        $temuSkus = [];
        $temu2Skus = [];
        foreach ($rows as $r) {
            $mp = strtolower((string) ($r['marketplace'] ?? ''));
            $sku = trim((string) ($r['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            if ($mp === 'temu') {
                $temuSkus[strtoupper($sku)] = $sku;
            } elseif ($mp === 'temu2') {
                $temu2Skus[strtoupper($sku)] = $sku;
            }
        }

        $temuMap = [];
        if ($temuSkus !== [] && Schema::hasTable('temu_metrics')) {
            $cols = ['sku', 'goods_id'];
            if (Schema::hasColumn('temu_metrics', 'sku_id')) {
                $cols[] = 'sku_id';
            }
            // Case-insensitive match — PEF SKUs and temu_metrics casing can differ.
            $upperList = array_keys($temuSkus);
            foreach (DB::table('temu_metrics')->select($cols)
                ->whereIn(DB::raw('UPPER(TRIM(sku))'), $upperList)
                ->get() as $m) {
                $temuMap[strtoupper(trim((string) $m->sku))] = $m;
            }
        }

        $temu2Map = [];
        if ($temu2Skus !== [] && Schema::hasTable('temu2_metrics')) {
            $cols2 = ['sku', 'goods_id'];
            if (Schema::hasColumn('temu2_metrics', 'sku_id')) {
                $cols2[] = 'sku_id';
            }
            $upperList2 = array_keys($temu2Skus);
            foreach (DB::table('temu2_metrics')->select($cols2)
                ->whereIn(DB::raw('UPPER(TRIM(sku))'), $upperList2)
                ->get() as $m) {
                $temu2Map[strtoupper(trim((string) $m->sku))] = $m;
            }
        }

        foreach ($rows as &$r) {
            $mp = strtolower((string) ($r['marketplace'] ?? ''));
            $key = strtoupper(trim((string) ($r['sku'] ?? '')));
            $m = null;
            if ($mp === 'temu') {
                $m = $temuMap[$key] ?? null;
            } elseif ($mp === 'temu2') {
                $m = $temu2Map[$key] ?? null;
            }
            if (! $m) {
                continue;
            }
            $gid = trim((string) ($m->goods_id ?? ''));
            $sid = trim((string) ($m->sku_id ?? ''));
            if ($gid !== '') {
                $r['goods_id'] = $gid;
            }
            if ($sid !== '') {
                $r['sku_id'] = $sid;
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function wantedChannelKeys(Request $request): array
    {
        $registry = $this->channelRegistry();
        $filterRaw = trim((string) $request->query('channel', ''));
        if ($filterRaw === '') {
            return array_keys($registry);
        }

        return array_values(array_filter(
            array_map('strtolower', array_map('trim', explode(',', $filterRaw))),
            fn ($k) => isset($registry[$k])
        ));
    }

    /**
     * Build normalized rows from live channel controllers (used by artisan command + ?live=1).
     *
     * @param  array<int, string>  $wanted
     * @return array{rows: array<int, array<string, mixed>>, errors: array<string, string>, channels: array<int, string>}
     */
    public function buildNormalizedRows(array $wanted, Request $request, bool $listedOnly = true): array
    {
        $registry = $this->channelRegistry();
        $out = [];
        $errors = [];

        foreach ($wanted as $key) {
            if (! isset($registry[$key])) {
                continue;
            }
            $cfg = $registry[$key];
            try {
                $rows = $this->fetchChannelRows($cfg, $request);
                foreach ($rows as $raw) {
                    if (! is_array($raw) && ! is_object($raw)) {
                        continue;
                    }
                    $row = $this->normalizeRow($raw, $cfg, $key);
                    if ($row === null) {
                        continue;
                    }
                    if ($listedOnly && (float) ($row['price'] ?? 0) <= 0 && (float) ($row['sprice'] ?? 0) <= 0) {
                        continue;
                    }
                    $out[] = $row;
                }
            } catch (\Throwable $e) {
                Log::warning('PricingErrorsFix channel failed: '.$key.' — '.$e->getMessage());
                $errors[$key] = $e->getMessage();
            }
        }

        return ['rows' => $out, 'errors' => $errors, 'channels' => $wanted];
    }

    /** @return array<string, array{label:string,marketplace:string,price_keys:array<int,string>,fetch:callable}> */
    public function publicChannelRegistry(): array
    {
        return $this->channelRegistry();
    }

    /**
     * @return array{groi:?float,nroi:?float,gpft:?float,npft:?float,sroi:?float,sgpft:?float,snroi:?float,snpft:?float}
     */
    public function publicComputeMetrics(
        ?float $price,
        ?float $sprice,
        float $lp,
        float $ship,
        float $margin,
        float $adsPct
    ): array {
        return $this->computeChannelMetrics($price, $sprice, $lp, $ship, $margin, $adsPct);
    }

    /**
     * After SPRICE save/push — patch cache for that SKU×marketplace (queued, non-blocking).
     */
    public static function queueSkuRefresh(string $sku, string $marketplace, ?float $sprice = null): void
    {
        $sku = trim($sku);
        $marketplace = strtolower(trim($marketplace));
        if ($sku === '' || $marketplace === '') {
            return;
        }

        // Normalize aliases used by CVR save
        $aliases = [
            'ebay' => 'ebay',
            'ebay1' => 'ebay',
            'ebaytwo' => 'ebay2',
            'ebaythree' => 'ebay3',
            'tiktok1' => 'tiktok',
            'tiktokshop' => 'tiktok',
            'tiktokshop1' => 'tiktok',
            'tiktokshop2' => 'tiktok2',
            'bestbuyusa' => 'bestbuy',
            'macys' => 'macy',
            'purchasingpower' => 'ppower',
            'purchase' => 'ppower',
        ];
        $channel = $aliases[$marketplace] ?? $marketplace;

        try {
            $params = [
                '--sku' => $sku,
                '--channel' => $channel,
            ];
            if ($sprice !== null) {
                $params['--sprice'] = (string) $sprice;
            }
            // Prefer queue; fall back to sync in background-ish call
            try {
                Artisan::queue('pricing-errors:calculate-data', $params);
            } catch (\Throwable $e) {
                Artisan::call('pricing-errors:calculate-data', $params);
            }
        } catch (\Throwable $e) {
            Log::warning('PricingErrorsFix queueSkuRefresh failed', [
                'sku' => $sku,
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Channel list exposed for the UI filter dropdown.
     */
    public function channelsJson(): JsonResponse
    {
        $list = [];
        foreach ($this->channelRegistry() as $key => $cfg) {
            $list[] = [
                'key' => $key,
                'label' => $cfg['label'],
                'marketplace' => $cfg['marketplace'],
            ];
        }

        return response()->json($list);
    }

    /**
     * Queue selected PEF price pushes — worker retries transient failures in background.
     */
    public function queuePush(Request $request, PricingErrorsFixPushJobStore $store): JsonResponse
    {
        $items = $request->input('items', []);
        if (! is_array($items) || $items === []) {
            return response()->json(['success' => false, 'message' => 'No items to push'], 400);
        }

        $current = $store->load();
        if ($store->isActive($current) && ! $store->isStale($current)) {
            return response()->json(array_merge($store->toApiResponse($current), [
                'success' => false,
                'message' => 'A price push is already running. Wait for it to finish or cancel it.',
            ]), 409);
        }
        if ($store->isActive($current)) {
            $store->forceStop('Cleared a stale push job (no worker was processing it).');
            $this->releaseUniqueJobLock(RunPricingErrorsFixPushJob::class, 'pricing-errors-fix-push');
        }

        $tasks = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $tasks[] = [
                'row_id' => $item['row_id'] ?? null,
                'sku' => $item['sku'] ?? null,
                'marketplace' => $item['marketplace'] ?? null,
                'channel' => $item['channel'] ?? null,
                'price' => $item['price'] ?? null,
                'sprice' => $item['sprice'] ?? null,
                'self_pick_price' => $item['self_pick_price'] ?? null,
                'goods_id' => $item['goods_id'] ?? null,
                'sku_id' => $item['sku_id'] ?? null,
            ];
        }

        $job = $store->create($tasks);
        if ((int) ($job['total'] ?? 0) === 0) {
            return response()->json(['success' => false, 'message' => 'No valid push items (need SKU + price > 0)'], 400);
        }

        // Clear unique lock so a prior stuck queue job cannot block this run.
        $this->releaseUniqueJobLock(RunPricingErrorsFixPushJob::class, 'pricing-errors-fix-push');

        // Prefer DB queue when a dedicated worker/watchdog is alive…
        try {
            RunPricingErrorsFixPushJob::dispatch();
        } catch (\Throwable $e) {
            Log::warning('PEF push queue dispatch failed', ['error' => $e->getMessage()]);
        }

        // …but ALWAYS spawn a sync runner too. On many servers the
        // pricing-errors-fix-push queue has no worker, so jobs sat at 0/N forever.
        // Runner uses a process lock — only one will process tasks.
        $spawned = $this->spawnPricingErrorsFixPushWorker();
        if (! $spawned) {
            Log::error('PEF push sync spawn failed — push may stall until a queue worker picks it up');
        }

        $store->update(function (array $state) use ($spawned) {
            $state['last_message'] = $spawned
                ? 'Worker started — pushing '.$state['total'].' row(s)…'
                : 'Queued — waiting for worker (spawn failed; ensure queue:work --queue=pricing-errors-fix-push)';
            $state['worker_spawned_at'] = now()->toDateTimeString();

            return $state;
        });

        return response()->json(array_merge($store->toApiResponse($store->load()), [
            'success' => true,
            'message' => 'Price push started ('.$job['total'].' row(s)). Processing in background with retries…',
            'worker_spawned' => $spawned,
        ]));
    }

    /**
     * Spawn `pricing-errors:push-run --sync` detached so push works without a queue worker.
     */
    private function spawnPricingErrorsFixPushWorker(): bool
    {
        try {
            $php = PHP_BINARY ?: 'php';
            // Prefer CLI binary when PHP_BINARY is php-fpm
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/pricing-errors-fix-push.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' pricing-errors:push-run --sync', 'r'));

                return true;
            }

            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' pricing-errors:push-run --sync >> '.escapeshellarg($log).' 2>&1 &';
            exec($cmd);

            return true;
        } catch (\Throwable $e) {
            Log::warning('PEF push spawn failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function pushJobStatus(PricingErrorsFixPushJobStore $store): JsonResponse
    {
        $state = $store->load();

        // Auto-kick if job is running but nothing has progressed (no worker / spawn died).
        if ($store->isActive($state) && $this->pefPushLooksStuck($state)) {
            $this->releaseUniqueJobLock(RunPricingErrorsFixPushJob::class, 'pricing-errors-fix-push');
            $kicked = $this->spawnPricingErrorsFixPushWorker();
            $store->update(function (array $s) use ($kicked) {
                $s['last_message'] = $kicked
                    ? 'Worker re-started after stall — continuing push…'
                    : 'Push stalled — could not start worker. Click Cancel, then Push again, or run: php artisan pricing-errors:push-run --sync';
                $s['worker_spawned_at'] = now()->toDateTimeString();

                return $s;
            });
            $state = $store->load();
        }

        return response()->json($store->toApiResponse($state));
    }

    /**
     * Stuck = still running, no task advanced, and no update for ~45s.
     *
     * @param  array<string, mixed>  $state
     */
    private function pefPushLooksStuck(array $state): bool
    {
        if ((int) ($state['current_index'] ?? 0) > 0) {
            return false; // already making progress — don't re-spawn
        }
        if (($state['current_sku'] ?? null) !== null) {
            return false; // actively pushing first item
        }
        $updatedAt = $state['worker_spawned_at'] ?? $state['updated_at'] ?? $state['started_at'] ?? null;
        if (! is_string($updatedAt) || $updatedAt === '') {
            return true;
        }
        try {
            return abs(now()->diffInSeconds(\Illuminate\Support\Carbon::parse($updatedAt))) >= 45;
        } catch (\Throwable) {
            return true;
        }
    }

    public function cancelPush(PricingErrorsFixPushJobStore $store): JsonResponse
    {
        $job = $store->forceStop('Cancelled by user.');
        $this->releaseUniqueJobLock(RunPricingErrorsFixPushJob::class, 'pricing-errors-fix-push');

        return response()->json(array_merge($store->toApiResponse($job), [
            'success' => true,
            'message' => 'Push cancelled.',
        ]));
    }

    private function releaseUniqueJobLock(string $jobClass, string $uniqueId): void
    {
        try {
            Cache::lock('laravel_unique_job:'.$jobClass.':'.$uniqueId)->forceRelease();
        } catch (\Throwable) {
            // best-effort
        }
    }

    /**
     * @return array<string, array{label:string,marketplace:string,price_keys:array<int,string>,fetch:callable}>
     */
    private function channelRegistry(): array
    {
        return [
            'amazon' => [
                'label' => 'Amazon',
                'marketplace' => 'amazon',
                'price_keys' => ['price', 'A Price', 'amazon_price'],
                'fetch' => fn (Request $r) => app(OverallAmazonController::class)->amazonDataJson($r),
            ],
            'ebay' => [
                'label' => 'eBay 1',
                'marketplace' => 'ebay1',
                'price_keys' => ['eBay Price', 'ebay_price', 'price', 'Price'],
                'fetch' => fn (Request $r) => app(EbayController::class)->ebayDataJson($r),
            ],
            'ebay2' => [
                'label' => 'eBay 2',
                'marketplace' => 'ebay2',
                'price_keys' => ['eBay Price', 'ebay_price', 'price', 'Price'],
                'fetch' => fn (Request $r) => app(EbayTwoController::class)->getViewEbayData($r),
            ],
            'ebay3' => [
                'label' => 'eBay 3',
                'marketplace' => 'ebay3',
                'price_keys' => ['eBay Price', 'ebay_price', 'price', 'Price'],
                'fetch' => fn (Request $r) => app(EbayThreeController::class)->ebay3DataJson($r),
            ],
            'temu' => [
                'label' => 'Temu',
                'marketplace' => 'temu',
                'price_keys' => ['price', 'Price', 'temu_price', 'T Price'],
                'fetch' => fn (Request $r) => app(TemuController::class)->getTemuDecreaseData($r),
            ],
            'temu2' => [
                'label' => 'Temu 2',
                'marketplace' => 'temu2',
                'price_keys' => ['price', 'Price', 'temu_price', 'T Price'],
                'fetch' => fn (Request $r) => app(TemuController::class)->getTemu2DecreaseData($r),
            ],
            'doba' => [
                'label' => 'Doba',
                'marketplace' => 'doba',
                'price_keys' => ['Doba Price', 'price', 'Price', 'doba_price'],
                'fetch' => fn (Request $r) => app(DobaController::class)->getViewdobaData($r),
            ],
            'tiktok' => [
                'label' => 'TikTok 1',
                'marketplace' => 'tiktok',
                'price_keys' => ['price', 'Price', 'TT Price', 'tiktok_price'],
                'fetch' => fn (Request $r) => app(TikTokPricingController::class)->tiktokDataJson($r),
            ],
            'tiktok2' => [
                'label' => 'TikTok 2',
                'marketplace' => 'tiktok2',
                'price_keys' => ['price', 'Price', 'TT Price', 'tiktok_price'],
                'fetch' => fn (Request $r) => app(TikTokPricingController::class)->tiktok2DataJson($r),
            ],
            'bestbuy' => [
                'label' => 'Best Buy',
                'marketplace' => 'bestbuy',
                'price_keys' => ['price', 'Price', 'BB Price', 'bestbuy_price'],
                'fetch' => fn (Request $r) => app(BestBuyPricingController::class)->bestbuyDataJson($r),
            ],
            'macy' => [
                'label' => "Macy's",
                'marketplace' => 'macy',
                'price_keys' => ['price', 'Price', 'Macy Price', 'macy_price'],
                'fetch' => fn (Request $r) => app(MacyController::class)->macysDataJson($r),
            ],
            'reverb' => [
                'label' => 'Reverb',
                'marketplace' => 'reverb',
                'price_keys' => ['price', 'Price', 'Reverb Price', 'reverb_price'],
                'fetch' => fn (Request $r) => app(ReverbController::class)->reverbDataJson($r),
            ],
            'topdawg' => [
                'label' => 'TopDawg',
                'marketplace' => 'topdawg',
                'price_keys' => ['TD Price', 'price', 'Price'],
                'fetch' => fn (Request $r) => app(TopDawgPricingController::class)->dataJson($r),
            ],
            'walmart' => [
                'label' => 'Walmart',
                'marketplace' => 'walmart',
                'price_keys' => ['price', 'Price', 'Walmart Price', 'walmart_price'],
                'fetch' => fn (Request $r) => app(WalmartControllerMarket::class)->walmartDataJson($r),
            ],
            'sb2c' => [
                'label' => 'Shopify B2C',
                'marketplace' => 'sb2c',
                'price_keys' => ['price', 'Price', 'Shopify Price'],
                'fetch' => fn (Request $r) => app(Shopifyb2cController::class)->shopifyB2cDataJson(),
            ],
            'sb2b' => [
                'label' => 'Shopify B2B',
                'marketplace' => 'sb2b',
                'price_keys' => ['price', 'Price', 'Shopify Price'],
                'fetch' => fn (Request $r) => app(Shopifyb2bController::class)->shopifyB2bDataJson(),
            ],
            'ppower' => [
                'label' => 'Purchasing Power',
                'marketplace' => 'ppower',
                'price_keys' => ['price', 'Price', 'PP Price'],
                'fetch' => fn (Request $r) => app(PurchasingPowerController::class)->dataJson($r),
            ],
            'tiendamia' => [
                'label' => 'Tiendamia',
                'marketplace' => 'tiendamia',
                'price_keys' => ['price', 'Price'],
                'fetch' => fn (Request $r) => app(TiendamiaPricingController::class)->tiendamiaDataJson($r),
            ],
            'pls' => [
                'label' => 'PLS',
                'marketplace' => 'pls',
                'price_keys' => ['price', 'Price'],
                'fetch' => fn (Request $r) => app(PlsController::class)->pricingDataJson($r),
            ],
            'wayfair' => [
                'label' => 'Wayfair',
                'marketplace' => 'wayfair',
                'price_keys' => ['price', 'Price', 'Wayfair Price'],
                'fetch' => fn (Request $r) => app(WayfairController::class)->getWayfairPricingData($r),
            ],
            'shein' => [
                'label' => 'Shein',
                'marketplace' => 'shein',
                'price_keys' => ['price', 'Price', 'Shein Price'],
                'fetch' => fn (Request $r) => app(SheinController::class)->getSheinPricingData($r),
            ],
            'faire' => [
                'label' => 'Faire',
                'marketplace' => 'faire',
                'price_keys' => ['price', 'Price', 'Faire Price'],
                'fetch' => fn (Request $r) => app(FaireController::class)->getViewFaireData($r),
            ],
            'aliexpress' => [
                'label' => 'AliExpress',
                'marketplace' => 'aliexpress',
                'price_keys' => ['price', 'Price', 'AE Price'],
                'fetch' => fn (Request $r) => app(AliexpressController::class)->getViewAliexpressData($r),
            ],
        ];
    }

    /**
     * @param  array{fetch:callable}  $cfg
     * @return array<int, array<string, mixed>|object>
     */
    private function fetchChannelRows(array $cfg, Request $request): array
    {
        $response = ($cfg['fetch'])($request);
        if ($response instanceof JsonResponse) {
            $payload = json_decode($response->getContent(), true);
        } elseif (is_array($response)) {
            $payload = $response;
        } else {
            return [];
        }

        if (! is_array($payload)) {
            return [];
        }

        // Common shapes: [ {...} ], { data: [...] }, { data: { data: [...] } }
        if (isset($payload['data']) && is_array($payload['data'])) {
            $inner = $payload['data'];
            if (isset($inner['data']) && is_array($inner['data'])) {
                return array_values($inner['data']);
            }
            // Associative list of rows vs keyed meta
            if ($this->looksLikeRowList($inner)) {
                return array_values($inner);
            }
        }

        if ($this->looksLikeRowList($payload)) {
            return array_values($payload);
        }

        return [];
    }

    private function looksLikeRowList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        $first = reset($arr);
        if (! is_array($first) && ! is_object($first)) {
            return false;
        }
        // Reject meta-only objects
        if (is_array($first) && isset($first['error']) && count($first) <= 2) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|object  $raw
     * @param  array{label:string,marketplace:string,price_keys:array<int,string>}  $cfg
     * @return array<string, mixed>|null
     */
    private function normalizeRow($raw, array $cfg, string $pullKey = ''): ?array
    {
        $r = is_object($raw) ? (array) $raw : $raw;
        if (! is_array($r)) {
            return null;
        }

        $sku = (string) $this->pick($r, ['(Child) sku', 'sku', 'SKU', 'child_sku', 'Child sku'], '');
        $sku = trim($sku);
        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
            return null;
        }
        if (! empty($r['is_parent_summary'])) {
            return null;
        }

        $parent = $this->pick($r, ['Parent', 'parent', 'parent_sku'], null);
        $inv = $this->toFloat($this->pick($r, ['INV', 'inv', 'inventory', 'Inventory'], 0));
        $ovL30 = $this->toFloat($this->pick($r, ['L30', 'OV L30', 'ov_l30', 'ovl30', 'overall_l30'], 0));
        $dil = $this->pick($r, ['Dil', 'Dil%', 'dil', 'dil_percent', 'dil%'], null);
        if ($dil === null || $dil === '') {
            $dil = $inv > 0 ? round(($ovL30 / $inv) * 100, 0) : 0;
        } else {
            $dil = $this->toFloat($dil);
        }

        $price = $this->extractChannelPrice($r, $cfg['price_keys'] ?? []);
        $sprice = $this->nullableFloat($this->pick($r, ['SPRICE', 'sprice', 'SPrice'], null));

        $success = $this->pick($r, ['SPRICE_STATUS', 'PUSH_STATUS', 'push_status', 'success', 'Success'], null);
        if (is_string($success)) {
            $success = trim($success);
            if ($success === '') {
                $success = null;
            }
        }

        $image = $this->pick($r, ['image_path', 'Image', 'image', 'image_src'], null);
        $lp = $this->toFloat($this->pick($r, ['LP_productmaster', 'lp', 'LP'], 0));
        $ship = $this->toFloat($this->pick($r, ['Ship_productmaster', 'ship', 'Ship', 'temu_ship'], 0));
        $rowMargin = $this->toFloat($this->pick($r, ['percentage', 'margin'], 0));
        $goodsId = $this->pick($r, ['goods_id', 'temu_goods_id', 'Goods ID', 'goodsId'], null);
        $skuId = $this->pick($r, ['sku_id', 'temu_sku_id', 'skuId'], null);
        if (is_scalar($goodsId)) {
            $goodsId = trim((string) $goodsId);
            if ($goodsId === '') {
                $goodsId = null;
            }
        } else {
            $goodsId = null;
        }
        if (is_scalar($skuId)) {
            $skuId = trim((string) $skuId);
            if ($skuId === '') {
                $skuId = null;
            }
        } else {
            $skuId = null;
        }

        // Channel-wise take-home + Ads% — same formulas as amazon/ebay/temu tabulators
        $marketplace = (string) $cfg['marketplace'];
        $channelL30 = $this->extractChannelL30($r, $marketplace);
        $margin = $this->resolveTakeHome($marketplace, $rowMargin);
        $adsPct = $this->resolveAdsPercent($marketplace);

        // When SPRICE empty, channels default SPRICE = price for suggested columns
        $spriceForCalc = ($sprice !== null && $sprice > 0) ? $sprice : ($price > 0 ? $price : null);

        $calc = $this->computeChannelMetrics(
            $price > 0 ? $price : null,
            $spriceForCalc,
            $lp,
            $ship,
            $margin,
            $adsPct
        );

        return [
            'id' => $cfg['marketplace'].'|'.$sku,
            'channel' => $cfg['label'],
            'channel_key' => $cfg['marketplace'],
            'pull_key' => $pullKey !== '' ? $pullKey : $cfg['marketplace'],
            'marketplace' => $cfg['marketplace'],
            'image_path' => $image,
            'parent' => $parent,
            'sku' => $sku,
            'inv' => $inv,
            'ov_l30' => $ovL30,
            'l30' => $channelL30,
            'dil' => $dil,
            'price' => $price > 0 ? round($price, 2) : null,
            'groi' => $calc['groi'],
            'nroi' => $calc['nroi'],
            'gpft' => $calc['gpft'],
            'npft' => $calc['npft'],
            'sprice' => $sprice !== null && $sprice > 0 ? round($sprice, 2) : null,
            // Sroi = SGROI (gross); Snroi = SROI (net after Ads%)
            'sroi' => $calc['sroi'],
            'sgpft' => $calc['sgpft'],
            'snroi' => $calc['snroi'],
            'snpft' => $calc['snpft'],
            'success' => $success,
            'lp' => $lp,
            'ship' => $ship,
            'margin' => $margin,
            'ads_pct' => $adsPct,
            'goods_id' => $goodsId,
            'sku_id' => $skuId,
            '_selected' => false,
        ];
    }

    /**
     * Channel take-home decimal used in GPFT/GROI/S* formulas.
     * Amazon always 0.80 (matches OverallAmazonController / amazon-tabulator-view).
     */
    private function resolveTakeHome(string $marketplace, float $rowMargin): float
    {
        $mp = strtolower(trim($marketplace));
        if ($mp === 'amazon') {
            return 0.80;
        }

        $m = $rowMargin;
        if ($m > 1) {
            $m = $m / 100;
        }
        if ($m > 0 && $m <= 1) {
            return $m;
        }

        if (isset($this->takeHomeCache[$mp])) {
            return $this->takeHomeCache[$mp];
        }

        $defaults = [
            'ebay' => 0.85, 'ebay1' => 0.85, 'ebay2' => 0.85, 'ebay3' => 0.85,
            'temu' => 0.87, 'temu2' => 0.87,
            'doba' => 0.95, // same as /price-increase & /doba-tabulator
            'tiktok' => 0.85, 'tiktok2' => 0.85,
            'walmart' => 0.85,
            'bestbuy' => 0.85, 'macy' => 0.85, 'reverb' => 0.85,
            'topdawg' => 0.85, 'sb2c' => 0.85, 'sb2b' => 0.85,
            'ppower' => 0.85, 'shein' => 0.85, 'faire' => 0.85,
            'aliexpress' => 0.85, 'wayfair' => 0.85, 'tiendamia' => 0.85, 'pls' => 0.85,
        ];

        return $this->takeHomeCache[$mp] = $defaults[$mp] ?? 0.80;
    }

    /**
     * Channel Ads% (TACOS) — used for NPFT = GPFT − Ads% and net ROI.
     */
    private function resolveAdsPercent(string $marketplace): float
    {
        $mp = strtolower(trim($marketplace));
        if (isset($this->adsPctCache[$mp])) {
            return $this->adsPctCache[$mp];
        }

        // Channels with no ads deduction (same as CVR master / channel tabulators)
        $noAds = [
            'doba', 'bestbuy', 'macy', 'topdawg', 'shein', 'faire', 'aliexpress',
            'ppower', 'sb2c', 'sb2b', 'temu2', 'wayfair', 'tiendamia', 'pls', 'reverb',
        ];
        if (in_array($mp, $noAds, true)) {
            return $this->adsPctCache[$mp] = 0.0;
        }

        try {
            if (in_array($mp, ['ebay', 'ebay1', 'ebay2', 'ebay3'], true)) {
                $ads = (float) app(ChannelMasterController::class)->getEbayMasterAdsPercent();

                return $this->adsPctCache[$mp] = $ads;
            }
        } catch (\Throwable $e) {
            Log::debug('PricingErrorsFix ebay ads: '.$e->getMessage());
        }

        $names = match ($mp) {
            'amazon' => ['Amazon'],
            'temu' => ['Temu'],
            'tiktok' => ['TikTok', 'Tiktok', 'TikTokShop'],
            'tiktok2' => ['TikTok 2', 'Tiktok2', 'TikTokShop2'],
            'walmart' => ['Walmart'],
            default => [ucfirst($mp)],
        };

        $ads = 0.0;
        foreach ($names as $name) {
            $v = ChannelMasterCalculatedData::where('channel', $name)->value('ads_percentage');
            if ($v === null) {
                $v = ChannelMasterCalculatedData::where('channel', 'like', $name.'%')->value('ads_percentage');
            }
            if ($v !== null && is_numeric($v)) {
                $ads = (float) $v;
                break;
            }
        }

        return $this->adsPctCache[$mp] = $ads;
    }

    /**
     * Same formulas as amazon-tabulator-view / OverallAmazonController:
     *   GPFT%  = ((price × margin − ship − lp) / price) × 100
     *   GROI%  = ((price × margin − ship − lp) / lp) × 100
     *   NPFT%  = GPFT% − Ads%
     *   NROI%  = (gross$ − price×Ads%/100) / lp × 100
     * Suggested columns use SPRICE the same way (SGPFT / Sroi=SGROI / Snpft / Snroi).
     *
     * @return array{groi:?float,nroi:?float,gpft:?float,npft:?float,sroi:?float,sgpft:?float,snroi:?float,snpft:?float}
     */
    private function computeChannelMetrics(
        ?float $price,
        ?float $sprice,
        float $lp,
        float $ship,
        float $margin,
        float $adsPct
    ): array {
        $out = [
            'groi' => null, 'nroi' => null, 'gpft' => null, 'npft' => null,
            'sroi' => null, 'sgpft' => null, 'snroi' => null, 'snpft' => null,
        ];

        if (! ($margin > 0 && $margin <= 1) || ! ($lp > 0)) {
            return $out;
        }

        if ($price !== null && $price > 0) {
            $gross = ($price * $margin) - $ship - $lp;
            $out['gpft'] = round(($gross / $price) * 100, 2);
            $out['groi'] = round(($gross / $lp) * 100, 2);
            $out['npft'] = round($out['gpft'] - $adsPct, 2);
            $adSpend = $price * ($adsPct / 100);
            $out['nroi'] = round((($gross - $adSpend) / $lp) * 100, 2);
        }

        if ($sprice !== null && $sprice > 0) {
            $gross = ($sprice * $margin) - $ship - $lp;
            $out['sgpft'] = round(($gross / $sprice) * 100, 2);
            $out['sroi'] = round(($gross / $lp) * 100, 2); // SGROI (gross)
            $out['snpft'] = round($out['sgpft'] - $adsPct, 2); // SPFT / Spft%
            $adSpend = $sprice * ($adsPct / 100);
            $out['snroi'] = round((($gross - $adSpend) / $lp) * 100, 2); // SROI (net)
        }

        return $out;
    }

    /**
     * Channel-specific L30 sold qty (not Shopify overall ov_l30).
     * Keys match each marketplace pricing tabulator / CVR breakdown `l30`.
     *
     * @param  array<string, mixed>  $row
     */
    private function extractChannelL30(array $row, string $marketplace): float
    {
        $mp = strtolower(trim($marketplace));
        $keysByMp = [
            'amazon' => ['A_L30', 'a_l30', 'units_ordered_l30', 'l30'],
            'ebay' => ['eBay L30', 'Ebay L30', 'ebay_l30', 'l30'],
            'ebay1' => ['eBay L30', 'Ebay L30', 'ebay_l30', 'l30'],
            'ebay2' => ['eBay L30', 'Ebay L30', 'ebay_l30', 'l30'],
            'ebay3' => ['eBay L30', 'Ebay L30', 'ebay_l30', 'l30'],
            'temu' => ['temu_l30', 'l30'],
            'temu2' => ['temu_l30', 'l30'],
            'doba' => ['doba L30', 'doba_l30', 'quantity_l30', 'l30'],
            'tiktok' => ['TT L30', 'tt_l30', 'l30'],
            'tiktok2' => ['TT L30', 'tt_l30', 'l30'],
            'bestbuy' => ['BB L30', 'bb_l30', 'm_l30', 'l30'],
            'macy' => ['MC L30', 'mc_l30', 'm_l30', 'l30'],
            'reverb' => ['RV L30', 'rv_l30', 'r_l30', 'l30'],
            'topdawg' => ['TD L30', 'td_l30', 'r_l30', 'l30'],
            'walmart' => ['W_L30', 'w_l30', 'sheet_l30', 'l30'],
            'sb2c' => ['B2B L30', 'b2c_l30', 'l30'],
            'sb2b' => ['B2B L30', 'b2b_l30', 'l30'],
            'ppower' => ['PP L30', 'pp_l30', 'm_l30', 'l30'],
            'tiendamia' => ['M L30', 'm_l30', 'l30'],
            'pls' => ['pls_l30', 'p_l30', 'l30'],
            'wayfair' => ['al30', 'l30'],
            'shein' => ['al30', 'l30'],
            'faire' => ['al30', 'A L30', 'l30'],
            'aliexpress' => ['al30', 'A L30', 'l30'],
        ];

        $keys = $keysByMp[$mp] ?? ['l30', 'channel_l30'];

        return $this->toFloat($this->pick($row, $keys, 0));
    }

    /**
     * Resolve listing price from channel-specific keys, then any *price* field
     * (excluding SPRICE / LMP / STANDARD / FBA / yesterday helpers).
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $preferredKeys
     */
    private function extractChannelPrice(array $row, array $preferredKeys): float
    {
        $defaults = [
            'price', 'Price', 'Prc', 'PRC',
            'eBay Price', 'ebay_price', 'Ebay Price',
            'TD Price', 'temu_price', 'Temu Price', 'T Price', 'fb_price', 'FB Price', 'base_price',
            'Doba Price', 'doba_price',
            'TT Price', 'tiktok_price', 'BB Price', 'bestbuy_price',
            'Macy Price', 'macy_price', 'Reverb Price', 'reverb_price',
            'Walmart Price', 'walmart_price', 'Shopify Price', 'PP Price',
            'Wayfair Price', 'Shein Price', 'Faire Price', 'AE Price', 'A Price', 'amazon_price',
        ];
        $keys = array_values(array_unique(array_merge($preferredKeys, $defaults)));
        $direct = $this->toFloat($this->pick($row, $keys, 0));
        if ($direct > 0) {
            return $direct;
        }

        $skip = [
            'sprice', 's_price', 'standard_price', 'lmp_price', 'fba_price',
            'price_yesterday', 'price_lmpa', 'a_price', 'e_price', 'e2_price',
            'temu1_price', 'temu1_base_price', 'recommended_base_price',
            'has_custom_sprice', 'sprice_status',
        ];
        foreach ($row as $k => $v) {
            $lk = strtolower(trim((string) $k));
            if ($lk === '' || ! str_contains($lk, 'price')) {
                continue;
            }
            if (in_array($lk, $skip, true)) {
                continue;
            }
            if (str_contains($lk, 'sprice') || str_contains($lk, 'lmp') || str_contains($lk, 'standard')) {
                continue;
            }
            $n = $this->toFloat($v);
            if ($n > 0) {
                return $n;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     * @param  mixed  $default
     * @return mixed
     */
    private function pick(array $row, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        // Case-insensitive fallback
        $lowerMap = [];
        foreach ($row as $k => $v) {
            $lowerMap[strtolower((string) $k)] = $v;
        }
        foreach ($keys as $key) {
            $lk = strtolower($key);
            if (array_key_exists($lk, $lowerMap) && $lowerMap[$lk] !== null && $lowerMap[$lk] !== '') {
                return $lowerMap[$lk];
            }
        }

        return $default;
    }

    private function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_string($value)) {
            $value = str_replace([',', '%', '$'], '', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace([',', '%', '$'], '', $value);
        }
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
