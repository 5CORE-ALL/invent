<?php

namespace App\Services;

use App\Models\AmazonBuyboxData;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonListingRaw;
use App\Models\AmazonListingStatus;
use App\Models\AmazonSkuCompetitor;
use App\Models\AmazonZeroViewDiagnostic;
use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use App\Models\ShopifySku;
use App\Services\MarketplaceManager\AmazonListingStatusHelper;
use App\Services\AmazonSpApiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmazonZeroViewsDiagnosticService
{
    public const CACHE_STATUS_KEY = 'amz_zero_views_diagnostic.status';

    public const CACHE_LOCK_KEY = 'amz_zero_views_diagnostic.lock';

    public const CACHE_SUMMARY_KEY = 'amz_zero_views_diagnostic.summary';

    public const CLASSIFICATIONS = [
        'BLOCKED',
        'INVENTORY ISSUE',
        'PRICING ISSUE',
        'BUYABILITY ISSUE',
        'LISTING ISSUE',
        'INDEXING ISSUE',
        'LOW TRAFFIC',
        'LOW RANKING',
        'NEEDS REVIEW',
        'HEALTHY',
    ];

    public const PRIORITY = [
        'BLOCKED' => 1,
        'INVENTORY ISSUE' => 2,
        'PRICING ISSUE' => 3,
        'BUYABILITY ISSUE' => 4,
        'LISTING ISSUE' => 5,
        'INDEXING ISSUE' => 6,
        'LOW TRAFFIC' => 7,
        'LOW RANKING' => 8,
        'NEEDS REVIEW' => 9,
        'HEALTHY' => 10,
    ];

    /**
     * Evaluate a single product from already-resolved facts. No Amazon API calls.
     *
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    public function evaluate(array $facts): array
    {
        $now = now()->timezone('America/Los_Angeles')->format('Y-m-d H:i');
        $listingStatus = $this->normalizeListingStatus($facts['listing_status'] ?? null);
        $suppressed = $this->isSuppressed($facts);
        $inventory = $this->numeric($facts['inventory'] ?? null);
        $price = $this->numeric($facts['price'] ?? null);
        $hasPrice = $price !== null && $price > 0;
        $buyable = $this->resolveBuyable($facts, $listingStatus, $inventory, $hasPrice);
        $title = trim((string) ($facts['title'] ?? ''));
        $hasTitle = $title !== '' && mb_strlen($title) >= 5;
        $image = trim((string) ($facts['main_image'] ?? ''));
        $hasImage = $image !== '';
        $category = trim((string) ($facts['category'] ?? ''));
        $hasCategory = $category !== '';
        $l30Views = (int) ($facts['l30_views'] ?? 0);
        $l7Views = (int) ($facts['l7_views'] ?? 0);
        $l30Sessions = (int) ($facts['l30_sessions'] ?? $l30Views);
        $l7Sessions = (int) ($facts['l7_sessions'] ?? $l7Views);
        $salesRank = isset($facts['sales_rank']) && is_numeric($facts['sales_rank'])
            ? (int) $facts['sales_rank']
            : null;
        $featuredWinner = $facts['featured_offer_winner'] ?? null;
        $asin = strtoupper(trim((string) ($facts['asin'] ?? '')));

        $checkpoints = [];
        $flags = [];

        $listingPass = in_array($listingStatus, ['ACTIVE'], true);
        $listingFail = in_array($listingStatus, ['INACTIVE', 'NOT_LISTED'], true);
        $checkpoints[] = $this->checkpoint(
            'listing_active',
            'Listing Active',
            $listingPass ? 'pass' : ($listingFail ? 'fail' : 'warn'),
            $listingStatus,
            (string) ($facts['listing_status_source'] ?? 'amazon_datsheets.listing_status'),
            $facts['listing_status_checked_at'] ?? $now,
            $listingFail
                ? 'Amazon listing is inactive or not listed, so it will not receive normal page views.'
                : ($listingPass
                    ? 'Listing status is ACTIVE.'
                    : 'Listing status could not be confirmed from currently synced Amazon data.')
        );

        $suppressionLabel = $suppressed === true ? 'Suppressed' : ($suppressed === false ? 'Not Suppressed' : 'Unknown');
        $checkpoints[] = $this->checkpoint(
            'suppression',
            'No Suppression',
            $suppressed === true ? 'fail' : ($suppressed === false ? 'pass' : 'na'),
            $suppressionLabel,
            (string) ($facts['suppression_source'] ?? 'amazon_listing_statuses / amazon_datsheets'),
            $facts['listing_status_checked_at'] ?? $now,
            $suppressed === true
                ? 'Amazon listing appears suppressed.'
                : ($suppressed === false
                    ? 'No suppression indicator was found in synced listing status.'
                    : 'Suppression is not stored as a separate Amazon field. Only ACTIVE/INACTIVE is synced.')
        );

        $invPass = $inventory !== null && $inventory > 0;
        $checkpoints[] = $this->checkpoint(
            'inventory',
            'Inventory Available',
            $invPass ? 'pass' : 'fail',
            $inventory === null ? 'Unknown' : ((string) $inventory).' units',
            'shopify_skus.inv',
            $facts['inventory_checked_at'] ?? $now,
            $invPass
                ? 'Sellable inventory is available.'
                : 'No sellable inventory is currently available.'
        );

        $checkpoints[] = $this->checkpoint(
            'price',
            'Price Available',
            $hasPrice ? 'pass' : 'fail',
            $hasPrice ? '$'.number_format($price, 2) : 'Missing/invalid',
            (string) ($facts['price_source'] ?? 'amazon_datsheets.price'),
            $facts['price_checked_at'] ?? $now,
            $hasPrice ? 'A valid selling price is present.' : 'No valid selling price.'
        );

        $buyableLabel = $buyable === true ? 'Yes' : ($buyable === false ? 'No' : 'Unknown');
        $checkpoints[] = $this->checkpoint(
            'buyable',
            'Buyable',
            $buyable === true ? 'pass' : ($buyable === false ? 'fail' : 'na'),
            $buyableLabel,
            (string) ($facts['buyable_source'] ?? 'listing_status + inventory + price'),
            $facts['listing_status_checked_at'] ?? $now,
            $buyable === true
                ? 'Offer appears purchasable from synced listing/offer data.'
                : ($buyable === false
                    ? 'Offer is not currently buyable.'
                    : 'Buyability could not be verified from currently synced data.')
        );

        $checkpoints[] = $this->checkpoint(
            'category',
            'Category',
            $hasCategory ? 'pass' : 'fail',
            $hasCategory ? $category : 'Missing',
            (string) ($facts['category_source'] ?? 'amazon_listings_raw.product_type'),
            $facts['category_checked_at'] ?? $now,
            $hasCategory
                ? 'Product type/category is present.'
                : 'Category/product classification is missing.'
        );

        $checkpoints[] = $this->checkpoint(
            'main_image',
            'Main Image',
            $hasImage ? 'pass' : 'fail',
            $hasImage ? 'Present' : 'Missing',
            (string) ($facts['image_source'] ?? 'product_master.main_image'),
            $facts['image_checked_at'] ?? $now,
            $hasImage ? 'A main product image is present.' : 'Main image missing/invalid.'
        );

        $checkpoints[] = $this->checkpoint(
            'title',
            'Title',
            $hasTitle ? 'pass' : 'fail',
            $hasTitle ? $title : 'Missing',
            (string) ($facts['title_source'] ?? 'amazon_datsheets.amazon_title'),
            $facts['title_checked_at'] ?? $now,
            $hasTitle ? 'Title is present.' : 'Title is missing or too short.'
        );

        $featuredLabel = $featuredWinner === true ? 'Yes' : ($featuredWinner === false ? 'No' : 'Not Available');
        $featuredPctNum = AmazonDatasheet::normalizeBuyBoxPercentage(
            $facts['featured_offer_percentage'] ?? $facts['buy_box_percentage'] ?? null
        );
        $featuredPct = $featuredPctNum !== null ? rtrim(rtrim(number_format($featuredPctNum, 1, '.', ''), '0'), '.').'%' : '—';
        $checkpoints[] = $this->checkpoint(
            'featured_offer',
            'Featured Offer / Buy Box',
            $featuredWinner === true ? 'pass' : ($featuredWinner === false ? 'warn' : 'na'),
            $featuredLabel.' · Featured Offer %: '.$featuredPct,
            'GET_SALES_AND_TRAFFIC_REPORT trafficByAsin.buyBoxPercentage',
            $facts['buybox_checked_at'] ?? $now,
            $featuredPctNum !== null
                ? 'L30 Featured Offer % from Amazon Sales & Traffic (buyBoxPercentage).'
                : ($featuredWinner === false
                    ? 'This offer is not the Buy Box winner. Featured Offer % has not been synced yet — run diagnostic to pull Sales & Traffic.'
                    : ($featuredWinner === true
                        ? 'This offer currently holds the Buy Box. Featured Offer % has not been synced yet — run diagnostic to pull Sales & Traffic.'
                        : 'Buy Box / Featured Offer % has not been pulled for this SKU.'))
        );
        if ($featuredWinner === false) {
            $flags[] = 'LOW FEATURED OFFER';
        }

        $classification = $this->classify(
            $listingFail,
            $suppressed === true,
            ! $invPass,
            ! $hasPrice,
            $buyable === false,
            $listingStatus === 'INCOMPLETE' || ! $hasCategory || ! $hasImage || ! $hasTitle,
            false,
            $l30Views,
            $salesRank,
            $asin === '' && $listingStatus === 'UNKNOWN'
        );

        $copy = $this->copyFor($classification, [
            'listing_status' => $listingStatus,
            'suppressed' => $suppressed === true,
            'inventory' => $inventory,
            'has_price' => $hasPrice,
            'buyable' => $buyable,
            'has_category' => $hasCategory,
            'has_image' => $hasImage,
            'has_title' => $hasTitle,
            'l30_views' => $l30Views,
            'sales_rank' => $salesRank,
        ]);

        $color = match ($classification) {
            'HEALTHY' => 'green',
            'BLOCKED', 'INVENTORY ISSUE', 'PRICING ISSUE', 'BUYABILITY ISSUE' => 'red',
            'LISTING ISSUE', 'INDEXING ISSUE', 'LOW TRAFFIC', 'LOW RANKING' => 'orange',
            default => 'gray',
        };

        $row = [
            'sku' => trim((string) ($facts['sku'] ?? '')),
            'parent' => trim((string) ($facts['parent'] ?? '')),
            'asin' => $asin !== '' ? $asin : null,
            'buyer_link' => $asin !== '' ? 'https://www.amazon.com/dp/'.$asin : null,
            'seller_link' => $asin !== ''
                ? 'https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin='.$asin
                : null,
            'product_name' => $hasTitle ? $title : (trim((string) ($facts['product_name'] ?? '')) ?: null),
            'marketplace' => $this->marketplaceLabel(),
            'account' => $this->accountLabel(),
            'inventory' => $inventory,
            'amazon_inventory' => $this->numeric($facts['amazon_inventory'] ?? null),
            'listing_status' => $listingStatus,
            'suppression' => $suppressionLabel,
            'buyable' => $buyableLabel,
            'price' => $hasPrice ? $price : null,
            'featured_offer' => $featuredLabel,
            'featured_offer_percentage' => $featuredPct,
            'l7_views' => $l7Views,
            'l30_views' => $l30Views,
            'l7_sessions' => $l7Sessions,
            'l30_sessions' => $l30Sessions,
            'search_indexed' => 'Not Verified',
            'category' => $hasCategory ? $category : null,
            'main_image' => $hasImage ? $image : null,
            'main_image_status' => $hasImage ? 'Present' : 'Missing',
            'title_status' => $hasTitle ? 'Present' : 'Missing',
            'brand' => trim((string) ($facts['brand'] ?? '')) ?: null,
            'fulfillment' => $facts['fulfillment'] ?? 'Unknown',
            'sales_rank' => $salesRank,
            'diagnostic_status' => $classification,
            'problem' => $copy['problem'],
            'recommended_action' => $copy['recommended'],
            'flags' => array_values(array_unique($flags)),
            'checkpoints' => $checkpoints,
            'color' => $color,
            'last_checked_at' => $facts['last_checked_at'] ?? null,
            'run_status' => $facts['run_status'] ?? null,
        ];

        return array_merge($row, $this->standardAmazonFields($facts, $row));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, last_page: int, total: int, summary: array<string, int>, page: int, size: int}
     */
    public function paginate(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $rawSize = $filters['size'] ?? 50;
        $wantAll = in_array(strtolower(trim((string) $rawSize)), ['all', 'true'], true)
            || (int) $rawSize === 0;

        $universe = $this->cachedTextMatched($filters);
        $summaryRows = array_values(array_filter(
            $universe,
            fn (array $row) => $this->matchesComputedFilters($row, $this->filtersForSummary($filters))
        ));
        $evaluated = array_values(array_filter(
            $summaryRows,
            fn (array $row) => $this->matchesComputedFilters($row, $filters)
        ));
        $total = count($evaluated);
        $size = $wantAll ? max(1, $total) : min(20000, max(1, (int) $rawSize));
        $slice = array_slice($evaluated, ($page - 1) * $size, $size);

        return [
            'data' => $slice,
            'last_page' => max(1, (int) ceil($total / max(1, $size))),
            'total' => $total,
            'summary' => $this->summarizeEvaluated($summaryRows),
            'page' => $page,
            'size' => $size,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function evaluateFiltered(array $filters): array
    {
        return array_values(array_filter(
            $this->evaluateTextMatched($filters),
            fn (array $row) => $this->matchesComputedFilters($row, $filters)
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function cachedTextMatched(array $filters): array
    {
        $version = (int) Cache::get(self::CACHE_SUMMARY_KEY.'.version', 1);
        $key = self::CACHE_SUMMARY_KEY.'.'.$version.'.fo1.'.md5(json_encode([
            $filters['sku'] ?? '',
            $filters['asin'] ?? '',
            $filters['brand'] ?? '',
            $filters['category'] ?? '',
            $filters['marketplace'] ?? '',
            $filters['account'] ?? '',
            $filters['date_from'] ?? '',
            $filters['date_to'] ?? '',
        ]));

        return Cache::remember($key, 90, fn () => $this->evaluateTextMatched($filters));
    }

    /**
     * Evaluate products matching text/search filters only (no card / 0-views / result filters).
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function evaluateTextMatched(array $filters): array
    {
        $products = $this->candidateProducts($filters);
        $maps = $this->loadRelatedMaps($products->pluck('sku')->filter()->unique()->values()->all());
        $rows = [];

        foreach ($products as $product) {
            $facts = $this->factsForProduct($product, $maps);
            if (! $this->matchesTextFilters($facts, $filters)) {
                continue;
            }
            $rows[] = $this->evaluate($facts);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $skus
     * @param  array<string, mixed>  $options
     * @return array{ok: int, fail: int, total: int}
     */
    public function runForSkus(array $skus, array $options = []): array
    {
        $started = microtime(true);
        $ok = 0;
        $fail = 0;
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));

        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereIn('sku', $skus)
            ->get(['id', 'parent', 'sku', 'main_image', 'Values']);

        $bySku = $products->keyBy(fn ($p) => strtoupper(trim((string) $p->sku)));
        $this->refreshFeaturedOfferPercentages();
        $maps = $this->loadRelatedMaps($skus);

        foreach ($skus as $index => $sku) {
            $skuStarted = microtime(true);
            try {
                $product = $bySku[strtoupper($sku)] ?? null;
                if (! $product) {
                    $product = (object) ['sku' => $sku, 'parent' => '', 'main_image' => null, 'Values' => []];
                }
                $facts = $this->factsForProduct($product, $maps);
                $result = $this->evaluate($facts);
                $this->persist($result, [
                    'run_status' => 'Completed',
                    'started_at' => now(),
                    'completed_at' => now(),
                    'duration_ms' => (int) round((microtime(true) - $skuStarted) * 1000),
                    'api_errors' => null,
                ]);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                Log::error('AmazonZeroViewsDiagnostic: SKU failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
                try {
                    $this->persist([
                        'sku' => $sku,
                        'asin' => null,
                        'product_name' => null,
                        'inventory' => null,
                        'listing_status' => 'UNKNOWN',
                        'suppression' => 'Unknown',
                        'buyable' => 'Unknown',
                        'price' => null,
                        'featured_offer' => 'Not Available',
                        'l7_views' => null,
                        'l30_views' => null,
                        'l7_sessions' => null,
                        'l30_sessions' => null,
                        'search_indexed' => 'Not Verified',
                        'category' => null,
                        'main_image_status' => 'Missing',
                        'title_status' => 'Missing',
                        'diagnostic_status' => 'NEEDS REVIEW',
                        'problem' => 'Diagnostic failed.',
                        'recommended_action' => 'Retry required.',
                        'checkpoints' => [],
                    ], [
                        'run_status' => 'Failed',
                        'started_at' => now(),
                        'completed_at' => now(),
                        'duration_ms' => (int) round((microtime(true) - $skuStarted) * 1000),
                        'api_errors' => $e->getMessage(),
                    ]);
                } catch (\Throwable $persistError) {
                    Log::error('AmazonZeroViewsDiagnostic: persist failed', [
                        'sku' => $sku,
                        'error' => $persistError->getMessage(),
                    ]);
                }
            }

            if (($index + 1) % 25 === 0 || $index + 1 === count($skus)) {
                self::writeStatus([
                    'running' => true,
                    'status' => 'Running',
                    'done' => $index + 1,
                    'total' => count($skus),
                    'ok' => $ok,
                    'fail' => $fail,
                    'message' => 'Diagnosed '.($index + 1).' / '.count($skus).' SKUs',
                ]);
            }
        }

        Cache::forget(self::CACHE_SUMMARY_KEY);
        Cache::forever(self::CACHE_SUMMARY_KEY.'.version', ((int) Cache::get(self::CACHE_SUMMARY_KEY.'.version', 1)) + 1);
        Log::info('AmazonZeroViewsDiagnostic: run completed', [
            'account' => $this->accountLabel(),
            'marketplace' => $this->marketplaceLabel(),
            'total' => count($skus),
            'ok' => $ok,
            'fail' => $fail,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        return ['ok' => $ok, 'fail' => $fail, 'total' => count($skus)];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    public function matchingSkus(array $filters): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($row) => trim((string) ($row['sku'] ?? '')),
            $this->evaluateFiltered($filters)
        ))));
    }

    /**
     * Pull L30 Featured Offer % from GET_SALES_AND_TRAFFIC_REPORT and store on amazon_datsheets.
     */
    public function refreshFeaturedOfferPercentages(): int
    {
        if (! Schema::hasTable('amazon_datsheets') || ! Schema::hasColumn('amazon_datsheets', 'buy_box_percentage')) {
            return 0;
        }

        self::writeStatus([
            'running' => true,
            'status' => 'Running',
            'message' => 'Pulling Featured Offer % from Amazon Sales & Traffic…',
        ]);

        try {
            $result = (new AmazonSpApiService())->fetchL30BuyBoxPercentagesByAsin();
        } catch (\Throwable $e) {
            Log::warning('AmazonZeroViewsDiagnostic: Featured Offer % pull failed', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        if (! ($result['success'] ?? false)) {
            Log::warning('AmazonZeroViewsDiagnostic: Featured Offer % pull unsuccessful', [
                'error' => $result['error'] ?? 'unknown',
            ]);

            return 0;
        }

        $updated = 0;
        foreach ($result['by_asin'] ?? [] as $asin => $pct) {
            $asin = strtoupper(trim((string) $asin));
            if ($asin === '' || ! is_numeric($pct)) {
                continue;
            }
            $updated += AmazonDatasheet::query()
                ->whereRaw('UPPER(TRIM(asin)) = ?', [$asin])
                ->update(['buy_box_percentage' => (float) $pct]);
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $meta
     */
    public function persist(array $row, array $meta = []): ?AmazonZeroViewDiagnostic
    {
        if (! Schema::hasTable('amazon_zero_view_diagnostics')) {
            return null;
        }

        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            return null;
        }

        return AmazonZeroViewDiagnostic::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'asin' => $row['asin'] ?? null,
                'marketplace' => $row['marketplace'] ?? $this->marketplaceLabel(),
                'account' => $row['account'] ?? $this->accountLabel(),
                'product_name' => $row['product_name'] ?? null,
                'inventory' => $row['inventory'] ?? null,
                'listing_status' => $row['listing_status'] ?? null,
                'suppression_status' => $row['suppression'] ?? null,
                'buyable_status' => $row['buyable'] ?? null,
                'price' => $row['price'] ?? null,
                'featured_offer_status' => $row['featured_offer'] ?? null,
                'l7_views' => $row['l7_views'] ?? null,
                'l30_views' => $row['l30_views'] ?? null,
                'l7_sessions' => $row['l7_sessions'] ?? null,
                'l30_sessions' => $row['l30_sessions'] ?? null,
                'search_index_status' => $row['search_indexed'] ?? 'Not Verified',
                'category_status' => ! empty($row['category']) ? 'Present' : 'Missing',
                'browse_node_status' => null,
                'main_image_status' => $row['main_image_status'] ?? null,
                'title_status' => $row['title_status'] ?? null,
                'diagnostic_status' => $row['diagnostic_status'] ?? null,
                'problem' => $row['problem'] ?? null,
                'recommended_action' => $row['recommended_action'] ?? null,
                'diagnostic_data' => $row,
                'run_status' => $meta['run_status'] ?? 'Completed',
                'api_errors' => $meta['api_errors'] ?? null,
                'started_at' => $meta['started_at'] ?? now(),
                'completed_at' => $meta['completed_at'] ?? now(),
                'duration_ms' => $meta['duration_ms'] ?? null,
                'last_checked_at' => now(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $skuOrAsin): array
    {
        $term = trim($skuOrAsin);
        if ($term === '') {
            return [];
        }

        $product = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($term) {
                $q->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($term)]);
            })
            ->first(['id', 'parent', 'sku', 'main_image']);

        if (! $product && preg_match('/^[A-Z0-9]{10}$/i', $term)) {
            $sheet = AmazonDatasheet::query()
                ->whereRaw('UPPER(TRIM(asin)) = ?', [strtoupper($term)])
                ->orderBy('id')
                ->first();
            if ($sheet) {
                $product = ProductMaster::query()
                    ->whereNull('deleted_at')
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim((string) $sheet->sku))])
                    ->first(['id', 'parent', 'sku', 'main_image']);
                if (! $product) {
                    $product = (object) [
                        'sku' => $sheet->sku,
                        'parent' => '',
                        'main_image' => null,
                    ];
                }
            }
        }

        if (! $product) {
            return [];
        }

        $maps = $this->loadRelatedMaps([(string) $product->sku]);
        $facts = $this->factsForProduct($product, $maps);

        return $this->evaluate($facts);
    }

    /**
     * @return array{marketplaces: list<array{value: string, label: string}>, accounts: list<array{value: string, label: string}>, brands: list<string>, categories: list<string>}
     */
    public function filterOptions(): array
    {
        $brands = [];
        $categories = [];
        if (Schema::hasTable('amazon_listings_raw')) {
            if (Schema::hasColumn('amazon_listings_raw', 'brand')) {
                $brands = AmazonListingRaw::query()
                    ->whereNotNull('brand')
                    ->where('brand', '!=', '')
                    ->distinct()
                    ->orderBy('brand')
                    ->limit(400)
                    ->pluck('brand')
                    ->map(fn ($v) => trim((string) $v))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
            if (Schema::hasColumn('amazon_listings_raw', 'product_type')) {
                $categories = AmazonListingRaw::query()
                    ->whereNotNull('product_type')
                    ->where('product_type', '!=', '')
                    ->distinct()
                    ->orderBy('product_type')
                    ->limit(400)
                    ->pluck('product_type')
                    ->map(fn ($v) => trim((string) $v))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return [
            'marketplaces' => [
                ['value' => 'amazon_us', 'label' => $this->marketplaceLabel()],
            ],
            'accounts' => [
                ['value' => $this->accountValue(), 'label' => $this->accountLabel()],
            ],
            'brands' => $brands,
            'categories' => $categories,
            'statuses' => ['ACTIVE', 'INACTIVE', 'INCOMPLETE', 'NOT_LISTED', 'UNKNOWN'],
            'diagnostic_results' => self::CLASSIFICATIONS,
        ];
    }

    public function marketplaceLabel(): string
    {
        $id = (string) config('services.amazon_sp.marketplace_id', 'ATVPDKIKX0DER');

        return $id === 'ATVPDKIKX0DER' ? 'Amazon US' : 'Amazon ('.$id.')';
    }

    public function accountLabel(): string
    {
        $seller = trim((string) config('services.amazon_sp.seller_id', ''));

        return $seller !== '' ? 'Amazon FBM · '.$seller : 'Amazon FBM';
    }

    public function accountValue(): string
    {
        $seller = trim((string) config('services.amazon_sp.seller_id', ''));

        return $seller !== '' ? $seller : 'amazon_fbm';
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function writeStatus(array $patch): void
    {
        $current = self::status();
        Cache::put(self::CACHE_STATUS_KEY, array_merge($current, $patch, [
            'updated_at' => now()->toDateTimeString(),
        ]), now()->addHours(6));
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(): array
    {
        $status = Cache::get(self::CACHE_STATUS_KEY);

        return is_array($status) ? $status : [
            'running' => false,
            'status' => 'Idle',
            'total' => 0,
            'done' => 0,
            'ok' => 0,
            'fail' => 0,
            'message' => 'No diagnostic run yet',
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function candidateProducts(array $filters): Collection
    {
        $query = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%']);

        $sku = trim((string) ($filters['sku'] ?? ''));
        if ($sku !== '') {
            $query->where('sku', 'like', '%'.$sku.'%');
        }

        return $query
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['id', 'parent', 'sku', 'main_image', 'Values']);
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, mixed>
     */
    private function loadRelatedMaps(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));

        $shopify = $skus === [] ? collect() : ShopifySku::mapByProductSkus($skus);
        $datasheetsGrouped = collect();
        $listingRaw = [];
        $listingStatus = [];
        $buybox = [];
        $dataView = [];
        $stock = [];
        $diagnostics = [];
        $lmpLowest = collect();
        $lmpDetails = collect();

        if (Schema::hasTable('amazon_datsheets')) {
            $datasheetsGrouped = AmazonDatasheet::groupedByNormalizedSku();
        }

        if (Schema::hasTable('amazon_sku_competitors')) {
            try {
                $lmpLookups = AmazonSkuCompetitor::buildGroupedLookup('amazon');
                $lmpLowest = $lmpLookups['lowest'] ?? collect();
                $lmpDetails = $lmpLookups['details'] ?? collect();
            } catch (\Throwable $e) {
                Log::warning('AmazonZeroViewsDiagnostic: LMP lookup failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($skus !== []) {
            foreach (array_chunk($skus, 400) as $chunk) {
                if (Schema::hasTable('amazon_listings_raw')) {
                    $rawCols = ['id', 'seller_sku', 'asin1', 'item_name'];
                    foreach (['thumbnail_image', 'product_type', 'item_type_keyword', 'brand', 'quantity', 'your_price'] as $col) {
                        if (Schema::hasColumn('amazon_listings_raw', $col)) {
                            $rawCols[] = $col;
                        }
                    }
                    AmazonListingRaw::query()
                        ->whereIn('seller_sku', $chunk)
                        ->select($rawCols)
                        ->orderBy('id')
                        ->get()
                        ->each(function ($row) use (&$listingRaw) {
                            $key = strtoupper(trim((string) $row->seller_sku));
                            if ($key === '' || isset($listingRaw[$key])) {
                                return;
                            }
                            $listingRaw[$key] = $row;
                        });
                }

                if (Schema::hasTable('amazon_listing_statuses')) {
                    AmazonListingStatus::query()
                        ->whereIn('sku', $chunk)
                        ->get()
                        ->each(function ($row) use (&$listingStatus) {
                            $key = strtoupper(trim((string) $row->sku));
                            if ($key === '' || isset($listingStatus[$key])) {
                                return;
                            }
                            $listingStatus[$key] = $row;
                        });
                }

                if (Schema::hasTable('amazon_buybox_data')) {
                    AmazonBuyboxData::query()
                        ->whereIn('sku', array_map(static fn ($s) => strtoupper(trim((string) $s)), $chunk))
                        ->get()
                        ->each(function ($row) use (&$buybox) {
                            $key = strtoupper(trim((string) $row->sku));
                            if ($key !== '') {
                                $buybox[$key] = $row;
                            }
                        });
                }

                if (Schema::hasTable('amazon_data_view')) {
                    AmazonDataView::query()
                        ->whereIn('sku', $chunk)
                        ->get()
                        ->each(function ($row) use (&$dataView) {
                            $key = strtoupper(trim((string) $row->sku));
                            if ($key !== '') {
                                $dataView[$key] = $row;
                            }
                        });
                }

                if (Schema::hasTable('product_stock_mappings') && Schema::hasColumn('product_stock_mappings', 'inventory_amazon')) {
                    ProductStockMapping::query()
                        ->whereIn('sku', $chunk)
                        ->get(['sku', 'inventory_amazon'])
                        ->each(function ($row) use (&$stock) {
                            $key = strtoupper(trim((string) $row->sku));
                            if ($key !== '') {
                                $stock[$key] = $row;
                            }
                        });
                }

                if (Schema::hasTable('amazon_zero_view_diagnostics')) {
                    AmazonZeroViewDiagnostic::query()
                        ->whereIn('sku', $chunk)
                        ->get(['sku', 'last_checked_at', 'run_status', 'diagnostic_status'])
                        ->each(function ($row) use (&$diagnostics) {
                            $key = strtoupper(trim((string) $row->sku));
                            if ($key !== '') {
                                $diagnostics[$key] = $row;
                            }
                        });
                }
            }
        }

        return [
            'shopify' => $shopify,
            'datasheets_grouped' => $datasheetsGrouped,
            'lmp_lowest' => $lmpLowest,
            'lmp_details' => $lmpDetails,
            'listing_raw' => $listingRaw,
            'listing_status' => $listingStatus,
            'buybox' => $buybox,
            'data_view' => $dataView,
            'stock' => $stock,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param  \App\Models\ProductMaster|object  $product
     * @param  array<string, mixed>  $maps
     * @return array<string, mixed>
     */
    private function factsForProduct(object $product, array $maps): array
    {
        $sku = trim((string) ($product->sku ?? ''));
        $key = strtoupper($sku);
        $skuClean = strtoupper(str_replace("\xC2\xA0", ' ', $sku));
        $skuLookupKey = str_replace(' ', '', $skuClean);
        $amazonSheetKey = AmazonDatasheet::normalizeSkuForLookup($sku);
        $grouped = $maps['datasheets_grouped'] ?? collect();
        $sheet = AmazonDatasheet::pickBestForProductSku(
            $sku,
            $grouped->get($amazonSheetKey)
                ?? $grouped->get($skuLookupKey)
                ?? $grouped->get($skuClean)
                ?? $grouped->get($key)
        );
        $raw = $maps['listing_raw'][$key] ?? null;
        $statusRow = $maps['listing_status'][$key] ?? null;
        $buybox = $maps['buybox'][$key] ?? null;
        $view = $maps['data_view'][$key] ?? null;
        $stock = $maps['stock'][$key] ?? null;
        $diag = $maps['diagnostics'][$key] ?? null;
        $shopify = $maps['shopify'][$sku] ?? $maps['shopify'][$key] ?? null;
        if ($shopify === null && $maps['shopify'] instanceof Collection) {
            $shopify = $maps['shopify']->get($sku);
        }

        $statusValue = $statusRow ? AmazonListingStatusHelper::valueArray($statusRow) : [];
        $viewValue = [];
        if ($view) {
            $viewValue = is_array($view->value) ? $view->value : [];
        }

        $listingStatus = $sheet?->listing_status
            ?? ($statusValue['listing_status'] ?? $statusValue['status'] ?? null);
        if (! $listingStatus && $raw) {
            $listingStatus = 'ACTIVE';
        }

        $title = trim((string) (
            $sheet?->amazon_title
            ?? $raw?->item_name
            ?? $statusValue['title']
            ?? ''
        ));
        $asin = strtoupper(trim((string) (
            $sheet?->asin
            ?? $buybox?->asin
            ?? $raw?->asin1
            ?? AmazonListingStatusHelper::resolveAsin($statusRow)
            ?? ''
        )));
        $price = $this->firstNumeric([
            $sheet?->price,
            $raw?->your_price,
            $buybox?->our_listing_price,
            $viewValue['SPRICE'] ?? $viewValue['STANDARD_PRICE'] ?? null,
            $statusValue['price'] ?? null,
        ]);
        $priceSource = 'amazon_datsheets.price';
        if (! $this->numeric($sheet?->price) && $raw?->your_price !== null) {
            $priceSource = 'amazon_listings_raw.your_price';
        }
        $image = trim((string) (
            $product->main_image
            ?? $raw?->thumbnail_image
            ?? $statusValue['image']
            ?? ''
        ));
        $category = trim((string) (
            $raw?->product_type
            ?? $raw?->item_type_keyword
            ?? $buybox?->sales_rank_category
            ?? ''
        ));
        $categorySource = $raw && trim((string) ($raw->product_type ?? '')) !== ''
            ? 'amazon_listings_raw.product_type'
            : ($buybox?->sales_rank_category ? 'amazon_buybox_data.sales_rank_category' : 'amazon_listings_raw');

        $sessionsL7 = (int) ($sheet?->sessions_l7 ?? 0);
        $sessionsL30 = (int) ($sheet?->sessions_l30 ?? 0);
        $unitsL30 = (int) ($sheet?->units_ordered_l30 ?? 0);
        $ovL30 = (int) ($shopify?->quantity ?? 0);

        $values = $this->decodeProductValues($product->Values ?? null);
        $lp = 0.0;
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp') {
                $lp = (float) $v;
                break;
            }
        }
        $ship = isset($values['ship']) ? (float) $values['ship'] : 0.0;

        $lmpKey = AmazonSkuCompetitor::normalizeSkuKey($sku);
        $lowestLmp = ($maps['lmp_lowest'] ?? collect())->get($lmpKey);
        $lmpPrice = ($lowestLmp && isset($lowestLmp->price) && is_numeric($lowestLmp->price))
            ? (float) $lowestLmp->price
            : null;
        $lmpEntries = ($maps['lmp_details'] ?? collect())->get($lmpKey);
        $lmpEntriesTotal = $lmpEntries instanceof Collection
            ? $lmpEntries->count()
            : (is_countable($lmpEntries) ? count($lmpEntries) : 0);

        $fulfillment = 'Unknown';
        if ($buybox && $buybox->is_fulfilled_by_amazon === true) {
            $fulfillment = 'FBA';
        } elseif ($buybox && $buybox->is_fulfilled_by_amazon === false) {
            $fulfillment = 'FBM';
        } elseif (! empty($viewValue['FBA'])) {
            $fulfillment = 'FBA';
        }

        $suppressed = null;
        $state = $statusRow ? AmazonListingStatusHelper::resolveListingState($statusRow) : '';
        $rawStatus = strtoupper((string) ($statusValue['listing_status'] ?? $statusValue['status'] ?? $listingStatus ?? ''));
        if (str_contains($rawStatus, 'SUPPRESS') || $state === 'inactive' && str_contains($state, 'suppress')) {
            $suppressed = true;
        } elseif (in_array($this->normalizeListingStatus($listingStatus), ['ACTIVE'], true)) {
            $suppressed = false;
        } elseif (in_array($this->normalizeListingStatus($listingStatus), ['INACTIVE', 'NOT_LISTED'], true)
            && str_contains($rawStatus, 'SUPPRESS')) {
            $suppressed = true;
        }

        return [
            'sku' => $sku,
            'parent' => trim((string) ($product->parent ?? '')),
            'asin' => $asin,
            'title' => $title,
            'product_name' => $title,
            'listing_status' => $listingStatus,
            'listing_status_source' => $sheet?->listing_status
                ? 'amazon_datsheets.listing_status'
                : 'amazon_listing_statuses.value',
            'listing_status_checked_at' => $this->formatTs($sheet?->updated_at),
            'suppressed' => $suppressed,
            'suppression_source' => 'amazon_datsheets.listing_status / amazon_listing_statuses',
            'inventory' => $shopify?->inv ?? 0,
            'amazon_inventory' => $stock?->inventory_amazon ?? $raw?->quantity,
            'inventory_checked_at' => $this->formatTs($shopify?->updated_at),
            'price' => $price,
            'price_source' => $priceSource,
            'price_checked_at' => $this->formatTs($sheet?->updated_at ?? $buybox?->fetched_at),
            'buyable_source' => 'listing_status + inventory + price + amazon_data_view.Live',
            'live' => $viewValue['Live'] ?? null,
            'listed' => $viewValue['Listed'] ?? null,
            'main_image' => $image,
            'image_source' => ! empty($product->main_image) ? 'product_master.main_image' : 'amazon_listings_raw.thumbnail_image',
            'image_checked_at' => $this->formatTs(now()),
            'category' => $category,
            'category_source' => $categorySource,
            'category_checked_at' => $this->formatTs($raw?->updated_at ?? $buybox?->fetched_at),
            'brand' => $raw?->brand,
            'l7_views' => $sessionsL7,
            'l30_views' => $sessionsL30,
            'l7_sessions' => $sessionsL7,
            'l30_sessions' => $sessionsL30,
            'ov_l30' => $ovL30,
            'a_l30' => $unitsL30,
            'lp' => $lp,
            'ship' => $ship,
            'lmp_price' => $lmpPrice,
            'lmp_entries_total' => $lmpEntriesTotal,
            'std_price' => $this->numeric($sheet?->price) ?? 0,
            'is_missing_amazon' => $sheet ? false : true,
            'featured_offer_winner' => $buybox?->is_buy_box_winner,
            'buy_box_percentage' => Schema::hasColumn('amazon_datsheets', 'buy_box_percentage')
                ? $this->numeric($sheet?->buy_box_percentage)
                : null,
            'featured_offer_percentage' => Schema::hasColumn('amazon_datsheets', 'buy_box_percentage')
                ? $this->numeric($sheet?->buy_box_percentage)
                : null,
            'buybox_checked_at' => $this->formatTs($buybox?->fetched_at),
            'fulfillment' => $fulfillment,
            'sales_rank' => $buybox?->sales_rank,
            'last_checked_at' => $diag?->last_checked_at
                ? $diag->last_checked_at->timezone('America/Los_Angeles')->format('Y-m-d H:i')
                : null,
            'run_status' => $diag?->run_status,
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $filters
     */
    private function matchesTextFilters(array $facts, array $filters): bool
    {
        $asin = trim((string) ($filters['asin'] ?? ''));
        if ($asin !== '' && stripos((string) ($facts['asin'] ?? ''), $asin) === false) {
            return false;
        }

        $brand = trim((string) ($filters['brand'] ?? ''));
        if ($brand !== '' && strcasecmp((string) ($facts['brand'] ?? ''), $brand) !== 0
            && stripos((string) ($facts['brand'] ?? ''), $brand) === false) {
            return false;
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '' && stripos((string) ($facts['category'] ?? ''), $category) === false) {
            return false;
        }

        $marketplace = trim((string) ($filters['marketplace'] ?? ''));
        if ($marketplace !== '' && ! in_array($marketplace, ['amazon_us', 'Amazon US', $this->marketplaceLabel()], true)) {
            return false;
        }

        $account = trim((string) ($filters['account'] ?? ''));
        if ($account !== '' && ! in_array($account, [$this->accountValue(), $this->accountLabel()], true)) {
            return false;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateFrom !== '' || $dateTo !== '') {
            $checked = $facts['last_checked_at'] ?? $facts['listing_status_checked_at'] ?? null;
            if (! $checked) {
                return false;
            }
            try {
                $ts = Carbon::parse($checked);
                if ($dateFrom !== '' && $ts->lt(Carbon::parse($dateFrom)->startOfDay())) {
                    return false;
                }
                if ($dateTo !== '' && $ts->gt(Carbon::parse($dateTo)->endOfDay())) {
                    return false;
                }
            } catch (\Throwable $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $filters
     */
    private function matchesComputedFilters(array $row, array $filters): bool
    {
        $zeroOnly = $this->truthy($filters['zero_only'] ?? false);
        $l30Filter = trim((string) ($filters['l30_views'] ?? ''));
        if ($l30Filter === '0' || ($zeroOnly && $l30Filter === '')) {
            if ((int) ($row['l30_views'] ?? 0) !== 0) {
                return false;
            }
        } elseif ($l30Filter === 'gt0' || $l30Filter === '>0') {
            if ((int) ($row['l30_views'] ?? 0) <= 0) {
                return false;
            }
        }

        $invFilter = strtolower(trim((string) ($filters['inv'] ?? '')));
        $inv = (float) ($row['INV'] ?? $row['inventory'] ?? 0);
        if ($invFilter === 'zero') {
            if ($inv > 0) {
                return false;
            }
        } elseif ($invFilter === 'more' || $invFilter === 'gt0' || $invFilter === '>0') {
            if ($inv <= 0) {
                return false;
            }
        }

        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && strtoupper((string) ($row['listing_status'] ?? '')) !== $status) {
            return false;
        }

        $result = strtoupper(trim((string) ($filters['diagnostic_result'] ?? $filters['diagnostic_status'] ?? '')));
        $card = strtolower(trim((string) ($filters['card'] ?? '')));
        if ($card !== '' && $card !== 'total' && $card !== 'zero_views') {
            $cardMap = [
                'blocked' => '__blocked__',
                'suppressed' => '__suppressed__',
                'out_of_stock' => 'INVENTORY ISSUE',
                'not_buyable' => 'BUYABILITY ISSUE',
                'indexing' => 'INDEXING ISSUE',
                'listing' => 'LISTING ISSUE',
                'needs_review' => 'NEEDS REVIEW',
                'healthy' => 'HEALTHY',
            ];
            if (isset($cardMap[$card])) {
                $result = $cardMap[$card];
            }
        }
        if ($result === '__suppressed__') {
            return strcasecmp((string) ($row['suppression'] ?? ''), 'Suppressed') === 0;
        }
        if ($result === '__blocked__') {
            return strtoupper((string) ($row['diagnostic_status'] ?? '')) === 'BLOCKED'
                && strcasecmp((string) ($row['suppression'] ?? ''), 'Suppressed') !== 0;
        }
        if ($result !== '' && strtoupper((string) ($row['diagnostic_status'] ?? '')) !== $result) {
            return false;
        }

        return true;
    }

    /**
     * Card totals follow INV / L30 / status filters, but ignore the selected card
     * so clicking Blocked does not zero the other cards.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filtersForSummary(array $filters): array
    {
        $out = $filters;
        $out['card'] = '';
        $out['diagnostic_result'] = '';
        $out['diagnostic_status'] = '';

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summarizeEvaluated(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'zero_views' => 0,
            'blocked' => 0,
            'suppressed' => 0,
            'out_of_stock' => 0,
            'not_buyable' => 0,
            'indexing' => 0,
            'listing' => 0,
            'needs_review' => 0,
            'healthy' => 0,
        ];

        foreach ($rows as $row) {
            if ((int) ($row['l30_views'] ?? 0) === 0) {
                $summary['zero_views']++;
            }
            $status = (string) ($row['diagnostic_status'] ?? '');
            $suppressed = strcasecmp((string) ($row['suppression'] ?? ''), 'Suppressed') === 0;
            if ($suppressed) {
                $summary['suppressed']++;
            }
            if ($status === 'BLOCKED' && ! $suppressed) {
                $summary['blocked']++;
            }
            if ($status === 'INVENTORY ISSUE') {
                $summary['out_of_stock']++;
            }
            if ($status === 'BUYABILITY ISSUE') {
                $summary['not_buyable']++;
            }
            if ($status === 'INDEXING ISSUE') {
                $summary['indexing']++;
            }
            if ($status === 'LISTING ISSUE') {
                $summary['listing']++;
            }
            if ($status === 'NEEDS REVIEW') {
                $summary['needs_review']++;
            }
            if ($status === 'HEALTHY') {
                $summary['healthy']++;
            }
        }

        return $summary;
    }

    /**
     * Same field names / formulas as Analytics Amz and Amz CVR Issues.
     *
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function standardAmazonFields(array $facts, array $row): array
    {
        $sku = trim((string) ($facts['sku'] ?? $row['sku'] ?? ''));
        $parent = trim((string) ($facts['parent'] ?? $row['parent'] ?? ''));
        $inv = $this->numeric($row['inventory'] ?? $facts['inventory'] ?? 0) ?? 0;
        $ovL30 = (int) ($facts['ov_l30'] ?? $row['L30'] ?? 0);
        $aL30 = (int) ($facts['a_l30'] ?? $row['A_L30'] ?? 0);
        $sess30 = (int) ($row['l30_views'] ?? $facts['l30_views'] ?? 0);
        $sess7 = (int) ($row['l7_views'] ?? $facts['l7_views'] ?? 0);
        $stdPrice = $this->numeric($facts['std_price'] ?? $facts['price'] ?? $row['price'] ?? null) ?? 0;
        $lp = (float) ($facts['lp'] ?? 0);
        $ship = (float) ($facts['ship'] ?? 0);
        $dil = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0.0;
        $cvr = $sess30 > 0 ? round(($aL30 / $sess30) * 100, 2) : 0.0;
        $groi = $lp > 0 ? round((($stdPrice * 0.80 - $ship - $lp) / $lp) * 100, 2) : 0.0;
        $lmp = $facts['lmp_price'] ?? null;

        return [
            'Parent' => $parent,
            '(Child) sku' => $sku,
            'image_path' => $row['main_image'] ?? $facts['main_image'] ?? null,
            'INV' => $inv,
            'INV_AMZ' => $this->numeric($facts['amazon_inventory'] ?? $row['amazon_inventory'] ?? null) ?? 0,
            'L30' => $ovL30,
            'A_L30' => $aL30,
            'Sess30' => $sess30,
            'Sess7' => $sess7,
            'CVR_L30' => $cvr,
            'E Dil%' => $dil,
            'GROI%' => $groi,
            'lmp_price' => is_numeric($lmp) ? (float) $lmp : null,
            'lmp_entries_total' => (int) ($facts['lmp_entries_total'] ?? 0),
            'lp' => $lp,
            'ship' => $ship,
            'std_price' => $stdPrice,
            'price' => $stdPrice,
            'is_missing_amazon' => (bool) ($facts['is_missing_amazon'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeProductValues(mixed $values): array
    {
        if (is_array($values)) {
            return $values;
        }
        if (is_string($values) && $values !== '') {
            $decoded = json_decode($values, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function isSuppressed(array $facts): ?bool
    {
        if (array_key_exists('suppressed', $facts) && $facts['suppressed'] !== null) {
            return (bool) $facts['suppressed'];
        }

        $raw = strtoupper((string) ($facts['listing_status'] ?? ''));
        if (str_contains($raw, 'SUPPRESS')) {
            return true;
        }

        return null;
    }

    private function resolveBuyable(array $facts, string $listingStatus, ?float $inventory, bool $hasPrice): ?bool
    {
        if (in_array($listingStatus, ['INACTIVE', 'NOT_LISTED'], true)) {
            return false;
        }
        if ($inventory !== null && $inventory <= 0) {
            return false;
        }
        if (! $hasPrice) {
            return false;
        }
        $live = $facts['live'] ?? null;
        if ($live === true || $live === 1 || $live === '1' || $live === 'true' || $live === 'Live') {
            return true;
        }
        if ($listingStatus === 'ACTIVE') {
            return true;
        }

        return null;
    }

    private function classify(
        bool $listingBlocked,
        bool $suppressed,
        bool $noInventory,
        bool $noPrice,
        bool $notBuyable,
        bool $listingIncomplete,
        bool $notIndexed,
        int $l30Views,
        ?int $salesRank,
        bool $needsReview
    ): string {
        if ($listingBlocked || $suppressed) {
            return 'BLOCKED';
        }
        if ($noInventory) {
            return 'INVENTORY ISSUE';
        }
        if ($noPrice) {
            return 'PRICING ISSUE';
        }
        if ($notBuyable) {
            return 'BUYABILITY ISSUE';
        }
        if ($listingIncomplete) {
            return 'LISTING ISSUE';
        }
        if ($notIndexed) {
            return 'INDEXING ISSUE';
        }
        if ($needsReview) {
            return 'NEEDS REVIEW';
        }
        if ($l30Views === 0) {
            if ($salesRank !== null && $salesRank > 0) {
                return 'LOW RANKING';
            }

            return 'LOW TRAFFIC';
        }

        return 'HEALTHY';
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{problem: string, recommended: string}
     */
    private function copyFor(string $classification, array $ctx): array
    {
        return match ($classification) {
            'BLOCKED' => [
                'problem' => ! empty($ctx['suppressed'])
                    ? 'Amazon listing is suppressed.'
                    : 'Amazon listing is inactive.',
                'recommended' => ! empty($ctx['suppressed'])
                    ? 'Fix all Amazon listing suppression issues.'
                    : 'Review listing status and reactivate/fix the listing.',
            ],
            'INVENTORY ISSUE' => [
                'problem' => 'No sellable inventory available.',
                'recommended' => 'Restore inventory before expecting normal traffic.',
            ],
            'PRICING ISSUE' => [
                'problem' => 'No valid selling price.',
                'recommended' => 'Fix the listing price.',
            ],
            'BUYABILITY ISSUE' => [
                'problem' => 'Offer is not currently buyable.',
                'recommended' => 'Check offer, fulfillment, inventory, pricing and listing eligibility.',
            ],
            'LISTING ISSUE' => [
                'problem' => empty($ctx['has_image'])
                    ? 'Main image missing/invalid.'
                    : (empty($ctx['has_title'])
                        ? 'Title is missing or invalid.'
                        : 'Category/product classification problem.'),
                'recommended' => empty($ctx['has_image'])
                    ? 'Add a compliant main image.'
                    : (empty($ctx['has_title'])
                        ? 'Add a complete Amazon title.'
                        : 'Review product type/category.'),
            ],
            'INDEXING ISSUE' => [
                'problem' => 'ASIN appears not to be discoverable through Amazon search.',
                'recommended' => 'Review title, product type, category, keywords, attributes and listing status.',
            ],
            'LOW TRAFFIC' => [
                'problem' => 'No technical listing block was found, but L30 views are 0.',
                'recommended' => 'Review ads, keywords and catalog completeness. Do not assume this is a ranking problem — search ranking data is not verified.',
            ],
            'LOW RANKING' => [
                'problem' => 'Listing looks technically healthy, L30 views are 0, and a sales rank is available.',
                'recommended' => 'Sales rank is not the same as search-keyword ranking. Review discoverability, ads and content. Organic keyword rank is not available via the current API.',
            ],
            'HEALTHY' => [
                'problem' => 'No blocking issue detected; product has L30 traffic.',
                'recommended' => 'No action required for zero-view diagnosis.',
            ],
            default => [
                'problem' => 'Not enough synced Amazon data to finish the diagnosis.',
                'recommended' => 'Review listing linkage (ASIN/SKU) and rerun after the next Amazon sync.',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function checkpoint(
        string $key,
        string $label,
        string $status,
        mixed $value,
        string $source,
        mixed $lastChecked,
        string $explanation
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'value' => $value,
            'source' => $source,
            'last_checked' => $lastChecked,
            'explanation' => $explanation,
        ];
    }

    private function normalizeListingStatus(mixed $status): string
    {
        $value = strtoupper(trim((string) $status));
        if ($value === '') {
            return 'UNKNOWN';
        }
        if (in_array($value, ['ACTIVE', 'LIVE', 'BUYABLE', 'BUYABLE_BY_QUANTITY', 'PUBLISHED'], true)) {
            return 'ACTIVE';
        }
        if (in_array($value, ['NOT_LISTED', 'MISSING'], true)) {
            return 'NOT_LISTED';
        }
        if (in_array($value, ['INCOMPLETE', 'DRAFT', 'PENDING'], true)) {
            return 'INCOMPLETE';
        }
        if (in_array($value, ['INACTIVE', 'SUPPRESSED', 'UNBUYABLE', 'STOPPED', 'INELIGIBLE', 'INVALID', 'OUT_OF_STOCK', 'DISCOVERABLE'], true)) {
            return 'INACTIVE';
        }

        return $value;
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function firstNumeric(array $values): ?float
    {
        foreach ($values as $value) {
            $n = $this->numeric($value);
            if ($n !== null && $n > 0) {
                return $n;
            }
        }

        return null;
    }

    private function formatTs(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->timezone('America/Los_Angeles')->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return is_string($value) ? $value : null;
        }
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
