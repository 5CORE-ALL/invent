<?php

namespace App\Services\Reviews;

use App\Models\AmazonDatasheet;
use App\Models\MarketplaceReview;
use App\Models\WalmartPriceData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pulls customer reviews for a SKU from a marketplace and stores them in marketplace_reviews.
 *
 * Marketplace seller APIs return only an average rating and a count, not review text. The
 * review text that can be read automatically comes from SerpApi:
 *   - Walmart: walmart_product_reviews (full reviews, paged)
 *   - Amazon:  amazon_product -> reviews_information (the reviews SerpApi exposes for the ASIN)
 * Every other marketplace is filled by CSV import or by hand on the Reviews page.
 */
class MarketplaceReviewFetcher
{
    /** @var list<string> */
    public const AUTO_FETCH = ['amazon', 'walmart'];

    public static function canAutoFetch(string $marketplace): bool
    {
        return in_array(strtolower($marketplace), self::AUTO_FETCH, true);
    }

    /**
     * @return array{fetched: int, new: int, error: string|null}
     */
    public function fetch(string $marketplace, string $sku, int $maxPages = 3): array
    {
        $marketplace = strtolower(trim($marketplace));
        $sku = trim($sku);
        if ($sku === '') {
            return ['fetched' => 0, 'new' => 0, 'error' => 'SKU is empty'];
        }
        if (! Schema::hasTable('marketplace_reviews')) {
            return ['fetched' => 0, 'new' => 0, 'error' => 'marketplace_reviews table is missing, run php artisan migrate'];
        }
        if (! (string) config('services.serpapi.key')) {
            return ['fetched' => 0, 'new' => 0, 'error' => 'SERPAPI_KEY is not set'];
        }

        try {
            return match ($marketplace) {
                'walmart' => $this->fetchWalmart($sku, $maxPages),
                'amazon' => $this->fetchAmazon($sku),
                default => ['fetched' => 0, 'new' => 0, 'error' => 'No automatic review source for '.$marketplace.' — use CSV import'],
            };
        } catch (\Throwable $e) {
            Log::warning('MarketplaceReviewFetcher failed', ['marketplace' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['fetched' => 0, 'new' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Store one review. Returns true when it was new.
     *
     * @param  array<string, mixed>  $r  rating, title, body, reviewer, date, external_id, url, verified, images
     */
    public function store(string $marketplace, string $sku, array $r, string $source, ?string $externalProductId = null): bool
    {
        $marketplace = strtolower(trim($marketplace));
        $body = trim((string) ($r['body'] ?? ''));
        $title = trim((string) ($r['title'] ?? ''));
        $rating = isset($r['rating']) && is_numeric($r['rating']) ? (int) round((float) $r['rating']) : null;
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            $rating = null;
        }
        if ($body === '' && $title === '' && $rating === null) {
            return false;
        }

        $date = $this->parseDate($r['date'] ?? null);
        $externalId = isset($r['external_id']) ? trim((string) $r['external_id']) : '';
        $reviewer = trim((string) ($r['reviewer'] ?? ''));
        $fingerprint = MarketplaceReview::fingerprintFor($marketplace, $sku, $externalId, $reviewer, $body !== '' ? $body : $title, $date);

        if (MarketplaceReview::query()->where('fingerprint', $fingerprint)->exists()) {
            return false;
        }

        MarketplaceReview::query()->create([
            'sku' => $sku,
            'marketplace' => $marketplace,
            'external_product_id' => $externalProductId,
            'external_review_id' => $externalId !== '' ? mb_substr($externalId, 0, 191) : null,
            'rating' => $rating,
            'title' => $title !== '' ? mb_substr($title, 0, 255) : null,
            'body' => $body !== '' ? $body : null,
            'reviewer_name' => $reviewer !== '' ? mb_substr($reviewer, 0, 191) : null,
            'verified' => (bool) ($r['verified'] ?? false),
            'reviewed_at' => $date,
            'review_url' => isset($r['url']) && $r['url'] !== '' ? mb_substr((string) $r['url'], 0, 500) : null,
            'images' => ! empty($r['images']) && is_array($r['images']) ? array_values($r['images']) : null,
            'source' => $source,
            'fingerprint' => $fingerprint,
            // A review without a star rating cannot be posted as a Shopify review.
            'shopify_status' => $rating === null ? MarketplaceReview::SHOPIFY_SKIPPED : MarketplaceReview::SHOPIFY_PENDING,
            'shopify_error' => $rating === null ? 'No star rating' : null,
        ]);

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $rows  sku, marketplace, rating, title, body, reviewer, date, review_id
     * @return array{saved: int, skipped: int, errors: int}
     */
    public function importRows(array $rows, ?string $defaultMarketplace = null): array
    {
        $stats = ['saved' => 0, 'skipped' => 0, 'errors' => 0];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $marketplace = strtolower(trim((string) ($row['marketplace'] ?? ''))) ?: strtolower((string) $defaultMarketplace);
            if ($sku === '' || $marketplace === '') {
                $stats['errors']++;

                continue;
            }
            try {
                $isNew = $this->store($marketplace, $sku, [
                    'rating' => $row['rating'] ?? null,
                    'title' => $row['title'] ?? ($row['review_title'] ?? ''),
                    'body' => $row['body'] ?? ($row['review_text'] ?? ($row['review'] ?? '')),
                    'reviewer' => $row['reviewer'] ?? ($row['reviewer_name'] ?? ($row['name'] ?? '')),
                    'date' => $row['date'] ?? ($row['review_date'] ?? null),
                    'external_id' => $row['review_id'] ?? '',
                    'verified' => in_array(strtolower((string) ($row['verified'] ?? '')), ['1', 'yes', 'true', 'verified'], true),
                ], 'csv');
                $isNew ? $stats['saved']++ : $stats['skipped']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * @return array{fetched: int, new: int, error: string|null}
     */
    protected function fetchWalmart(string $sku, int $maxPages): array
    {
        $itemId = WalmartPriceData::query()->where('sku', $sku)->whereNotNull('item_id')->value('item_id');
        $itemId = trim((string) $itemId);
        if ($itemId === '') {
            return ['fetched' => 0, 'new' => 0, 'error' => 'No Walmart item id for this SKU'];
        }

        $fetched = 0;
        $new = 0;
        for ($page = 1; $page <= max(1, $maxPages); $page++) {
            $res = $this->serp(['engine' => 'walmart_product_reviews', 'product_id' => $itemId, 'page' => $page, 'sort' => 'submission-desc']);
            if (isset($res['error'])) {
                // "hasn't returned any results" simply means there are no more pages.
                return ['fetched' => $fetched, 'new' => $new, 'error' => $page === 1 ? (string) $res['error'] : null];
            }
            $list = is_array($res['reviews'] ?? null) ? $res['reviews'] : [];
            if ($list === []) {
                break;
            }
            foreach ($list as $rv) {
                if (! is_array($rv)) {
                    continue;
                }
                $fetched++;
                $new += $this->store('walmart', $sku, [
                    'rating' => $rv['rating'] ?? null,
                    'title' => $rv['title'] ?? '',
                    'body' => $rv['text'] ?? ($rv['review_text'] ?? ''),
                    'reviewer' => $rv['user_nickname'] ?? '',
                    'date' => $rv['review_submission_time'] ?? ($rv['date'] ?? null),
                    'external_id' => $rv['review_id'] ?? ($rv['id'] ?? ''),
                    'verified' => stripos((string) ($rv['customer_type'] ?? ''), 'verified') !== false,
                    'images' => is_array($rv['images'] ?? null) ? array_values(array_filter(array_map(
                        static fn ($i) => is_array($i) ? ($i['url'] ?? $i['link'] ?? null) : (is_string($i) ? $i : null),
                        $rv['images']
                    ))) : [],
                ], 'serpapi', $itemId) ? 1 : 0;
            }
        }

        return ['fetched' => $fetched, 'new' => $new, 'error' => null];
    }

    /**
     * @return array{fetched: int, new: int, error: string|null}
     */
    protected function fetchAmazon(string $sku): array
    {
        $asin = AmazonDatasheet::query()->where('sku', $sku)->whereNotNull('asin')->where('asin', '!=', '')->value('asin');
        $asin = strtoupper(trim((string) $asin));
        if ($asin === '') {
            return ['fetched' => 0, 'new' => 0, 'error' => 'No ASIN for this SKU'];
        }

        $res = $this->serp(['engine' => 'amazon_product', 'amazon_domain' => 'amazon.com', 'asin' => $asin]);
        if (isset($res['error'])) {
            return ['fetched' => 0, 'new' => 0, 'error' => (string) $res['error']];
        }

        $info = is_array($res['reviews_information'] ?? null) ? $res['reviews_information'] : [];
        $list = [];
        foreach (['authors_reviews', 'top_reviews', 'reviews'] as $key) {
            if (is_array($info[$key] ?? null) && isset($info[$key][0])) {
                $list = array_merge($list, $info[$key]);
            }
        }

        $fetched = 0;
        $new = 0;
        foreach ($list as $rv) {
            if (! is_array($rv)) {
                continue;
            }
            $fetched++;
            $new += $this->store('amazon', $sku, [
                'rating' => $rv['rating'] ?? null,
                'title' => $rv['title'] ?? '',
                'body' => $rv['text'] ?? ($rv['body'] ?? ''),
                'reviewer' => $rv['name'] ?? ($rv['author'] ?? ''),
                'date' => $rv['date'] ?? null,
                'external_id' => $rv['id'] ?? ($rv['review_id'] ?? ''),
                'url' => $rv['link'] ?? '',
                'verified' => (bool) ($rv['verified_purchase'] ?? false),
                'images' => is_array($rv['images'] ?? null) ? array_values(array_filter(array_map(
                    static fn ($i) => is_array($i) ? ($i['link'] ?? $i['url'] ?? null) : (is_string($i) ? $i : null),
                    $rv['images']
                ))) : [],
            ], 'serpapi', $asin) ? 1 : 0;
        }

        return [
            'fetched' => $fetched,
            'new' => $new,
            'error' => $fetched === 0 ? 'Amazon returned no readable reviews for '.$asin.' (import a CSV for this one)' : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function serp(array $params): array
    {
        $response = Http::withoutVerifying()->timeout(60)->get('https://serpapi.com/search.json', $params + [
            'api_key' => config('services.serpapi.key'),
        ]);
        $json = $response->json();

        return is_array($json) ? $json : ['error' => 'SerpApi HTTP '.$response->status()];
    }

    protected function parseDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse(preg_replace('/^Reviewed in .*? on /i', '', $value) ?? $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
