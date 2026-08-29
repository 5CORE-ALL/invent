<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Models\AmazonDatasheet;
use App\Models\AmazonProductReview;
use App\Models\JungleScoutProductData;
use App\Services\AmazonAdsService;
use App\Services\AmazonSpApiService;
use App\Services\CronMonitor\CronExecutionContext;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull avg star rating + review count for Amazon SKUs via Advertising API
 * (Brand Posts product list → customerReviewSummary), with SP-API Catalog
 * customerReviews, then live SerpApi amazon_product (the Amazon PDP rating),
 * then Jungle Scout as last resort.
 */
class CollectAmazonReviews extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'amazon:collect-reviews
        {--chunk=20 : ASINs per Ads Brand Posts request}
        {--limit=0 : Max SKUs to process (0 = all)}
        {--sku= : Optional single SKU}
        {--ads-only : Do not fall back to SP-API / SerpApi / Jungle Scout}
        {--no-serp : Skip SerpApi live Amazon PDP ratings}';

    protected $description = 'Collect Amazon avg rating + review count via Ads API, SP-API, live Amazon (SerpApi), then Jungle Scout';

    protected string $monitorJobName = 'Amazon Collect Reviews';

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
                $this->warn('→ Continuing with live Amazon PDP / Jungle Scout fallbacks.');
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

            $this->info('Chunk done — live Amazon API hits: '.$this->countLiveReviews($reviewByAsin, $asinChunk).'/'.count($asinChunk));
        }

        // 3) One live Amazon PDP rating per variation family (shoppers see one family rating)
        if (! $adsOnly && ! $this->option('no-serp')) {
            $filled = $this->fillMissingFromFamilySerp($asins, $reviewByAsin);
            if ($filled > 0) {
                $monitor->markApiConnected(true);
                $this->info("SerpApi family fill: {$filled} ASIN(s)");
            }
        }

        // 4) Last resort — Jungle Scout cache (often weeks stale vs the Amazon PDP)
        if (! $adsOnly) {
            foreach ($asins as $asin) {
                if (isset($reviewByAsin[$asin])) {
                    continue;
                }
                $js = $this->jungleScoutReviewForAsin($asin);
                if ($js !== null) {
                    $reviewByAsin[$asin] = $js;
                } else {
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
     * Live amazon.com PDP rating via SerpApi (engine=amazon_product).
     *
     * @return array{rating: float, review_count: int, source: string}|null
     */
    private function serpApiReviewForAsin(string $asin): ?array
    {
        $asin = strtoupper(trim($asin));
        $apiKey = (string) config('services.serpapi.key');
        if ($asin === '' || $apiKey === '') {
            return null;
        }

        try {
            $response = null;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $response = Http::timeout(25)->get('https://serpapi.com/search', [
                    'engine' => 'amazon_product',
                    'amazon_domain' => 'amazon.com',
                    'asin' => $asin,
                    'api_key' => $apiKey,
                ]);
                if ($response->status() !== 429) {
                    break;
                }
                sleep(5 * ($attempt + 1));
            }
            if (! $response || ! $response->successful()) {
                return null;
            }
            $data = $response->json();
            if (! is_array($data) || ! empty($data['error'])) {
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
        } catch (\Throwable $e) {
            Log::warning('amazon:collect-reviews SerpApi failed', [
                'asin' => $asin,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, array{rating: mixed, review_count: int, source: string}>  $reviewByAsin
     * @param  list<string>  $asinChunk
     */
    private function countLiveReviews(array $reviewByAsin, array $asinChunk): int
    {
        $n = 0;
        foreach ($asinChunk as $asin) {
            if (isset($reviewByAsin[$asin])) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Amazon shows one rating for a variation family. Fetch the parent (or first
     * child) once via SerpApi and copy that PDP rating onto every family ASIN.
     *
     * @param  list<string>  $asins
     * @param  array<string, array{rating: mixed, review_count: int, source: string}>  $reviewByAsin
     */
    private function fillMissingFromFamilySerp(array $asins, array &$reviewByAsin): int
    {
        $families = $this->variationFamilyMap($asins);
        $filled = 0;
        $fetchedKey = [];

        foreach ($families as $familyKey => $members) {
            $missing = [];
            $live = null;
            foreach ($members as $member) {
                if (! in_array($member, $asins, true) && $member !== $familyKey) {
                    continue;
                }
                if (isset($reviewByAsin[$member]) && $this->isLiveReviewSource($reviewByAsin[$member]['source'] ?? '')) {
                    $live = $reviewByAsin[$member];
                } elseif (in_array($member, $asins, true) && ! isset($reviewByAsin[$member])) {
                    $missing[] = $member;
                }
            }
            if ($missing === [] && $live === null) {
                continue;
            }
            if ($live === null) {
                if (isset($fetchedKey[$familyKey])) {
                    $live = $fetchedKey[$familyKey];
                } else {
                    $probeAsin = in_array($familyKey, $asins, true) ? $familyKey : ($missing[0] ?? $members[0] ?? null);
                    $live = $probeAsin ? $this->serpApiReviewForAsin($probeAsin) : null;
                    $fetchedKey[$familyKey] = $live;
                    usleep(200000);
                }
            }
            if ($live === null) {
                continue;
            }
            foreach ($members as $member) {
                if (! in_array($member, $asins, true)) {
                    continue;
                }
                if (isset($reviewByAsin[$member]) && $this->isLiveReviewSource($reviewByAsin[$member]['source'] ?? '')) {
                    continue;
                }
                $reviewByAsin[$member] = $live;
                $filled++;
            }
        }

        // ASINs with no Jungle Scout family row — still try the PDP once
        foreach ($asins as $asin) {
            if (isset($reviewByAsin[$asin])) {
                continue;
            }
            $serp = $this->serpApiReviewForAsin($asin);
            if ($serp !== null) {
                $reviewByAsin[$asin] = $serp;
                $filled++;
            }
            usleep(200000);
        }

        return $filled;
    }

    /**
     * @param  list<string>  $asins
     * @return array<string, list<string>>  familyKey => member ASINs
     */
    private function variationFamilyMap(array $asins): array
    {
        $upper = array_values(array_unique(array_map(static fn ($a) => strtoupper(trim((string) $a)), $asins)));
        if ($upper === []) {
            return [];
        }

        $rows = JungleScoutProductData::query()
            ->orderByDesc('id')
            ->get(['asin', 'data']);

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
            $variants = $data['variants'] ?? [];
            if (is_array($variants)) {
                foreach ($variants as $variant) {
                    $v = strtoupper(trim((string) $variant));
                    if ($v !== '') {
                        $families[$key][$v] = true;
                    }
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

    private function isLiveReviewSource(string $source): bool
    {
        return in_array($source, ['amazon_ads_bp', 'amazon_sp_catalog', 'serpapi_amazon'], true);
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
