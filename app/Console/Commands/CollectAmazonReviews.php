<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Models\AmazonDatasheet;
use App\Models\AmazonProductReview;
use App\Models\JungleScoutProductData;
use App\Models\SerpApiRawResponse;
use App\Services\AmazonAdsService;
use App\Services\AmazonSpApiService;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull avg star rating + review count for Amazon SKUs via Advertising API
 * (Brand Posts), SP-API Catalog, Jungle Scout, then live SerpApi amazon.com
 * ratings (overwrites stale Jungle Scout when the PDP returns a rating).
 */
class CollectAmazonReviews extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'amazon:collect-reviews
        {--chunk=20 : ASINs per Ads Brand Posts request}
        {--limit=0 : Max SKUs to process (0 = all)}
        {--sku= : Optional single SKU}
        {--ads-only : Do not fall back to SP-API / Jungle Scout / SerpApi}
        {--no-serp : Skip SerpApi live Amazon PDP ratings}
        {--serp-only : Write only SerpApi / live sources (do not write Jungle Scout)}';

    protected $description = 'Collect Amazon avg rating + review count via Ads, SP-API, cached/live SerpApi, then Jungle Scout gaps';

    protected string $monitorJobName = 'Amazon Collect Reviews';

    private bool $serpLiveDisabled = false;

    private int $serpCacheHits = 0;

    private int $serpLiveHits = 0;

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeCollect($m),
            $this->monitorJobName
        );
    }

    protected function executeCollect(CronExecutionContext $monitor): int
    {
        if (! Schema::hasTable('amazon_product_reviews')) {
            $this->error('Table amazon_product_reviews does not exist.');

            return self::FAILURE;
        }

        $ads = app(AmazonAdsService::class);
        $spApi = app(AmazonSpApiService::class);
        $chunkSize = max(1, min(20, (int) $this->option('chunk')));
        $limit = max(0, (int) $this->option('limit'));
        $onlySku = trim((string) $this->option('sku'));
        $adsOnly = (bool) $this->option('ads-only');
        $now = Carbon::now();

        $query = AmazonDatasheet::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('asin')
            ->where('asin', '!=', '')
            ->orderBy('id');

        if ($onlySku !== '') {
            $query->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($onlySku)]);
        }

        $rows = $query->get(['id', 'sku', 'asin']);
        if ($limit > 0) {
            $rows = $rows->take($limit);
        }

        // Prefer first non-empty ASIN per SKU (keep original SKU casing for DB upsert)
        $bySkuNorm = [];
        foreach ($rows as $row) {
            $skuRaw = trim(str_replace("\xC2\xA0", ' ', (string) $row->sku));
            $skuNorm = strtoupper($skuRaw);
            $asin = strtoupper(trim((string) $row->asin));
            if ($skuNorm === '' || $asin === '' || isset($bySkuNorm[$skuNorm])) {
                continue;
            }
            $bySkuNorm[$skuNorm] = ['sku' => $skuRaw, 'asin' => $asin];
        }

        $monitor->setFetched(count($bySkuNorm));
        $monitor->setExpected(count($bySkuNorm));
        $this->info('SKUs with ASIN to process: '.count($bySkuNorm));

        if ($bySkuNorm === []) {
            return self::SUCCESS;
        }

        // Build ASIN → SKUs map (multiple SKUs can share an ASIN)
        $asinToSkus = [];
        foreach ($bySkuNorm as $entry) {
            $asinToSkus[$entry['asin']][] = $entry['sku'];
        }

        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $asins = array_keys($asinToSkus);

        // Probe Ads Brand Posts once — this profile often returns 403 (no Brand Posts scope).
        // Do not retry every chunk (spams the same Authorization error).
        $useBrandPosts = true;
        $useSpCatalog = ! $adsOnly;
        $probeAsin = $asins[0] ?? null;
        if ($probeAsin) {
            try {
                $ads->getBrandPostProductsByAsins([$probeAsin]);
                $this->info('Ads Brand Posts: OK — will use customerReviewSummary when present.');
                $monitor->markApiConnected(true);
            } catch (\Throwable $e) {
                $useBrandPosts = false;
                $short = $this->shortApiError($e);
                $this->warn('Ads Brand Posts unavailable (skipping for this run): '.$short);
                $this->warn('→ Continuing with Jungle Scout, then cached/live SerpApi for Amazon PDP ratings.');
                Log::warning('amazon:collect-reviews Ads Brand Posts disabled for run', [
                    'error' => $e->getMessage(),
                ]);
            }

            if ($useSpCatalog) {
                try {
                    $probeCtx = [];
                    $probeBody = $spApi->getCatalogItemByAsin($probeAsin, $probeCtx, 'summaries,customerReviews');
                    if (! is_array($probeBody)) {
                        $useSpCatalog = false;
                        $this->warn('SP-API Catalog customerReviews unavailable — skipping SP-API for this run.');
                    } else {
                        $this->info('SP-API Catalog: reachable — will use customerReviews when present.');
                        $monitor->markApiConnected(true);
                    }
                } catch (\Throwable $e) {
                    $useSpCatalog = false;
                    $this->warn('SP-API Catalog unavailable — skipping: '.$this->shortApiError($e));
                }
            }
        }

        $reviewByAsin = [];
        $chunks = array_chunk($asins, $chunkSize);
        $chunkTotal = count($chunks);
        foreach ($chunks as $chunkIndex => $asinChunk) {
            $this->info('Chunk '.($chunkIndex + 1).'/'.$chunkTotal.' ('.count($asinChunk).' ASINs)');

            // 1) Amazon Advertising API — Brand Posts product list (customerReviewSummary)
            if ($useBrandPosts) {
                try {
                    $bp = $ads->getBrandPostProductsByAsins($asinChunk);
                    $eligible = $bp['eligibleProducts'] ?? [];
                    if (is_array($eligible)) {
                        foreach ($eligible as $product) {
                            if (! is_array($product)) {
                                continue;
                            }
                            $asin = strtoupper(trim((string) ($product['id'] ?? $product['asin'] ?? '')));
                            if ($asin === '') {
                                continue;
                            }
                            $parsed = AmazonAdsService::extractReviewSummary($product);
                            if ($parsed['rating'] !== null || $parsed['review_count'] !== null) {
                                $reviewByAsin[$asin] = [
                                    'rating' => $parsed['rating'],
                                    'review_count' => $parsed['review_count'] ?? 0,
                                    'source' => 'amazon_ads_bp',
                                ];
                            }
                        }
                    }
                    $monitor->markApiConnected(true);
                } catch (\Throwable $e) {
                    $useBrandPosts = false;
                    $this->warn('Ads Brand Posts failed mid-run — disabling: '.$this->shortApiError($e));
                    Log::warning('amazon:collect-reviews Ads Brand Posts failed mid-run', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 2) Fallback — SP-API Catalog customerReviews (only if the catalog call itself works)
            if ($useSpCatalog) {
                foreach ($asinChunk as $asin) {
                    if (isset($reviewByAsin[$asin])) {
                        continue;
                    }
                    try {
                        $parsed = $spApi->getCatalogReviewSummaryByAsin($asin);
                        if ($parsed['rating'] !== null || $parsed['review_count'] !== null) {
                            $reviewByAsin[$asin] = [
                                'rating' => $parsed['rating'],
                                'review_count' => $parsed['review_count'] ?? 0,
                                'source' => 'amazon_sp_catalog',
                            ];
                            $monitor->markApiConnected(true);
                        }
                        usleep(100000);
                    } catch (\Throwable $e) {
                        $useSpCatalog = false;
                        $this->warn('SP-API Catalog failed mid-run — disabling: '.$this->shortApiError($e));
                        break;
                    }
                }
            }

            // 3) Jungle Scout (gap fill only — never used when --serp-only)
            if (! $adsOnly && ! $this->option('serp-only')) {
                foreach ($asinChunk as $asin) {
                    if (isset($reviewByAsin[$asin])) {
                        continue;
                    }
                    $js = $this->jungleScoutReviewForAsin($asin);
                    if ($js !== null) {
                        $reviewByAsin[$asin] = $js;
                    }
                }
            }

            $found = 0;
            foreach ($asinChunk as $asin) {
                if (isset($reviewByAsin[$asin])) {
                    $found++;
                }
            }
            $this->info('Chunk done — reviews found for '.$found.'/'.count($asinChunk).' ASINs');
        }

        // 4) SerpApi amazon.com PDP — cache first, then live if quota remains.
        //    Overwrites Jungle Scout (JS child ratings are often stale vs the family PDP).
        if (! $adsOnly && ! $this->option('no-serp')) {
            $this->disableLiveSerpIfNoQuota();
            $filled = $this->overwriteWithSerpApi($asins, $reviewByAsin);
            if ($filled > 0) {
                $monitor->markApiConnected(true);
            }
            $this->info("SerpApi ratings applied to {$filled} ASIN(s) (cache={$this->serpCacheHits}, live={$this->serpLiveHits})");
        }

        if (! $adsOnly) {
            foreach ($asins as $asin) {
                if (! isset($reviewByAsin[$asin])) {
                    $skipped++;
                }
            }
        }

        foreach ($asins as $asin) {
            $payload = $reviewByAsin[$asin] ?? null;
            $skus = $asinToSkus[$asin] ?? [];
            if ($payload === null) {
                continue;
            }

            foreach ($skus as $sku) {
                try {
                    $link = 'https://www.amazon.com/dp/'.$asin;
                    $attrs = [
                        'channel' => 'Amazon',
                        'asin' => $asin,
                        'product_rating' => $payload['rating'],
                        'review_count' => (int) ($payload['review_count'] ?? 0),
                        'link' => $link,
                        'source' => $payload['source'],
                        'fetched_at' => $now,
                    ];

                    if (! Schema::hasColumn('amazon_product_reviews', 'asin')) {
                        unset($attrs['asin']);
                    }
                    if (! Schema::hasColumn('amazon_product_reviews', 'source')) {
                        unset($attrs['source']);
                    }
                    if (! Schema::hasColumn('amazon_product_reviews', 'fetched_at')) {
                        unset($attrs['fetched_at']);
                    }

                    $existing = AmazonProductReview::query()
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                        ->where(function ($q) {
                            $q->where('channel', 'Amazon')->orWhereNull('channel')->orWhere('channel', '');
                        })
                        ->first();

                    $payloadSource = (string) ($payload['source'] ?? '');
                    if ($existing && $this->isLiveReviewSource((string) $existing->source) && ! $this->isLiveReviewSource($payloadSource)) {
                        continue;
                    }
                    if ($this->option('serp-only') && ! $this->isLiveReviewSource($payloadSource)) {
                        continue;
                    }

                    if ($existing) {
                        $existing->fill($attrs);
                        $existing->sku = $sku;
                        $existing->save();
                    } else {
                        AmazonProductReview::create(array_merge(['sku' => $sku], $attrs));
                    }
                    $updated++;
                    $monitor->markApiConnected(true);
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('amazon:collect-reviews save failed', [
                        'sku' => $sku,
                        'asin' => $asin,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $monitor->setProcessed($updated + $skipped + $failed);
        $monitor->setUpdated($updated);
        $monitor->setSkipped($skipped);
        $monitor->setFailed($failed);
        // ASINs with no rating are a normal skip, not a missed update.
        $monitor->setExpected($updated + $failed);
        if ($updated > 0) {
            $monitor->markApiConnected(true);
        }

        $this->info("Updated: {$updated} | Skipped (no rating): {$skipped} | Failed: {$failed}");

        return self::SUCCESS;
    }

    private function shortApiError(\Throwable $e): string
    {
        $msg = $e->getMessage();
        // Drop huge URL/ASIN lists from Guzzle ClientException messages
        if (preg_match('/\b(\d{3})\s+([A-Za-z ]+)\b/', $msg, $m)) {
            return trim($m[1].' '.$m[2]);
        }
        if (str_contains($msg, 'Invalid key=value pair')) {
            return '403 Forbidden (Ads app missing Brand Posts permission / invalid Authorization for /bp/v2)';
        }

        return mb_strimwidth($msg, 0, 160, '…');
    }

    /**
     * Amazon PDP rating: Laravel cache → stored SerpApi body → one live
     * SerpApi search (no_cache=false so SerpApi reuses its own cached search).
     *
     * @return array{rating: float|null, review_count: int, source: string}|null
     */
    private function serpApiReviewForAsin(string $asin): ?array
    {
        $asin = strtoupper(trim($asin));
        if ($asin === '') {
            return null;
        }

        $cached = $this->cachedSerpReview($asin);
        if ($cached !== null) {
            $this->serpCacheHits++;

            return $cached;
        }

        if ($this->serpLiveDisabled) {
            return null;
        }

        $apiKey = (string) config('services.serpapi.key');
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = Http::timeout(25)->get('https://serpapi.com/search', [
                'engine' => 'amazon_product',
                'amazon_domain' => 'amazon.com',
                'asin' => $asin,
                'no_cache' => 'false',
                'api_key' => $apiKey,
            ]);

            $data = $response->json();
            $err = is_array($data) ? strtolower((string) ($data['error'] ?? '')) : '';
            if ($response->status() === 429 || str_contains($err, 'run out of searches')) {
                $this->serpLiveDisabled = true;
                $msg = $err !== '' ? $err : '429 rate limit';
                $this->warn('SerpApi stopped live searches: '.$msg);
                Log::warning('amazon:collect-reviews SerpApi live disabled', ['error' => $msg]);

                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $parsed = $this->parseSerpProductResults(is_array($data) ? $data : []);
            $this->storeSerpReviewCache($asin, $data, $parsed, $response->status());
            if ($parsed !== null) {
                $this->serpLiveHits++;
            }
            usleep(1000000);

            return $parsed;
        } catch (\Throwable $e) {
            Log::warning('amazon:collect-reviews SerpApi failed', [
                'asin' => $asin,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{rating: float|null, review_count: int, source: string}|null
     */
    private function cachedSerpReview(string $asin): ?array
    {
        $cacheKey = $this->serpCacheKey($asin);
        $fromRuntime = Cache::get($cacheKey);
        if (is_array($fromRuntime) && array_key_exists('review_count', $fromRuntime)) {
            return $fromRuntime;
        }

        if (Schema::hasTable('serp_api_raw_responses')) {
            try {
                $row = SerpApiRawResponse::query()
                    ->where('success', true)
                    ->where('search_query', $asin)
                    ->where('marketplace', 'amazon_product')
                    ->orderByDesc('id')
                    ->first();
                if ($row) {
                    $body = is_string($row->raw_body) ? json_decode($row->raw_body, true) : null;
                    $parsed = $this->parseSerpProductResults(is_array($body) ? $body : []);
                    if ($parsed !== null) {
                        Cache::put($cacheKey, $parsed, now()->addHours(24));

                        return $parsed;
                    }
                }
            } catch (\Throwable $e) {
                // fall through to stored search-index
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{rating: float|null, review_count: int, source: string}|null  $parsed
     */
    private function storeSerpReviewCache(string $asin, mixed $data, ?array $parsed, int $httpStatus): void
    {
        if ($parsed !== null) {
            Cache::put($this->serpCacheKey($asin), $parsed, now()->addHours(24));
        }

        if (! Schema::hasTable('serp_api_raw_responses') || ! is_array($data)) {
            return;
        }

        try {
            $body = json_encode($data);
            if ($body === false) {
                return;
            }
            SerpApiRawResponse::create([
                'search_query' => $asin,
                'page' => 1,
                'marketplace' => 'amazon_product',
                'request_params' => [
                    'engine' => 'amazon_product',
                    'amazon_domain' => 'amazon.com',
                    'asin' => $asin,
                    'no_cache' => 'false',
                    'api_key' => '(cached)',
                ],
                'http_status' => $httpStatus,
                'raw_body' => $body,
                'success' => $httpStatus >= 200 && $httpStatus < 300 && empty($data['error']),
            ]);
        } catch (\Throwable $e) {
            // Cache write is best-effort — do not fail the reviews run.
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{rating: float|null, review_count: int, source: string}|null
     */
    private function parseSerpProductResults(array $data): ?array
    {
        if ($data === [] || ! empty($data['error'])) {
            return null;
        }
        $pr = $data['product_results'] ?? null;
        if (! is_array($pr)) {
            return null;
        }
        $rating = isset($pr['rating']) && is_numeric($pr['rating']) ? (float) $pr['rating'] : null;
        $reviews = isset($pr['reviews']) && is_numeric($pr['reviews']) ? (int) $pr['reviews'] : null;
        if ($rating === null && $reviews === null) {
            return null;
        }

        return [
            'rating' => $rating !== null ? round($rating, 2) : null,
            'review_count' => $reviews ?? 0,
            'source' => 'serpapi_amazon',
        ];
    }

    private function serpCacheKey(string $asin): string
    {
        return 'serpapi:amazon_product:rating:'.strtoupper($asin);
    }

    /**
     * One cached/live SerpApi lookup per variation family. Overwrites Jungle Scout.
     * Never retries 429 (that burns the SerpApi token).
     *
     * @param  list<string>  $asins
     * @param  array<string, array{rating: mixed, review_count: int, source: string}>  $reviewByAsin
     */
    private function overwriteWithSerpApi(array $asins, array &$reviewByAsin): int
    {
        $families = $this->variationFamilyMap($asins);
        $filled = 0;

        foreach ($families as $familyKey => $members) {
            $targets = array_values(array_filter(
                $members,
                static fn ($asin) => in_array($asin, $asins, true)
            ));
            if ($targets === []) {
                continue;
            }

            $live = $this->liveFamilyRating($targets, $reviewByAsin);
            if ($live === null) {
                $probe = in_array($familyKey, $targets, true) ? $familyKey : $targets[0];
                if (! $this->serpLiveDisabled) {
                    $this->line('SerpApi '.$probe.' (family '.$familyKey.', '.count($targets).' ASINs, cache-first)');
                }
                $live = $this->serpApiReviewForAsin($probe);
            }
            if ($live === null) {
                continue;
            }

            foreach ($targets as $asin) {
                $src = $reviewByAsin[$asin]['source'] ?? '';
                if ($this->isLiveReviewSource((string) $src)) {
                    continue;
                }
                $reviewByAsin[$asin] = $live;
                $filled++;
            }
        }

        return $filled;
    }

    /**
     * Reuse a rating we already stored this run (or earlier) so a family
     * does not spend another SerpApi search.
     *
     * @param  list<string>  $targets
     * @param  array<string, array{rating: mixed, review_count: int, source: string}>  $reviewByAsin
     * @return array{rating: mixed, review_count: int, source: string}|null
     */
    private function liveFamilyRating(array $targets, array $reviewByAsin): ?array
    {
        foreach ($targets as $asin) {
            $row = $reviewByAsin[$asin] ?? null;
            if (! is_array($row)) {
                continue;
            }
            $src = (string) ($row['source'] ?? '');
            if ($this->isLiveReviewSource($src)) {
                return $row;
            }
        }

        try {
            $existing = AmazonProductReview::query()
                ->whereIn('asin', $targets)
                ->whereIn('source', ['amazon_ads_bp', 'amazon_sp_catalog', 'serpapi_amazon', 'amazon_pdp'])
                ->whereNotNull('product_rating')
                ->orderByDesc('fetched_at')
                ->orderByDesc('id')
                ->first();
            if ($existing) {
                return [
                    'rating' => (float) $existing->product_rating,
                    'review_count' => (int) ($existing->review_count ?? 0),
                    'source' => (string) $existing->source,
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /**
     * @param  list<string>  $asins
     * @return array<string, list<string>>
     */
    private function variationFamilyMap(array $asins): array
    {
        $upper = array_values(array_unique(array_map(
            static fn ($a) => strtoupper(trim((string) $a)),
            $asins
        )));
        $rows = JungleScoutProductData::query()->orderByDesc('id')->get(['asin', 'data']);

        $families = [];
        $seen = [];
        foreach ($rows as $row) {
            $asin = strtoupper(trim((string) $row->asin));
            if ($asin === '' || isset($seen[$asin])) {
                continue;
            }
            $seen[$asin] = true;
            $data = is_array($row->data) ? $row->data : (json_decode($row->data ?? '[]', true) ?: []);
            if (! is_array($data)) {
                $data = [];
            }
            $parent = strtoupper(trim((string) ($data['parent_asin'] ?? '')));
            $key = $parent !== '' ? $parent : $asin;
            $families[$key][$asin] = true;
            if ($parent !== '') {
                $families[$key][$parent] = true;
            }
            foreach ((array) ($data['variants'] ?? []) as $variant) {
                $v = strtoupper(trim((string) $variant));
                if ($v !== '') {
                    $families[$key][$v] = true;
                }
            }
        }

        foreach ($upper as $asin) {
            if (! isset($seen[$asin])) {
                $families[$asin][$asin] = true;
            }
        }

        $out = [];
        foreach ($families as $key => $members) {
            $out[$key] = array_keys($members);
        }

        return $out;
    }

    /**
     * @param  list<string>  $sources
     */
    private function isLiveReviewSource(string $source): bool
    {
        return in_array($source, ['amazon_ads_bp', 'amazon_sp_catalog', 'serpapi_amazon', 'amazon_pdp'], true);
    }

    private function disableLiveSerpIfNoQuota(): void
    {
        if ($this->serpLiveDisabled) {
            return;
        }

        $apiKey = (string) config('services.serpapi.key');
        if ($apiKey === '') {
            $this->serpLiveDisabled = true;
            $this->warn('SerpApi key missing — cache only.');

            return;
        }

        try {
            $acct = Http::timeout(15)->get('https://serpapi.com/account.json', [
                'api_key' => $apiKey,
            ])->json();
            if (! is_array($acct)) {
                return;
            }
            $left = (int) ($acct['total_searches_left'] ?? $acct['plan_searches_left'] ?? 0);
            $used = (int) ($acct['this_month_usage'] ?? 0);
            $plan = (int) ($acct['searches_per_month'] ?? 0);
            if ($left <= 0) {
                $this->serpLiveDisabled = true;
                $this->warn("SerpApi has 0 searches left ({$used}/{$plan}). Live pull skipped — cache only.");
                Log::warning('amazon:collect-reviews SerpApi quota empty', [
                    'used' => $used,
                    'plan' => $plan,
                ]);
            } else {
                $this->info("SerpApi searches left: {$left}");
            }
        } catch (\Throwable $e) {
            $this->warn('SerpApi account check failed — will attempt live until 429.');
        }
    }

    /**
     * @return array{rating: float, review_count: int, source: string}|null
     */
    private function jungleScoutReviewForAsin(string $asin): ?array
    {
        $asin = strtoupper(trim($asin));
        if ($asin === '') {
            return null;
        }

        $row = JungleScoutProductData::query()
            ->whereRaw('UPPER(TRIM(asin)) = ?', [$asin])
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            return null;
        }

        $data = is_array($row->data) ? $row->data : (json_decode($row->data ?? '[]', true) ?: []);
        if (! is_array($data)) {
            return null;
        }

        // data may be a list of product entries or a single associative payload
        $entries = array_is_list($data) ? $data : [$data];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $rating = $entry['rating'] ?? $entry['data']['rating'] ?? null;
            $reviews = $entry['reviews'] ?? $entry['data']['reviews'] ?? null;
            if (is_numeric($rating) && (float) $rating > 0) {
                return [
                    'rating' => round((float) $rating, 2),
                    'review_count' => is_numeric($reviews) ? (int) $reviews : 0,
                    'source' => 'junglescout_fallback',
                ];
            }
        }

        return null;
    }
}
