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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull avg star rating + review count for Amazon SKUs via Advertising API
 * (Brand Posts product list → customerReviewSummary), with SP-API Catalog
 * customerReviews then Jungle Scout as fallbacks when Ads does not return ratings.
 */
class CollectAmazonReviews extends Command
{
    use MonitorsCronExecution;

    protected $signature = 'amazon:collect-reviews
        {--chunk=20 : ASINs per Ads Brand Posts request}
        {--limit=0 : Max SKUs to process (0 = all)}
        {--sku= : Optional single SKU}
        {--ads-only : Do not fall back to SP-API / Jungle Scout}';

    protected $description = 'Collect Amazon avg rating + review count via Ads API (Brand Posts), with SP-API / Jungle Scout fallbacks';

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
                $this->warn('→ Continuing with Jungle Scout fallback for ratings/reviews.');
                Log::warning('amazon:collect-reviews Ads Brand Posts disabled for run', [
                    'error' => $e->getMessage(),
                ]);
            }

            if ($useSpCatalog) {
                try {
                    $probe = $spApi->getCatalogReviewSummaryByAsin($probeAsin);
                    if ($probe['rating'] === null && $probe['review_count'] === null) {
                        $useSpCatalog = false;
                        $this->warn('SP-API Catalog customerReviews unavailable — skipping SP-API for this run.');
                    } else {
                        $this->info('SP-API Catalog reviews: OK.');
                        $monitor->markApiConnected(true);
                    }
                } catch (\Throwable $e) {
                    $useSpCatalog = false;
                    $this->warn('SP-API Catalog unavailable — skipping: '.$this->shortApiError($e));
                }
            }
        }

        $chunks = array_chunk($asins, $chunkSize);
        $chunkTotal = count($chunks);
        foreach ($chunks as $chunkIndex => $asinChunk) {
            $reviewByAsin = [];
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

            // 2) Fallback — SP-API Catalog customerReviews (only if probe succeeded)
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

            // 3) Fallback — Jungle Scout (same source the Rating column uses today)
            if (! $adsOnly) {
                foreach ($asinChunk as $asin) {
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

            foreach ($asinChunk as $asin) {
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

                        // Only set optional columns when migration has run
                        if (! Schema::hasColumn('amazon_product_reviews', 'asin')) {
                            unset($attrs['asin']);
                        }
                        if (! Schema::hasColumn('amazon_product_reviews', 'source')) {
                            unset($attrs['source']);
                        }
                        if (! Schema::hasColumn('amazon_product_reviews', 'fetched_at')) {
                            unset($attrs['fetched_at']);
                        }

                        // Match existing row by SKU case-insensitively when present
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

            $this->info('Chunk done — reviews found for '.count($reviewByAsin).'/'.count($asinChunk).' ASINs');
        }

        $monitor->setUpdated($updated);
        $monitor->setSkipped($skipped);
        $monitor->setFailed($failed);
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
