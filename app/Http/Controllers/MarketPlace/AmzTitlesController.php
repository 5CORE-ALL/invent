<?php

namespace App\Http\Controllers\MarketPlace;

use App\Console\Commands\PullAmazonTitlesCommand;
use App\Http\Controllers\AmazonAdsMissingController;
use App\Http\Controllers\Controller;
use App\Models\AmazonAdsMissingLink;
use App\Models\AmazonDatasheet;
use App\Models\AmazonSkuCompetitor;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\LmpSkuGroupService;
use App\Support\OpenAiRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmzTitlesController extends Controller
{
    /** Selected pulls at or below this count run inline (avoids stuck background spawn). */
    private const SYNC_PULL_SKU_LIMIT = 40;

    public function index()
    {
        return view('market-places.amz_titles', [
            'defaultAiPrompt' => $this->defaultAiAnalyzePrompt(),
        ]);
    }

    /**
     * Pull Amazon item_name → product_master.title150 (Current Title 170).
     * Small selections run synchronously; larger ones spawn background artisan.
     */
    public function startPull(Request $request)
    {
        $status = PullAmazonTitlesCommand::status();
        $probe = Cache::lock(PullAmazonTitlesCommand::LOCK_CACHE_KEY, 5);
        $lockFree = $probe->get();
        if ($lockFree) {
            $probe->release();
        }
        if (! empty($status['running']) && ! $lockFree) {
            return response()->json([
                'success' => true,
                'already_running' => true,
                'status' => $status,
                'message' => $status['message'] ?? 'Titles pull already running',
            ]);
        }
        if (! empty($status['running']) && $lockFree) {
            PullAmazonTitlesCommand::writeStatus([
                'running' => false,
                'message' => 'Previous pull flag cleared (stale)',
            ]);
        }

        $skus = $request->input('skus', []);
        if (is_string($skus)) {
            $decoded = json_decode($skus, true);
            $skus = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($skus)) {
            $skus = [];
        }
        $skus = array_values(array_unique(array_filter(array_map(static function ($s) {
            $s = trim((string) $s);

            return ($s !== '' && ! str_starts_with(strtoupper($s), 'PARENT')) ? $s : null;
        }, $skus))));

        if ($skus === []) {
            return response()->json([
                'success' => false,
                'error' => 'Select one or more rows first. Pull Titles only runs for selected SKUs.',
            ], 422);
        }

        $dir = storage_path('app/titles-pull');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $skusFile = $dir.'/skus-'.uniqid('', true).'.txt';
        file_put_contents($skusFile, implode("\n", $skus)."\n");

        $count = count($skus);
        $runSync = $count <= self::SYNC_PULL_SKU_LIMIT;

        PullAmazonTitlesCommand::writeStatus([
            'running' => true,
            'total' => $count,
            'done' => 0,
            'ok' => 0,
            'fail' => 0,
            'lot' => min(20, max(1, $count)),
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'message' => $runSync
                ? "Pulling Amazon titles for {$count} selected SKU(s)…"
                : "Starting background Amazon titles pull for {$count} selected SKU(s)…",
        ]);

        if ($runSync) {
            return $this->runPullSynchronously($skusFile, $count);
        }

        return $this->spawnPullInBackground($skusFile, $count);
    }

    /**
     * Run pull in this request (reliable for 1–40 SKUs; background exec often never starts).
     */
    protected function runPullSynchronously(string $skusFile, int $count)
    {
        @set_time_limit(max(120, $count * 15));

        try {
            $exit = Artisan::call('amazon:pull-titles', [
                '--lot' => min(20, max(1, $count)),
                '--skus-file' => $skusFile,
                '--skip-inv-check' => true,
                '--delay-ms' => $count <= 5 ? 300 : 800,
                '--lot-pause-ms' => 500,
            ]);
            $output = trim((string) Artisan::output());
            $status = PullAmazonTitlesCommand::status();

            if ($exit !== 0 && empty($status['finished_at'])) {
                PullAmazonTitlesCommand::writeStatus([
                    'running' => false,
                    'finished_at' => now()->toDateTimeString(),
                    'message' => 'Pull failed (exit '.$exit.'). '.mb_substr($output, 0, 240),
                ]);
                $status = PullAmazonTitlesCommand::status();
            }

            Log::info('AmzTitles sync pull finished', [
                'sku_count' => $count,
                'exit' => $exit,
                'ok' => $status['ok'] ?? null,
                'fail' => $status['fail'] ?? null,
            ]);

            return response()->json([
                'success' => ($exit === 0),
                'sync' => true,
                'message' => $status['message'] ?? ($exit === 0 ? 'Pull complete' : 'Pull failed'),
                'status' => $status,
                'output' => mb_substr($output, 0, 500),
            ], $exit === 0 ? 200 : 500);
        } catch (\Throwable $e) {
            Log::error('AmzTitles sync pull failed', ['error' => $e->getMessage()]);
            PullAmazonTitlesCommand::writeStatus([
                'running' => false,
                'finished_at' => now()->toDateTimeString(),
                'message' => 'Pull failed: '.$e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'sync' => true,
                'error' => $e->getMessage(),
                'status' => PullAmazonTitlesCommand::status(),
            ], 500);
        } finally {
            if (is_file($skusFile)) {
                @unlink($skusFile);
            }
        }
    }

    /**
     * Spawn amazon:pull-titles in background (large selections).
     */
    protected function spawnPullInBackground(string $skusFile, int $count)
    {
        $php = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $args = [
            $php,
            $artisan,
            'amazon:pull-titles',
            '--lot=20',
            '--skus-file='.$skusFile,
            '--skip-inv-check',
        ];

        $cmd = implode(' ', array_map('escapeshellarg', $args));
        $logFile = storage_path('logs/amazon-titles-pull.log');
        $cleanup = '; rm -f '.escapeshellarg($skusFile);
        $full = 'nohup '.$cmd.$cleanup.' >> '.escapeshellarg($logFile).' 2>&1 &';

        try {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                pclose(popen('start /B '.$cmd.' >> '.escapeshellarg($logFile).' 2>&1', 'r'));
            } else {
                exec($full);
            }
        } catch (\Throwable $e) {
            Log::error('AmzTitles startPull spawn failed', ['error' => $e->getMessage()]);
            if (is_file($skusFile)) {
                @unlink($skusFile);
            }
            PullAmazonTitlesCommand::writeStatus([
                'running' => false,
                'message' => 'Failed to start: '.$e->getMessage(),
                'finished_at' => now()->toDateTimeString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to start background pull: '.$e->getMessage(),
            ], 500);
        }

        // If background never touches status, clear stuck "Starting…" after a short wait.
        usleep(400000);
        $after = PullAmazonTitlesCommand::status();
        $msg = (string) ($after['message'] ?? '');
        if (! empty($after['running']) && str_contains($msg, 'Starting background')) {
            // Fall back to sync for reliability when spawn is a no-op.
            return $this->runPullSynchronously($skusFile, $count);
        }

        Log::info('AmzTitles background pull started', [
            'sku_filter_count' => $count,
            'skus_file' => $skusFile,
        ]);

        return response()->json([
            'success' => true,
            'sync' => false,
            'message' => "Background pull started for {$count} selected SKU(s) → Current Title 170",
            'status' => PullAmazonTitlesCommand::status(),
        ]);
    }

    /**
     * Poll titles pull progress; auto-clear stale "running" if lock is free and stuck.
     */
    public function pullStatus()
    {
        $status = PullAmazonTitlesCommand::status();
        if (! empty($status['running'])) {
            $probe = Cache::lock(PullAmazonTitlesCommand::LOCK_CACHE_KEY, 5);
            $lockFree = $probe->get();
            if ($lockFree) {
                $probe->release();
            }
            $started = isset($status['started_at']) ? strtotime((string) $status['started_at']) : false;
            $stale = $lockFree && $started && (time() - $started) > 90
                && (int) ($status['done'] ?? 0) === 0
                && (int) ($status['total'] ?? 0) === 0;
            if ($stale) {
                PullAmazonTitlesCommand::writeStatus([
                    'running' => false,
                    'finished_at' => now()->toDateTimeString(),
                    'message' => 'Pull aborted: background worker never started (stale status cleared). Try again.',
                ]);
                $status = PullAmazonTitlesCommand::status();
            }
        }

        return response()->json([
            'success' => true,
            'status' => $status,
        ]);
    }

    /**
     * Tabulator JSON — Product Master child rows + parent summary rows
     * (same pattern as Analytics Amz), with Title 170 from product_master.title150.
     */
    public function data(Request $request)
    {
        $productMasters = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['id', 'parent', 'sku', 'main_image', 'title150']);

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyBySku = ShopifySku::mapByProductSkus($skus);

        $amzL30BySku = [];
        $amazonTitleBySku = [];
        $asinBySku = [];
        if ($skus !== []) {
            foreach (array_chunk($skus, 500) as $skuChunk) {
                AmazonDatasheet::query()
                    ->whereIn('sku', $skuChunk)
                    ->select('id', 'sku', 'asin', 'units_ordered_l30', 'amazon_title')
                    ->orderBy('id')
                    ->get()
                    ->each(function ($row) use (&$amzL30BySku, &$amazonTitleBySku, &$asinBySku) {
                        $key = strtoupper(trim((string) $row->sku));
                        if ($key === '') {
                            return;
                        }
                        if (! isset($amzL30BySku[$key])) {
                            $amzL30BySku[$key] = (float) ($row->units_ordered_l30 ?? 0);
                        }
                        if (! isset($asinBySku[$key]) && ! empty($row->asin)) {
                            $asinBySku[$key] = trim((string) $row->asin);
                        }
                        if (! isset($amazonTitleBySku[$key])) {
                            $t = trim((string) ($row->amazon_title ?? ''));
                            if ($t !== '') {
                                $amazonTitleBySku[$key] = $t;
                            }
                        }
                    });
            }
        }

        // LMP Amazon competitors (amazon_sku_competitors), same source as Analytics Amz LMP.
        $lmpLookup = AmazonSkuCompetitor::buildGroupedLookup('amazon');
        $lmpDetailsLookup = $lmpLookup['details'];
        $lmpSkuGroupService = app(LmpSkuGroupService::class);
        try {
            $lmpSkuGroupService->prepareForSkus($skus);
        } catch (\Throwable $e) {
            Log::warning('AmzTitles LMP group prepare failed', ['error' => $e->getMessage()]);
        }

        $childRows = [];
        foreach ($productMasters as $pm) {
            $sku = trim((string) ($pm->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $skuKey = strtoupper($sku);
            $parent = preg_replace('/\s+/', ' ', trim((string) ($pm->parent ?? '')));
            $shopify = $shopifyBySku[$sku] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            $ovL30 = (float) ($shopify->quantity ?? 0);
            $amzL30 = (float) ($amzL30BySku[$skuKey] ?? 0);
            $dilPct = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0;
            $title150 = trim((string) ($pm->title150 ?? ''));
            $amazonTitle = $amazonTitleBySku[$skuKey] ?? '';
            $asin = $asinBySku[$skuKey] ?? null;

            $linkedLmpSkus = $lmpSkuGroupService->groupContaining($sku);
            if ($linkedLmpSkus === []) {
                $linkedLmpSkus = [$sku];
            }
            $allLmpEntries = collect();
            foreach ($linkedLmpSkus as $linkedSku) {
                $lookupKey = AmazonSkuCompetitor::normalizeSkuKey($linkedSku);
                $entries = $lmpDetailsLookup->get($lookupKey);
                if ($entries instanceof \Illuminate\Support\Collection) {
                    $allLmpEntries = $allLmpEntries->merge($entries);
                }
            }
            $allLmpEntries = AmazonSkuCompetitor::dedupeByAsin($allLmpEntries);
            $lowestLmp = AmazonSkuCompetitor::lowestFromCollection($allLmpEntries)
                ?: $allLmpEntries->first();
            $lmpPrice = ($lowestLmp && is_numeric($lowestLmp->price ?? null))
                ? (float) $lowestLmp->price
                : null;

            $childRows[] = [
                'parent' => $parent,
                'sku' => $sku,
                'asin' => $asin,
                'buyer_link' => $asin ? ('https://www.amazon.com/dp/'.$asin) : null,
                'inv' => $inv,
                'ov_l30' => $ovL30,
                'dil_pct' => $dilPct,
                'amz_l30' => $amzL30,
                'image' => $pm->main_image ?: null,
                'title150' => $title150 !== '' ? $title150 : null,
                'amazon_title' => $amazonTitle !== '' ? $amazonTitle : null,
                'lmp_price' => $lmpPrice,
                'lmp_count' => $allLmpEntries->count(),
                'lmp_title' => $lowestLmp->product_title ?? null,
                'lmp_asin' => $lowestLmp->asin ?? null,
                'lmp_link' => $lowestLmp->product_link ?? null,
                'linked_lmp_skus' => array_values($linkedLmpSkus),
                'top_keywords' => [],
                'negative_keywords' => [],
                'is_parent_summary' => false,
            ];
        }

        // Title 170 / image for PARENT {parent} SKUs in product_master (if present).
        $parentNames = collect($childRows)
            ->pluck('parent')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Top 20 Amazon Ads keywords per parent (L30 impressions).
        $keywordsByParent = $this->topKeywordsByParents($parentNames);
        // Top 30 negative KW candidates (cached AI suggestions per parent).
        $negByParent = $this->cachedNegativeKeywordsByParents($parentNames);
        foreach ($childRows as &$childRow) {
            $p = (string) ($childRow['parent'] ?? '');
            $childRow['top_keywords'] = $keywordsByParent[$p] ?? [];
            $childRow['negative_keywords'] = $negByParent[$p] ?? [];
        }
        unset($childRow);

        $parentSkuKeys = array_map(fn ($p) => 'PARENT '.$p, $parentNames);
        $parentPmBySku = [];
        if ($parentSkuKeys !== []) {
            foreach (array_chunk($parentSkuKeys, 500) as $chunk) {
                ProductMaster::query()
                    ->whereNull('deleted_at')
                    ->whereIn('sku', $chunk)
                    ->get(['sku', 'main_image', 'title150'])
                    ->each(function ($pm) use (&$parentPmBySku) {
                        $parentPmBySku[trim((string) $pm->sku)] = $pm;
                    });
            }
        }

        // Insert parent summary rows after each parent's children (Analytics Amz style).
        $data = [];
        $grouped = collect($childRows)->groupBy(function ($row) {
            return $row['parent'] !== '' ? $row['parent'] : '__no_parent__';
        });

        foreach ($grouped as $parentKey => $rows) {
            foreach ($rows as $row) {
                $data[] = $row;
            }

            if ($parentKey === '__no_parent__' || $parentKey === '') {
                continue;
            }

            $inv = (float) $rows->sum('inv');
            $ovL30 = (float) $rows->sum('ov_l30');
            $amzL30 = (float) $rows->sum('amz_l30');
            $dilPct = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0;
            $parentSku = 'PARENT '.$parentKey;
            $parentPm = $parentPmBySku[$parentSku] ?? null;
            $title150 = $parentPm ? trim((string) ($parentPm->title150 ?? '')) : '';
            $firstWithImage = $rows->first(fn ($r) => ! empty($r['image']));
            $image = ($parentPm && $parentPm->main_image)
                ? $parentPm->main_image
                : ($firstWithImage['image'] ?? null);

            $data[] = [
                'parent' => $parentKey,
                'sku' => $parentSku,
                'asin' => null,
                'buyer_link' => null,
                'inv' => $inv,
                'ov_l30' => $ovL30,
                'dil_pct' => $dilPct,
                'amz_l30' => $amzL30,
                'image' => $image,
                'title150' => $title150 !== '' ? $title150 : null,
                'amazon_title' => null,
                'lmp_price' => null,
                'lmp_count' => 0,
                'lmp_title' => null,
                'lmp_asin' => null,
                'lmp_link' => null,
                'linked_lmp_skus' => [],
                'top_keywords' => $keywordsByParent[$parentKey] ?? [],
                'negative_keywords' => $negByParent[$parentKey] ?? [],
                'is_parent_summary' => true,
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'sku_count' => collect($data)->where('is_parent_summary', false)->count(),
                'parent_count' => collect($data)->where('is_parent_summary', true)->count(),
                'row_count' => count($data),
                'refreshed_at' => now()->toDateTimeString(),
                'pull' => PullAmazonTitlesCommand::status(),
            ],
        ]);
    }

    /**
     * Top 20 Amazon SP Ads keywords per parent, ranked by L30 impressions.
     *
     * @param  list<string>  $parents
     * @return array<string, list<array{keyword: string, impressions: int}>>
     */
    private function topKeywordsByParents(array $parents): array
    {
        $parents = array_values(array_unique(array_filter(array_map(
            static fn ($p) => preg_replace('/\s+/', ' ', trim((string) $p)),
            $parents
        ), static fn ($p) => $p !== '')));

        if ($parents === []) {
            return [];
        }

        if (! Schema::hasTable('amazon_sp_keyword_reports')) {
            return [];
        }

        $linkSkus = array_map(static fn ($p) => 'PARENT '.$p, $parents);
        $parentToCampaigns = [];

        if (Schema::hasTable('amazon_ads_missing_links') && class_exists(AmazonAdsMissingLink::class)) {
            try {
                AmazonAdsMissingLink::query()
                    ->whereIn('sku', $linkSkus)
                    ->where('type', 'KW')
                    ->get(['sku', 'campaign_name'])
                    ->each(function ($link) use (&$parentToCampaigns) {
                        $parent = trim((string) preg_replace('/^PARENT\s+/i', '', (string) $link->sku));
                        $campaign = trim((string) ($link->campaign_name ?? ''));
                        if ($parent === '' || $campaign === '') {
                            return;
                        }
                        $parentToCampaigns[$parent][] = $campaign;
                    });
            } catch (\Throwable $e) {
                Log::warning('AmzTitles keyword links load failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: campaign names that look like PARENT {parent} … KW
        foreach ($parents as $parent) {
            if (! empty($parentToCampaigns[$parent])) {
                $parentToCampaigns[$parent] = array_values(array_unique($parentToCampaigns[$parent]));

                continue;
            }
            try {
                $like = 'PARENT '.str_replace(['%', '_'], ['\%', '\_'], $parent).'%KW%';
                $found = DB::table('amazon_sp_keyword_reports')
                    ->where('report_date_range', 'L30')
                    ->where('campaignName', 'like', $like)
                    ->distinct()
                    ->limit(20)
                    ->pluck('campaignName')
                    ->map(static fn ($c) => trim((string) $c))
                    ->filter()
                    ->values()
                    ->all();
                if ($found !== []) {
                    $parentToCampaigns[$parent] = $found;
                }
            } catch (\Throwable $e) {
                // ignore fallback failures
            }
        }

        $allCampaigns = [];
        foreach ($parentToCampaigns as $campaigns) {
            foreach ($campaigns as $c) {
                $allCampaigns[$c] = true;
            }
        }
        $allCampaigns = array_keys($allCampaigns);
        if ($allCampaigns === []) {
            return array_fill_keys($parents, []);
        }

        $rows = DB::table('amazon_sp_keyword_reports')
            ->select('campaignName', 'keyword', DB::raw('MAX(impressions) as impressions'))
            ->where('report_date_range', 'L30')
            ->whereIn('campaignName', $allCampaigns)
            ->whereIn(DB::raw('UPPER(matchType)'), ['EXACT', 'PHRASE', 'BROAD'])
            ->where('keyword', 'not like', 'asin=%')
            ->whereNotNull('keyword')
            ->where('keyword', '!=', '')
            ->groupBy('campaignName', 'keyword')
            ->get();

        $byParent = array_fill_keys($parents, []);
        foreach ($parentToCampaigns as $parent => $campaigns) {
            $set = array_fill_keys($campaigns, true);
            $agg = [];
            foreach ($rows as $r) {
                if (! isset($set[$r->campaignName])) {
                    continue;
                }
                $kw = trim((string) $r->keyword);
                if ($kw === '') {
                    continue;
                }
                $imp = (int) ($r->impressions ?? 0);
                $agg[$kw] = max($agg[$kw] ?? 0, $imp);
            }
            arsort($agg);
            $top = [];
            foreach ($agg as $kw => $imp) {
                $top[] = ['keyword' => $kw, 'impressions' => (int) $imp];
                if (count($top) >= 20) {
                    break;
                }
            }
            $byParent[$parent] = $top;
        }

        return $byParent;
    }

    private function negativeKeywordsCacheKey(string $parent): string
    {
        $parent = preg_replace('/\s+/', ' ', trim($parent)) ?? '';

        return 'amz_tt_neg_kw_v1_'.md5(strtolower($parent));
    }

    /**
     * @param  list<string>  $parents
     * @return array<string, list<array{keyword: string, checked: bool}>>
     */
    private function cachedNegativeKeywordsByParents(array $parents): array
    {
        $out = [];
        foreach ($parents as $parent) {
            $parent = preg_replace('/\s+/', ' ', trim((string) $parent)) ?? '';
            if ($parent === '') {
                continue;
            }
            $cached = Cache::get($this->negativeKeywordsCacheKey($parent));
            $out[$parent] = is_array($cached) ? $cached : [];
        }

        return $out;
    }

    /**
     * Generate top 30 AI negative keywords for a parent (cached). Checked by default.
     */
    public function suggestNegatives(Request $request)
    {
        $validated = $request->validate([
            'parent' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'force' => 'nullable|boolean',
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent'])) ?? '';
        $sku = trim((string) ($validated['sku'] ?? ''));
        if ($parent === '') {
            return response()->json(['success' => false, 'message' => 'Parent is required.'], 422);
        }

        $cacheKey = $this->negativeKeywordsCacheKey($parent);
        if (! $request->boolean('force')) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                return response()->json([
                    'success' => true,
                    'parent' => $parent,
                    'negative_keywords' => $cached,
                    'cached' => true,
                    'count' => count($cached),
                ]);
            }
        }

        // Reuse Amazon Ads Missing AI negatives generator.
        $inner = Request::create('/amazon-ads/missing/ai-negatives', 'POST', [
            'parent' => $parent,
            'target_sku' => $sku,
            'campaign_name' => 'PARENT '.$parent.' KW',
            'mode' => 'generate',
        ]);
        $inner->headers->set('Accept', 'application/json');

        try {
            $resp = app(AmazonAdsMissingController::class)->aiNegativeKeywords($inner);
            $payload = $resp->getData(true);
        } catch (\Throwable $e) {
            Log::error('AmzTitles suggestNegatives failed', ['parent' => $parent, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate negative keywords: '.$e->getMessage(),
            ], 500);
        }

        if (! is_array($payload) || empty($payload['ok'])) {
            return response()->json([
                'success' => false,
                'message' => is_array($payload) ? ($payload['message'] ?? 'AI negatives failed') : 'AI negatives failed',
            ], 502);
        }

        $suggested = collect($payload['suggested'] ?? [])
            ->map(static fn ($t) => trim((string) $t))
            ->filter()
            ->unique(static fn ($t) => strtolower($t))
            ->take(30)
            ->values()
            ->map(static fn ($t) => ['keyword' => $t, 'checked' => true])
            ->all();

        // If AI returned fewer than 30, keep them; still cache.
        Cache::put($cacheKey, $suggested, now()->addHours(12));

        return response()->json([
            'success' => true,
            'parent' => $parent,
            'negative_keywords' => $suggested,
            'cached' => false,
            'count' => count($suggested),
            'existing_count' => (int) ($payload['existing_count'] ?? 0),
        ]);
    }

    /**
     * Push checked negative keywords to Amazon SP campaign for this parent.
     */
    public function approveNegatives(Request $request)
    {
        $validated = $request->validate([
            'parent' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'keywords' => 'required|array|min:1|max:50',
            'keywords.*' => 'required|string|max:255',
            'match_type' => 'nullable|string|in:PHRASE,EXACT,NEGATIVE_PHRASE,NEGATIVE_EXACT',
        ]);

        $parent = preg_replace('/\s+/', ' ', trim($validated['parent'])) ?? '';
        $keywords = collect($validated['keywords'])
            ->map(static fn ($t) => trim((string) $t))
            ->filter()
            ->unique(static fn ($t) => strtolower($t))
            ->values()
            ->all();

        if ($parent === '' || $keywords === []) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one negative keyword to approve.',
            ], 422);
        }

        $inner = Request::create('/amazon-ads/missing/push-negatives', 'POST', [
            'parent' => $parent,
            'campaign_name' => 'PARENT '.$parent.' KW',
            'keywords' => $keywords,
            'include_existing' => false,
            'match_type' => $validated['match_type'] ?? 'PHRASE',
        ]);
        $inner->headers->set('Accept', 'application/json');

        try {
            $resp = app(AmazonAdsMissingController::class)->pushNegativeKeywords($inner);
            $payload = $resp->getData(true);
        } catch (\Throwable $e) {
            Log::error('AmzTitles approveNegatives failed', ['parent' => $parent, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Push failed: '.$e->getMessage(),
            ], 500);
        }

        $ok = is_array($payload) && ! empty($payload['ok']);

        // Keep cache in sync: drop successfully pushed terms from checked list? Keep list; mark pushed.
        if ($ok) {
            $cacheKey = $this->negativeKeywordsCacheKey($parent);
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $pushed = array_fill_keys(array_map('strtolower', $keywords), true);
                $updated = [];
                foreach ($cached as $item) {
                    $kw = is_array($item) ? (string) ($item['keyword'] ?? '') : (string) $item;
                    if ($kw === '') {
                        continue;
                    }
                    $updated[] = [
                        'keyword' => $kw,
                        'checked' => empty($pushed[strtolower($kw)]),
                        'pushed' => ! empty($pushed[strtolower($kw)]),
                    ];
                }
                Cache::put($cacheKey, $updated, now()->addHours(12));
            }
        }

        return response()->json([
            'success' => $ok,
            'message' => is_array($payload) ? ($payload['message'] ?? ($ok ? 'Pushed' : 'Push failed')) : 'Push failed',
            'parent' => $parent,
            'added' => (int) ($payload['added'] ?? 0),
            'failed' => (int) ($payload['failed'] ?? 0),
            'duplicates' => (int) ($payload['duplicates'] ?? 0),
            'campaign_id' => $payload['campaign_id'] ?? null,
            'campaign_name' => $payload['campaign_name'] ?? null,
            'negative_keywords' => Cache::get($this->negativeKeywordsCacheKey($parent), []),
        ], $ok ? 200 : 422);
    }

    /**
     * AI analyze Amazon title via buyer link — % scores + suggested Title 170.
     */
    public function aiAnalyze(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'buyer_link' => 'nullable|string|max:1000',
            'current_title' => 'nullable|string|max:2000',
            'parent' => 'nullable|string|max:255',
            'prompt' => 'nullable|string|max:8000',
        ]);

        $sku = trim($validated['sku']);
        $buyerLink = trim((string) ($validated['buyer_link'] ?? ''));
        $currentTitle = trim((string) ($validated['current_title'] ?? ''));
        $parent = trim((string) ($validated['parent'] ?? ''));
        $userPrompt = trim((string) ($validated['prompt'] ?? ''));

        if ($userPrompt === '') {
            $userPrompt = $this->defaultAiAnalyzePrompt();
        }
        // Strip URLs from user prompt so the model does not refuse "can't access links".
        $userPrompt = trim((string) preg_replace('#https?://\S+#i', '[link omitted]', $userPrompt));

        $asin = null;
        if ($buyerLink !== '' && preg_match('/\/(?:dp|gp\/product)\/([A-Z0-9]{10})/i', $buyerLink, $m)) {
            $asin = strtoupper($m[1]);
        } elseif ($buyerLink !== '' && preg_match('/[?&]asin=([A-Z0-9]{10})/i', $buyerLink, $m)) {
            $asin = strtoupper($m[1]);
        }

        if ($currentTitle === '' && $sku === '' && ! $asin) {
            return response()->json([
                'success' => false,
                'message' => 'Need Current Title 170, SKU, or ASIN to run AI.',
            ], 422);
        }

        $hasOpenai = (bool) config('services.openai.key');
        $claudeKey = config('services.claude.key') ?: config('services.anthropic.key');
        $hasClaude = is_string($claudeKey) && $claudeKey !== '';

        if (! $hasClaude && ! $hasOpenai) {
            return response()->json([
                'success' => false,
                'message' => 'AI is not configured. Set CLAUDE_API_KEY in .env (preferred), or OPENAI_API_KEY.',
            ], 503);
        }

        $asinLine = $asin ? "- ASIN: {$asin}" : '- ASIN: (not available)';
        $titleLine = $currentTitle !== ''
            ? "- Current Title 170: {$currentTitle}"
            : '- Current Title 170: (EMPTY — create a strong Amazon title from SKU/Parent/ASIN context)';
        $emptyTitleRules = $currentTitle === ''
            ? <<<'RULES'
- Current Title 170 is EMPTY.
- Still give visibility/conversion/overall scores for the missing title (typically low).
- suggested_title MUST be a complete Amazon-ready title inferred from SKU/Parent/ASIN.
RULES
            : <<<'RULES'
- Improve the Current Title 170 for higher Amazon search visibility and conversions.
- Suggest one stronger Amazon title.
RULES;

        $prompt = <<<PROMPT
Task guidance from user:
{$userPrompt}

IMPORTANT: Do not browse the web. Do not mention URLs. Use only the fields below.
IMPORTANT LENGTH (overrides any conflicting user guidance): Amazon Title max is 170 characters.
suggested_title MUST be between 150 and 170 characters inclusive (count spaces). Prefer 160–170. Never under 150. Never over 170.
(If the user asked for 200+, still cap at 170 — Amazon rejects longer titles.)

Product context:
- SKU: {$sku}
- Parent: {$parent}
{$asinLine}
{$titleLine}

Requirements:
{$emptyTitleRules}
- CRITICAL: suggested_title character count MUST be 150–170 (include spaces). Expand with useful attributes (size, wattage, pack, use case, fitment) until ≥150 chars — do NOT return a short title.
- Give integer % scores 0–100 for visibility, conversion, and overall for the CURRENT title.
- Also give ONE combined score for the suggested_title itself: ai_title_score (0–100) — overall strength for Amazon visibility and conversions (single number, not separate vis/conv).
- Output MUST be a single JSON object only. No prose. No markdown.

Return ONLY valid JSON with this exact shape:
{
  "visibility_score": 0,
  "conversion_score": 0,
  "overall_score": 0,
  "suggested_title": "improved Amazon title that is 150 to 170 characters long",
  "ai_title_score": 0,
  "char_count": 0
}
PROMPT;

        try {
            $text = null;
            $lastError = '';

            // Prefer Claude — OPENAI_API_KEY currently returns 401 for this project.
            if ($hasClaude) {
                [$text, $claudeErr] = $this->requestAiAnalyzeFromClaude($prompt, $sku, (string) $claudeKey);
                if ($text === null && $claudeErr !== '') {
                    $lastError = $claudeErr;
                }
            }

            if ($text === null && $hasOpenai) {
                [$text, $openaiErr] = $this->requestAiAnalyzeFromOpenAi($prompt, $sku);
                if ($text === null && $openaiErr !== '') {
                    $lastError = $openaiErr;
                }
            }

            if ($text === null) {
                return response()->json([
                    'success' => false,
                    'message' => $lastError !== '' ? $lastError : 'AI request failed.',
                ], 502);
            }

            $data = $this->decodeAiAnalyzePayload($text);
            if ($data === null && $hasClaude) {
                // Second pass: force the previous prose into the required JSON schema.
                $repairPrompt = <<<REPAIR
Convert the following text into ONLY this JSON object (no markdown, no commentary):
{
  "visibility_score": 0,
  "conversion_score": 0,
  "overall_score": 0,
  "suggested_title": "...",
  "ai_title_score": 0
}
If the text refused to analyze, invent a reasonable analysis from SKU "{$sku}", Parent "{$parent}", ASIN "{$asin}", Title "{$currentTitle}".

Text to convert:
{$text}
REPAIR;
                [$repaired, $repairErr] = $this->requestAiAnalyzeFromClaude($repairPrompt, $sku, (string) $claudeKey);
                if ($repaired !== null) {
                    $data = $this->decodeAiAnalyzePayload($repaired);
                } elseif ($repairErr !== '') {
                    $lastError = $repairErr;
                }
            }

            if ($data === null) {
                Log::warning('AmzTitles AI analyze: invalid JSON payload', [
                    'sku' => $sku,
                    'preview' => mb_substr($text, 0, 500),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid AI response format. Try again, or check the AI Prompt.',
                ], 422);
            }

            $visibility = max(0, min(100, (int) round((float) (
                $data['visibility_score'] ?? $data['visibility'] ?? $data['vis_score'] ?? 0
            ))));
            $conversion = max(0, min(100, (int) round((float) (
                $data['conversion_score'] ?? $data['conversion'] ?? $data['conv_score'] ?? 0
            ))));
            $overall = max(0, min(100, (int) round((float) (
                $data['overall_score'] ?? $data['score'] ?? $data['overall'] ?? 0
            ))));
            if ($overall === 0 && ($visibility > 0 || $conversion > 0)) {
                $overall = (int) round(($visibility + $conversion) / 2);
            }

            $suggested = trim((string) (
                $data['suggested_title'] ?? $data['improved_title'] ?? $data['title'] ?? ''
            ));
            $suggested = $this->enforceAmazonTitleLengthBand(
                $suggested,
                $sku,
                $parent,
                $asin,
                $currentTitle,
                $hasClaude ? (string) $claudeKey : null,
                $hasOpenai,
                $data
            );

            $aiTitleScore = max(0, min(100, (int) round((float) (
                $data['ai_title_score']
                    ?? $data['suggested_title_score']
                    ?? $data['ai_score']
                    ?? 0
            ))));
            // Backward compat: if model still returns separate vis/conv for AI title, merge to one.
            if ($aiTitleScore === 0) {
                $aiTitleVis = max(0, min(100, (int) round((float) (
                    $data['ai_title_visibility_score']
                        ?? $data['suggested_visibility_score']
                        ?? $data['ai_visibility_score']
                        ?? 0
                ))));
                $aiTitleConv = max(0, min(100, (int) round((float) (
                    $data['ai_title_conversion_score']
                        ?? $data['suggested_conversion_score']
                        ?? $data['ai_conversion_score']
                        ?? 0
                ))));
                if ($aiTitleVis > 0 || $aiTitleConv > 0) {
                    $aiTitleScore = (int) round(($aiTitleVis + $aiTitleConv) / 2);
                }
            }
            // If model omitted AI Title score, estimate slightly above current overall.
            if ($suggested !== '' && $aiTitleScore === 0) {
                $aiTitleScore = min(100, max($overall, (int) round($overall + 8)));
            }

            $charCount = $suggested !== '' ? mb_strlen($suggested) : 0;

            return response()->json([
                'success' => true,
                'sku' => $sku,
                'buyer_link' => $buyerLink,
                'visibility_score' => $visibility,
                'conversion_score' => $conversion,
                'overall_score' => $overall,
                'suggested_title' => $suggested !== '' ? $suggested : null,
                'char_count' => $charCount,
                'length_ok' => $charCount >= 150 && $charCount <= 170,
                'ai_title_score' => $suggested !== '' ? $aiTitleScore : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('AmzTitles AI analyze exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'AI error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse model text into associative array (handles markdown fences / surrounding prose).
     *
     * @return array<string, mixed>|null
     */
    private function decodeAiAnalyzePayload(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Strip common markdown fences.
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text);

        $tryDecode = static function (string $raw): ?array {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                // Some models wrap: {"result":{...}} or {"data":{...}}
                foreach (['result', 'data', 'analysis', 'response'] as $wrap) {
                    if (isset($decoded[$wrap]) && is_array($decoded[$wrap])) {
                        $inner = $decoded[$wrap];
                        if (
                            isset($inner['suggested_title']) || isset($inner['visibility_score'])
                            || isset($inner['overall_score']) || isset($inner['conversion_score'])
                        ) {
                            return $inner;
                        }
                    }
                }

                return $decoded;
            }

            // Double-encoded JSON string
            if (is_string($decoded)) {
                $again = json_decode($decoded, true);

                return is_array($again) ? $again : null;
            }

            return null;
        };

        $data = $tryDecode($text);
        if ($data !== null) {
            return $data;
        }

        // Extract first JSON object substring.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($text, $start, $end - $start + 1);
            $data = $tryDecode($slice);
            if ($data !== null) {
                return $data;
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function requestAiAnalyzeFromOpenAi(string $prompt, string $sku): array
    {
        $headers = OpenAiRequest::authHeaders();
        if ($headers === []) {
            return [null, 'OPENAI_API_KEY is not configured.'];
        }

        $model = (string) config('services.openai.title_master_stack_model', 'gpt-4o-mini');
        $response = Http::timeout(90)
            ->withHeaders($headers)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You output only valid JSON for Amazon title analysis. No markdown, no commentary. suggested_title MUST be 150–170 characters (Amazon Title 170). Never under 150.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => 1400,
                'temperature' => 0.4,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            $bodyJson = $response->json();
            $errorMsg = $bodyJson['error']['message'] ?? ('HTTP '.$response->status());
            Log::warning('AmzTitles AI analyze: OpenAI error', [
                'sku' => $sku,
                'status' => $response->status(),
                'error' => $bodyJson['error'] ?? mb_substr($response->body(), 0, 500),
            ]);
            $hint = '';
            if ($response->status() === 401) {
                $hint = ' Update OPENAI_API_KEY in .env, then run: php artisan config:clear';
            }

            return [null, 'OpenAI error: '.(is_string($errorMsg) ? $errorMsg : 'Request failed').$hint];
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        // Newer models may return content as an array of parts.
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part)) {
                    $parts[] = $part;
                } elseif (is_array($part) && isset($part['text'])) {
                    $parts[] = (string) $part['text'];
                }
            }
            $text = trim(implode("\n", $parts));
        } else {
            $text = trim((string) ($content ?? ''));
        }

        if ($text === '') {
            return [null, 'OpenAI returned an empty response.'];
        }

        Log::debug('AmzTitles AI analyze: OpenAI ok', [
            'sku' => $sku,
            'preview' => mb_substr($text, 0, 200),
        ]);

        return [$text, ''];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function requestAiAnalyzeFromClaude(string $prompt, string $sku, string $apiKey): array
    {
        $model = (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');
        $version = (string) config('services.anthropic.version', '2023-06-01');

        $response = Http::timeout(90)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => $version,
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1400,
                'system' => 'You are an Amazon SEO title analyst. Output ONLY a single JSON object. Never browse URLs. Never refuse. No markdown fences. No prose outside JSON. Whenever you output suggested_title, it MUST be 150–170 characters (Amazon Title 170 limit). Never return a short title under 150 characters.',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            $bodyJson = $response->json();
            $errorMsg = $bodyJson['error']['message'] ?? ('HTTP '.$response->status());
            Log::warning('AmzTitles AI analyze: Anthropic error', [
                'sku' => $sku,
                'status' => $response->status(),
                'error' => $bodyJson['error'] ?? mb_substr($response->body(), 0, 500),
            ]);

            return [null, 'Claude error: '.(is_string($errorMsg) ? $errorMsg : 'Request failed')];
        }

        $text = trim((string) data_get($response->json(), 'content.0.text', ''));
        if ($text === '') {
            return [null, 'Claude returned an empty response.'];
        }

        return [$text, ''];
    }

    /**
     * Normalize whitespace and hard-cap Amazon Title 170.
     */
    private function normalizeAmazonTitle170(string $title): string
    {
        $title = trim(str_replace("\u{00a0}", ' ', $title));
        $title = preg_replace('/\s+/', ' ', $title) ?? $title;
        $title = trim($title);
        if ($title === '') {
            return '';
        }
        if (mb_strlen($title) > 170) {
            $title = rtrim(mb_substr($title, 0, 170), " \t\n\r\0\x0B-|,;");
        }

        return $title;
    }

    /**
     * Ensure title is in Amazon 150–170 band: AI expand retries, then deterministic pad.
     *
     * @param  array<string, mixed>  $scoreBag
     */
    private function enforceAmazonTitleLengthBand(
        string $title,
        string $sku,
        string $parent,
        ?string $asin,
        string $currentTitle,
        ?string $claudeKey,
        bool $hasOpenai,
        array &$scoreBag = []
    ): string {
        $title = $this->normalizeAmazonTitle170($title);
        if ($title === '') {
            return '';
        }
        if (mb_strlen($title) >= 150) {
            return $title;
        }

        // Up to 3 AI expansion attempts — models often land ~130–145 and stop early.
        for ($attempt = 1; $attempt <= 3 && mb_strlen($title) < 150; $attempt++) {
            $lenNow = mb_strlen($title);
            $need = 150 - $lenNow;
            $target = min(170, max(160, $lenNow + $need + 10));
            $asinLabel = $asin ?: '(n/a)';
            $lengthFixPrompt = <<<FIX
Expand this Amazon title. Current length = {$lenNow}. You MUST add at least {$need} more characters.

SKU: {$sku}
Parent: {$parent}
ASIN: {$asinLabel}
Current listing title: {$currentTitle}
Title to expand (keep meaning, do not shorten): {$title}

HARD RULES:
- Return ONLY JSON.
- suggested_title length MUST be {$target} characters (±5), and MUST be between 150 and 170 inclusive.
- Count every character including spaces. Prefer ~165.
- Keep the existing words; APPEND natural Amazon attributes/phrases (size, wattage, pair/pack, fitment, coaxial/tweeter, car stereo, upgrade/replacement, high efficiency, clear crisp sound, universal fit).
- Do NOT return a title shorter than 150. Do NOT exceed 170.

{"suggested_title":"...","ai_title_score":0,"char_count":0}
FIX;

            $fixedText = null;
            if (is_string($claudeKey) && $claudeKey !== '') {
                [$fixedText] = $this->requestAiAnalyzeFromClaude($lengthFixPrompt, $sku, $claudeKey);
            }
            if ($fixedText === null && $hasOpenai) {
                [$fixedText] = $this->requestAiAnalyzeFromOpenAi($lengthFixPrompt, $sku);
            }
            if ($fixedText === null) {
                continue;
            }

            $fixedData = $this->decodeAiAnalyzePayload($fixedText);
            if (! is_array($fixedData)) {
                continue;
            }
            $fixedTitle = $this->normalizeAmazonTitle170((string) (
                $fixedData['suggested_title'] ?? $fixedData['title'] ?? ''
            ));
            if ($fixedTitle === '') {
                continue;
            }
            // Accept only if longer (or already in band).
            if (mb_strlen($fixedTitle) > mb_strlen($title) || mb_strlen($fixedTitle) >= 150) {
                $title = $fixedTitle;
                if (isset($fixedData['ai_title_score'])) {
                    $scoreBag['ai_title_score'] = $fixedData['ai_title_score'];
                } elseif (
                    isset($fixedData['ai_title_visibility_score'])
                    || isset($fixedData['ai_title_conversion_score'])
                ) {
                    $v = (float) ($fixedData['ai_title_visibility_score'] ?? 0);
                    $c = (float) ($fixedData['ai_title_conversion_score'] ?? 0);
                    $scoreBag['ai_title_score'] = (int) round(($v + $c) / 2);
                }
            }
        }

        if (mb_strlen($title) < 150) {
            $title = $this->padAmazonTitleToMinLength($title, $sku, $parent, $currentTitle, 150, 170);
        }

        return $this->normalizeAmazonTitle170($title);
    }

    /**
     * Deterministic pad so we never return under minLength when AI keeps under-shooting.
     */
    private function padAmazonTitleToMinLength(
        string $title,
        string $sku,
        string $parent,
        string $currentTitle,
        int $minLength = 150,
        int $maxLength = 170
    ): string {
        $title = $this->normalizeAmazonTitle170($title);
        if ($title === '' || mb_strlen($title) >= $minLength) {
            return $title;
        }

        $phrases = [
            'Universal Fit',
            'Car Stereo Audio',
            'High Efficiency Sound',
            'Clear Crisp Bass',
            'Replacement Upgrade',
            'Easy Install',
            'Heavy Duty',
            'Professional Grade',
            'Aftermarket Speakers',
            'Built for Daily Driving',
        ];

        // Pull extra tokens from current title / parent / sku that are not already present.
        $extras = preg_split('/[\s\-\|,\/]+/', $currentTitle.' '.$parent.' '.$sku) ?: [];
        foreach ($extras as $token) {
            $token = trim((string) $token);
            if (mb_strlen($token) < 3) {
                continue;
            }
            if (stripos($title, $token) !== false) {
                continue;
            }
            $phrases[] = $token;
        }

        foreach ($phrases as $phrase) {
            if (mb_strlen($title) >= $minLength) {
                break;
            }
            $phrase = trim((string) $phrase);
            if ($phrase === '' || stripos($title, $phrase) !== false) {
                continue;
            }
            $candidate = $this->normalizeAmazonTitle170($title.' '.$phrase);
            if (mb_strlen($candidate) <= $maxLength) {
                $title = $candidate;
            } else {
                // Fit a truncated phrase into remaining room.
                $room = $maxLength - mb_strlen($title) - 1;
                if ($room >= 4) {
                    $chunk = rtrim(mb_substr($phrase, 0, $room));
                    if ($chunk !== '') {
                        $title = $this->normalizeAmazonTitle170($title.' '.$chunk);
                    }
                }
                break;
            }
        }

        // Last resort: pad with spaced filler words (still readable-ish for length gate).
        $filler = ['Audio', 'System', 'Stereo', 'Speaker', 'Quality', 'Performance'];
        $i = 0;
        while (mb_strlen($title) < $minLength && $i < 40) {
            $word = $filler[$i % count($filler)];
            $candidate = $this->normalizeAmazonTitle170($title.' '.$word);
            if (mb_strlen($candidate) > $maxLength) {
                $room = $maxLength - mb_strlen($title);
                if ($room >= 2) {
                    $title = $this->normalizeAmazonTitle170($title.str_repeat(' X', (int) floor($room / 2)));
                }
                break;
            }
            $title = $candidate;
            $i++;
        }

        return $this->normalizeAmazonTitle170($title);
    }

    public function defaultAiAnalyzePrompt(): string
    {
        return 'Analyze the Current Title for this Amazon product. Give % scores for visibility and conversions. Suggest a stronger Amazon Title that improves visibility and conversions. The suggested_title MUST be 150–170 characters (Amazon max is 170 — do not aim for 200). Expand with useful product attributes until at least 150 characters. Do not browse any URL — use only the title text and SKU/ASIN context provided.';
    }
}
