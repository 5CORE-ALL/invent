<?php

namespace App\Services\Reviews;

use App\Models\AliexpressMetric;
use App\Models\AmazonDatasheet;
use App\Models\MarketplaceReview;
use App\Models\Temu2Metric;
use App\Models\Temu3Metric;
use App\Models\TemuMetric;
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
    public const AUTO_FETCH = ['amazon', 'walmart', 'aliexpress', 'temu', 'temu2', 'temu3', 'ebay1', 'ebay2', 'ebay3', 'reverb'];

    public static function canAutoFetch(string $marketplace): bool
    {
        return in_array(strtolower($marketplace), self::AUTO_FETCH, true);
    }

    /** Fetched for the whole seller account in pages (not product by product). */
    public static function isAccountWide(string $marketplace): bool
    {
        return MarketplaceFeedbackSources::handles($marketplace);
    }

    /**
     * Pull `$pagesPerCall` pages of account-wide feedback (eBay / Reverb) and store them against our SKUs.
     *
     * @return array{total_pages: int, next_page: int|null, fetched: int, new: int, error: string|null}
     */
    public function fetchAccountPages(string $marketplace, int $startPage = 1, int $pagesPerCall = 2): array
    {
        $marketplace = strtolower(trim($marketplace));
        $sources = app(MarketplaceFeedbackSources::class);
        $startPage = max(1, $startPage);
        $fetched = 0;
        $new = 0;
        $totalPages = 0;
        $page = $startPage;

        for ($i = 0; $i < $pagesPerCall; $i++, $page++) {
            $res = $sources->page($marketplace, $page);
            if ($res['error'] !== null) {
                return ['total_pages' => $totalPages, 'next_page' => null, 'fetched' => $fetched, 'new' => $new, 'error' => $res['error']];
            }
            $totalPages = $res['total_pages'];
            $reviews = $res['reviews'];
            $skuByItem = $sources->skuMap($marketplace, array_column($reviews, 'item_id'));

            // One query for what is already stored, instead of one per review.
            $rows = [];
            foreach ($reviews as $rv) {
                $itemId = (string) ($rv['item_id'] ?? '');
                $sku = $skuByItem[$itemId] ?? ('['.$marketplace.'] item '.($itemId !== '' ? $itemId : 'unknown'));
                $rv['sku'] = $sku;
                $rv['fp'] = MarketplaceReview::fingerprintFor($marketplace, $sku, $rv['external_id'] ?? '', $rv['reviewer'] ?? '', $rv['body'] ?? '', $this->parseDate($rv['date'] ?? null));
                $rows[] = $rv;
            }
            $known = $rows === [] ? [] : array_flip(MarketplaceReview::query()->whereIn('fingerprint', array_column($rows, 'fp'))->pluck('fingerprint')->all());

            foreach ($rows as $rv) {
                $fetched++;
                if (isset($known[$rv['fp']])) {
                    continue;
                }
                $new += $this->store($marketplace, $rv['sku'], $rv, 'api', (string) ($rv['item_id'] ?? '') ?: null) ? 1 : 0;
            }

            if ($page >= $totalPages) {
                return ['total_pages' => $totalPages, 'next_page' => null, 'fetched' => $fetched, 'new' => $new, 'error' => null];
            }
        }

        return ['total_pages' => $totalPages, 'next_page' => $page, 'fetched' => $fetched, 'new' => $new, 'error' => null];
    }

    /**
     * SKUs (with a marketplace product id) that can be fetched one by one.
     *
     * @return list<string>
     */
    public function fetchableSkus(string $marketplace, string $onlySku = ''): array
    {
        [$model, $idColumn] = match ($marketplace) {
            'walmart' => [WalmartPriceData::class, 'item_id'],
            'amazon' => [AmazonDatasheet::class, 'asin'],
            'aliexpress' => [AliexpressMetric::class, 'product_id'],
            'temu' => [TemuMetric::class, 'goods_id'],
            'temu2' => [Temu2Metric::class, 'goods_id'],
            'temu3' => [Temu3Metric::class, 'goods_id'],
            default => [null, null],
        };
        if ($model === null) {
            return [];
        }

        $q = $model::query()->whereNotNull($idColumn)->where($idColumn, '!=', '')->whereNotNull('sku')->where('sku', '!=', '');
        if ($onlySku !== '') {
            $q->where('sku', $onlySku);
        }

        return $q->orderBy('sku')->distinct()->pluck('sku')
            ->map(static fn ($s) => trim((string) $s))->filter()->unique()->values()->all();
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
        if (in_array($marketplace, ['amazon', 'walmart'], true) && ! (string) config('services.serpapi.key')) {
            return ['fetched' => 0, 'new' => 0, 'error' => 'SERPAPI_KEY is not set'];
        }

        try {
            return match ($marketplace) {
                'walmart' => $this->fetchWalmart($sku, $maxPages),
                'amazon' => $this->fetchAmazon($sku),
                'aliexpress' => $this->fetchAliexpress($sku, $maxPages),
                'temu', 'temu2', 'temu3' => $this->fetchTemu($marketplace, $sku, $maxPages),
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
    public function store(string $marketplace, string $sku, array $r, string $source, ?string $externalProductId = null, ?string $shopifyStatus = null, ?string $shopifyNote = null): bool
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
            'shopify_status' => $rating === null
                ? MarketplaceReview::SHOPIFY_SKIPPED
                : ($shopifyStatus ?? MarketplaceReview::SHOPIFY_PENDING),
            'shopify_error' => $rating === null ? 'No star rating' : $shopifyNote,
            'shopify_pushed_at' => $shopifyStatus === MarketplaceReview::SHOPIFY_PUSHED ? now() : null,
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
            // Judge.me export / import format: product_handle + metaobject_handle + status columns.
            $judgeMe = array_key_exists('product_handle', $row) && ! isset($row['sku']);
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($judgeMe) {
                $sku = $this->skuForProductHandle(trim((string) ($row['product_handle'] ?? '')), trim((string) ($row['product_id'] ?? '')));
            }
            $marketplace = strtolower(trim((string) ($row['marketplace'] ?? '')))
                ?: strtolower((string) $defaultMarketplace)
                ?: ($judgeMe ? 'judgeme' : '');
            if ($sku === '' || $marketplace === '') {
                $stats['errors']++;

                continue;
            }

            $shopifyStatus = null;
            $note = null;
            $images = [];
            if ($judgeMe) {
                // A Judge.me export is what is already on the store: do not push it again.
                $published = strcasecmp(trim((string) ($row['status'] ?? 'Published')), 'Published') === 0;
                $shopifyStatus = $published ? MarketplaceReview::SHOPIFY_PUSHED : MarketplaceReview::SHOPIFY_SKIPPED;
                $note = $published ? 'Already in Judge.me (imported from a Judge.me CSV)' : 'Hidden or archived in Judge.me';
                $images = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', (string) ($row['picture_urls'] ?? '')) ?: [])));
            }

            try {
                $isNew = $this->store($marketplace, $sku, [
                    'rating' => $row['rating'] ?? null,
                    'title' => $row['title'] ?? ($row['review_title'] ?? ''),
                    'body' => $row['body'] ?? ($row['review_text'] ?? ($row['review'] ?? '')),
                    'reviewer' => $row['reviewer'] ?? ($row['reviewer_name'] ?? ($row['name'] ?? '')),
                    'date' => $row['date'] ?? ($row['review_date'] ?? null),
                    'external_id' => $row['review_id'] ?? ($row['metaobject_handle'] ?? ''),
                    'verified' => in_array(strtolower((string) ($row['verified'] ?? '')), ['1', 'yes', 'true', 'verified'], true),
                    'images' => $images,
                ], $judgeMe ? 'judgeme' : 'csv', null, $shopifyStatus, $note);
                $isNew ? $stats['saved']++ : $stats['skipped']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /** @var array<string, string> */
    protected array $handleSkuCache = [];

    /**
     * Our SKU for a Shopify product (matched on the /products/{handle} part of shopify_skus.product_link).
     * When nothing matches the handle itself is kept, so the review is still stored and visible.
     */
    protected function skuForProductHandle(string $handle, string $productId): string
    {
        $key = $handle !== '' ? $handle : ('id:'.$productId);
        if ($key === 'id:') {
            return '';
        }
        if (isset($this->handleSkuCache[$key])) {
            return $this->handleSkuCache[$key];
        }

        $sku = '';
        if ($handle !== '' && Schema::hasTable('shopify_skus')) {
            $like = '%/products/'.str_replace(['%', '_'], ['\\%', '\\_'], $handle).'%';
            $sku = trim((string) \App\Models\ShopifySku::query()
                ->where('product_link', 'like', $like)
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->orderBy('id')->value('sku'));
        }

        return $this->handleSkuCache[$key] = ($sku !== '' ? $sku : $key);
    }

    /**
     * AliExpress buyer evaluations through the seller Open Platform.
     *
     * @return array{fetched: int, new: int, error: string|null}
     */
    protected function fetchAliexpress(string $sku, int $maxPages): array
    {
        $productId = trim((string) AliexpressMetric::query()->where('sku', $sku)->whereNotNull('product_id')->value('product_id'));
        if ($productId === '') {
            return ['fetched' => 0, 'new' => 0, 'error' => 'No AliExpress product id for this SKU'];
        }

        $svc = app(\App\Services\AliExpressApiService::class);
        $fetched = 0;
        $new = 0;
        for ($page = 1; $page <= max(1, $maxPages); $page++) {
            $res = $svc->fetchProductEvaluations($productId, $page, 20);
            if (empty($res['success'])) {
                return ['fetched' => $fetched, 'new' => $new, 'error' => $page === 1 ? (string) ($res['message'] ?? 'AliExpress review call failed') : null];
            }
            $list = $this->findRows($res['data'] ?? [], ['feedback', 'evaluation']);
            if ($list === []) {
                break;
            }
            foreach ($list as $rv) {
                $fetched++;
                $epoch = $rv['feedback_epoch_date'] ?? ($rv['evaluation_time'] ?? null);
                $date = is_numeric($epoch) ? Carbon::createFromTimestamp((int) ((float) $epoch > 99999999999 ? (float) $epoch / 1000 : (float) $epoch))->toDateTimeString() : $epoch;
                $images = $rv['image_urls'] ?? ($rv['images'] ?? []);
                $images = is_string($images) ? preg_split('/[,;\s]+/', $images, -1, PREG_SPLIT_NO_EMPTY) : (is_array($images) ? $images : []);
                $body = trim((string) ($rv['feedback'] ?? ''));
                if (! empty($rv['additional_feedback'])) {
                    $body = trim($body."\n\n".$rv['additional_feedback']);
                }
                $new += $this->store('aliexpress', $sku, [
                    'rating' => $rv['evaluation'] ?? ($rv['star'] ?? null),
                    'title' => '',
                    'body' => $body,
                    'reviewer' => $rv['buyer_blured_name'] ?? ($rv['buyer_name'] ?? ''),
                    'date' => $date,
                    'external_id' => $rv['id'] ?? ($rv['evaluation_id'] ?? ($rv['order_id'] ?? '')),
                    'verified' => true,
                    'images' => array_values(array_filter(array_map('strval', $images))),
                ], 'api', $productId) ? 1 : 0;
            }
            if (count($list) < 20) {
                break;
            }
        }

        return ['fetched' => $fetched, 'new' => $new, 'error' => null];
    }

    /**
     * Temu buyer reviews (temu.research.goods.reviews.query).
     *
     * @return array{fetched: int, new: int, error: string|null}
     */
    protected function fetchTemu(string $marketplace, string $sku, int $maxPages): array
    {
        [$model, $service] = match ($marketplace) {
            'temu2' => [Temu2Metric::class, \App\Services\Temu2ApiService::class],
            'temu3' => [Temu3Metric::class, \App\Services\Temu3ApiService::class],
            default => [TemuMetric::class, \App\Services\TemuApiService::class],
        };
        $goodsId = trim((string) $model::query()->where('sku', $sku)->whereNotNull('goods_id')->value('goods_id'));
        if ($goodsId === '') {
            return ['fetched' => 0, 'new' => 0, 'error' => 'No Temu goods id for this SKU'];
        }

        $svc = app($service);
        $fetched = 0;
        $new = 0;
        for ($page = 1; $page <= max(1, $maxPages); $page++) {
            $params = ['goodsId' => (int) $goodsId, 'countryCode' => 'US', 'pageNo' => $page, 'pageSize' => 20];
            if ((string) config('services.temu.mall_name')) {
                $params['mallName'] = (string) config('services.temu.mall_name');
            }
            $res = $svc->callOpenApi('temu.research.goods.reviews.query', $params);
            if (empty($res['success'])) {
                return ['fetched' => $fetched, 'new' => $new, 'error' => $page === 1 ? (string) ($res['message'] ?? 'Temu review call failed') : null];
            }
            $list = $this->findRows($res['result'] ?? [], ['comment', 'score']);
            if ($list === []) {
                break;
            }
            foreach ($list as $rv) {
                $fetched++;
                $ms = $rv['timeMs'] ?? ($rv['time'] ?? null);
                $date = is_numeric($ms) ? Carbon::createFromTimestamp((int) ((float) $ms > 99999999999 ? (float) $ms / 1000 : (float) $ms))->toDateTimeString() : $ms;
                $pics = $rv['pictures'] ?? ($rv['images'] ?? []);
                $new += $this->store($marketplace, $sku, [
                    'rating' => $rv['score'] ?? ($rv['rating'] ?? null),
                    'title' => '',
                    'body' => $rv['comment'] ?? '',
                    'reviewer' => $rv['nickName'] ?? ($rv['reviewerName'] ?? ''),
                    'date' => $date,
                    'external_id' => $rv['reviewId'] ?? ($rv['id'] ?? ''),
                    'verified' => true,
                    'images' => is_array($pics) ? array_values(array_filter(array_map(static fn ($i) => is_array($i) ? ($i['url'] ?? null) : (is_string($i) ? $i : null), $pics))) : [],
                ], 'api', $goodsId) ? 1 : 0;
            }
            if (count($list) < 20) {
                break;
            }
        }

        return ['fetched' => $fetched, 'new' => $new, 'error' => null];
    }

    /**
     * Find the list of review rows inside an arbitrary API response: the first array of
     * associative arrays whose items contain at least one of the given keys.
     *
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    protected function findRows(mixed $node, array $keys, int $depth = 0): array
    {
        if (! is_array($node) || $depth > 8) {
            return [];
        }
        if ($node !== [] && array_is_list($node) && is_array($node[0] ?? null)) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $node[0])) {
                    return array_values(array_filter($node, 'is_array'));
                }
            }
        }
        foreach ($node as $child) {
            $found = $this->findRows($child, $keys, $depth + 1);
            if ($found !== []) {
                return $found;
            }
        }

        return [];
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
