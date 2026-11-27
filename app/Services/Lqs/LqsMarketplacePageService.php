<?php

namespace App\Services\Lqs;

use App\Models\AlibabaMetric;
use App\Models\AliexpressMetric;
use App\Models\DobaMetric;
use App\Models\TopDawgProduct;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\FaireMetric;
use App\Models\LqsMarketplaceAction;
use App\Models\LqsMarketplaceHistory;
use App\Models\LqsMarketplaceScore;
use App\Models\NeweggMetric;
use App\Models\ProductMaster;
use App\Models\ReverbMetric;
use App\Models\SheinMetric;
use App\Models\ShopifySku;
use App\Models\Temu2Metric;
use App\Models\Temu3Metric;
use App\Models\TemuMetric;
use App\Models\WalmartListingViewsData;
use App\Models\WalmartMetrics;
use App\Support\Lqs\LqsMarketplaceCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LqsMarketplacePageService
{
    public function config(string $slug): array
    {
        $channel = LqsMarketplaceCatalog::find($slug);
        if (! $channel) {
            throw new \InvalidArgumentException('Unknown LQS marketplace: '.$slug);
        }

        return $channel;
    }

    public function tableRows(string $slug): array
    {
        $channel = $this->config($slug);
        $normalizeSku = static function ($value) {
            return strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));
        };

        $productMastersBySku = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->get()
            ->keyBy(fn ($row) => $normalizeSku($row->sku));

        // Same SKU matching every other Shopify-backed page uses: NBSP, hyphen/space/underscore and
        // compact ("LS 180-6" vs "LS180-6") variants all resolve, and L30 is topped up from shopify_raw_orders.
        $pmSkus = $productMastersBySku->map(fn ($row) => (string) $row->sku)->values()->all();
        $shopifyMatches = ShopifySku::mapByProductSkus($pmSkus);
        $shopifyBySku = [];
        foreach ($productMastersBySku as $normalizedKey => $pmRow) {
            $match = $shopifyMatches->get((string) $pmRow->sku);
            if ($match !== null) {
                $shopifyBySku[$normalizedKey] = $match;
            }
        }

        $scoresBySku = [];
        if (Schema::hasTable('lqs_marketplace_scores')) {
            foreach (LqsMarketplaceScore::query()->where('marketplace', $slug)->get() as $score) {
                $scoresBySku[$normalizeSku($score->sku)] = $score;
            }
        }

        $latestActionsBySku = collect();
        if (Schema::hasTable('lqs_marketplace_actions')) {
            $latestActionsBySku = LqsMarketplaceAction::query()
                ->where('marketplace', $slug)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy(fn ($row) => $normalizeSku($row->sku))
                ->map(fn ($group) => $group->first());
        }

        $apiMetrics = ($channel['metrics'] ?? null) === 'shopify'
            ? $this->shopifyApiMetrics($shopifyBySku)
            : $this->apiMetricsBySku($channel['metrics'] ?? null, $normalizeSku);
        if ($slug === 'tiktok' || $slug === 'tiktok2') {
            $apiMetrics = $this->overlayTikTokListingIds($slug, $apiMetrics, $normalizeSku);
        }
        $apiMetrics = $this->overlayBuyerLinks($slug, $apiMetrics, $normalizeSku);
        $apiMetrics = $this->applyKnownProductUrls($slug, $apiMetrics, $normalizeSku);
        $hasAuditColumns = Schema::hasColumn('lqs_marketplace_scores', 'audit_findings');
        $yoastBySku = $slug === 'shopify' ? $this->shopifySeoScoresBySku() : [];

        $rows = [];
        foreach ($productMastersBySku as $normalizedSku => $productMaster) {
            $shopifyRow = $shopifyBySku[$normalizedSku] ?? null;
            $score = $scoresBySku[$normalizedSku] ?? null;
            $api = $apiMetrics[$normalizedSku] ?? [];
            if (empty($api['listing_url'])) {
                $compactSku = preg_replace('/[^A-Z0-9]/', '', $normalizedSku) ?? '';
                $compactApi = ($compactSku !== '' && $compactSku !== $normalizedSku) ? ($apiMetrics[$compactSku] ?? []) : [];
                if (! empty($compactApi['listing_url'])) {
                    $api['listing_url'] = $compactApi['listing_url'];
                    if (empty($api['listing_id']) && ! empty($compactApi['listing_id'])) {
                        $api['listing_id'] = $compactApi['listing_id'];
                    }
                }
            }
            $yoast = $yoastBySku[$normalizedSku]
                ?? $yoastBySku[ShopifySku::normalizeSkuForShopifyLookup((string) $productMaster->sku)]
                ?? $yoastBySku['c:'.ShopifySku::compactSkuForLookup((string) $productMaster->sku)]
                ?? [];

            // A Product Master SKU that is not listed on Shopify (e.g. "sourcing" items, cartons, products
            // not launched yet) has no Shopify row, no Yoast score and no sheet score, so it can never carry
            // LQS data. Showing it only adds blank rows and empty parent groups to the Shopify page.
            if ($slug === 'shopify'
                && $shopifyRow === null
                && $yoast === []
                && $score === null
                && ! $latestActionsBySku->has($normalizeSku($productMaster->sku))) {
                continue;
            }

            $inv = $shopifyRow ? (int) ($shopifyRow->inv ?? 0) : 0;
            $imageSrc = $shopifyRow ? ($shopifyRow->image_src ?? null) : null;
            $l30 = $this->firstNumber($api['l30'] ?? null, $score->l30 ?? null, $shopifyRow ? ($shopifyRow->quantity ?? null) : null);
            $sessions = $this->firstNumber($api['sessions'] ?? null, $score->sessions ?? null, $shopifyRow ? ($shopifyRow->views ?? null) : null);
            $price = $this->firstNumber($api['price'] ?? null, $score->price ?? null);
            $listingId = $yoast['shopify_product_id'] ?? ($api['listing_id'] ?? ($score->listing_id ?? null));
            $lqs = $this->firstNullableNumber($score->lqs ?? null, $api['lqs'] ?? null);
            if ($lqs === null && isset($yoast['seo_score']) && is_numeric($yoast['seo_score'])) {
                $lqs = round(((float) $yoast['seo_score']) / 10, 1);
            }
            $rating = $this->firstNullableNumber($score->rating ?? null, $api['rating'] ?? null);
            $reviews = $this->firstNullableInt($score->reviews ?? null, $api['reviews'] ?? null);
            $marketplaceUrl = $this->listingUrl($channel['listing_url'] ?? null, $listingId);
            $shopifyStore = in_array($slug, ['shopify', 'pls'], true);
            $listingUrl = ! empty($api['listing_url'])
                ? $api['listing_url']
                : ($marketplaceUrl
                    ?: (($shopifyStore && $shopifyRow && ! empty($shopifyRow->product_link))
                        ? $shopifyRow->product_link
                        : ($yoast['listing_url'] ?? null)));

            $displaySku = trim((string) ($productMaster->sku ?? $normalizedSku));
            $parent = $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null;
            $latestAction = $latestActionsBySku->get($normalizeSku($displaySku));
            $hasAction = $latestAction !== null;

            $rows[] = [
                'sku' => $displaySku,
                'parent' => $parent,
                'is_parent' => false,
                'asin' => $listingId,
                'listing_id' => $listingId,
                'listing_url' => $listingUrl,
                'image' => $imageSrc,
                'inv' => $inv,
                'l30' => (int) $l30,
                'sessions' => (int) $sessions,
                'dil_percent' => $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0,
                'price' => $price ? (float) $price : null,
                'rating' => $rating,
                'reviews' => $reviews,
                'cvr' => $sessions > 0 ? round(($l30 / $sessions) * 100, 2) : null,
                'lqs' => $lqs,
                'audit_findings' => $hasAuditColumns ? ($score->audit_findings ?? null) : null,
                'audit_suggestions' => $hasAuditColumns ? ($score->audit_suggestions ?? null) : null,
                'has_action' => $hasAction,
                'latest_action_text' => $hasAction ? $latestAction->action : null,
                'latest_action_user' => $hasAction ? ($latestAction->user->name ?? 'Unknown') : null,
                'latest_action_date' => $hasAction ? $latestAction->created_at->format('d M, h:i A') : null,
                'shopify_product_id' => $yoast['shopify_product_id'] ?? null,
                'focus_keyphrase' => $yoast['focus_keyphrase'] ?? null,
                'seo_title' => $yoast['seo_title'] ?? null,
                'seo_score' => $yoast['seo_score'] ?? null,
                'seo_rating' => $yoast['seo_rating'] ?? null,
                'readability_score' => $yoast['readability_score'] ?? null,
                'readability_rating' => $yoast['readability_rating'] ?? null,
                'seo_findings' => $yoast['findings'] ?? [],
            ];
        }

        $formatted = $this->withParentRows($rows);
        $this->saveSnapshot($slug, $formatted);

        return $formatted;
    }

    public function saveAction(string $slug, string $sku, string $action): LqsMarketplaceAction
    {
        $entry = LqsMarketplaceAction::create([
            'marketplace' => $slug,
            'sku' => trim($sku),
            'action' => trim($action),
            'user_id' => auth()->id(),
        ]);
        $entry->load('user:id,name');

        return $entry;
    }

    public function actionHistory(string $slug, string $sku)
    {
        return LqsMarketplaceAction::query()
            ->where('marketplace', $slug)
            ->where('sku', trim($sku))
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'action' => $entry->action,
                'user_name' => $entry->user->name ?? 'Unknown',
                'created_at' => $entry->created_at->format('d M, h:i A'),
            ]);
    }

    public function badgeHistory(string $slug, string $metric, int $days): array
    {
        $startDate = $days > 0 ? now()->subDays($days)->toDateString() : '2000-01-01';
        $today = now()->toDateString();

        $data = LqsMarketplaceHistory::query()
            ->where('marketplace', $slug)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $today)
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->format('d M'),
                'value' => (float) ($row->{$metric} ?? 0),
            ]);

        if ($data->isEmpty()) {
            $data = collect([['date' => $today, 'value' => 0]]);
        }

        return $data->values()->all();
    }

    public function cvrHistory(string $slug, int $days): array
    {
        $startDate = $days > 0 ? now()->subDays($days)->toDateString() : '2000-01-01';
        $today = now()->toDateString();

        return LqsMarketplaceHistory::query()
            ->where('marketplace', $slug)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $today)
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                $cvr = ($row->total_sessions > 0)
                    ? round(($row->total_l30 / $row->total_sessions) * 100, 2)
                    : 0;

                return [
                    'date' => $row->date->format('d M'),
                    'value' => $cvr,
                ];
            })
            ->values()
            ->all();
    }

    public function importScores(string $slug, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $lqs = $this->nullableNumber($row['lqs'] ?? null);
            // eBay LQS is a 0-100 percentage. Sheets are often filled on a 0-10 scale (10 = perfect),
            // which would otherwise show as "10%" in red. Same rule the AI audit uses.
            if (\App\Support\Lqs\LqsScale::isPercent($slug) && $lqs !== null && $lqs > 0 && $lqs <= 10) {
                $lqs *= 10;
            }

            LqsMarketplaceScore::updateOrCreate(
                ['marketplace' => $slug, 'sku' => $sku],
                [
                    'lqs' => $lqs,
                    'rating' => $this->nullableNumber($row['rating'] ?? null),
                    'reviews' => $this->nullableInt($row['reviews'] ?? null),
                    'l30' => $this->nullableInt($row['l30'] ?? null),
                    'sessions' => $this->nullableInt($row['sessions'] ?? null),
                    'price' => $this->nullableNumber($row['price'] ?? null),
                    'listing_id' => trim((string) ($row['listing_id'] ?? '')) ?: null,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function exportRows(string $slug): array
    {
        $out = [];
        foreach ($this->tableRows($slug) as $row) {
            if (! empty($row['is_parent'])) {
                continue;
            }
            $out[] = [
                'sku' => $row['sku'],
                'listing_id' => $row['listing_id'] ?? '',
                'lqs' => $row['lqs'],
                'rating' => $row['rating'],
                'reviews' => $row['reviews'],
                'l30' => $row['l30'],
                'sessions' => $row['sessions'],
                'price' => $row['price'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{l30:?float,sessions:?float,price:?float,listing_id:?string,lqs:?float,rating:?float,reviews:?int}>
     */
    private function apiMetricsBySku(?string $source, callable $normalizeSku): array
    {
        if ($source === 'ebay' && class_exists(EbayMetric::class) && Schema::hasTable('ebay_metrics')) {
            return $this->mapEbayStyleMetrics(EbayMetric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'ebay2' && class_exists(Ebay2Metric::class) && Schema::hasTable('ebay_2_metrics')) {
            return $this->mapEbayStyleMetrics(Ebay2Metric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'ebay3' && class_exists(Ebay3Metric::class) && Schema::hasTable('ebay_3_metrics')) {
            return $this->mapEbayStyleMetrics(Ebay3Metric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'temu' && class_exists(TemuMetric::class) && Schema::hasTable((new TemuMetric)->getTable())) {
            return $this->mapTemuStyleMetrics(TemuMetric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'temu2' && class_exists(Temu2Metric::class) && Schema::hasTable('temu2_metrics')) {
            return $this->mapTemuStyleMetrics(Temu2Metric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'temu3' && class_exists(Temu3Metric::class) && Schema::hasTable('temu3_metrics')) {
            return $this->mapTemuStyleMetrics(Temu3Metric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'walmart') {
            return $this->mapWalmartMetrics($normalizeSku);
        }
        if ($source === 'reverb' && class_exists(ReverbMetric::class) && Schema::hasTable('reverb_metric')) {
            return $this->mapGenericMetrics(ReverbMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'product_id',
            ]);
        }
        if ($source === 'newegg' && class_exists(NeweggMetric::class) && Schema::hasTable('newegg_metric')) {
            return $this->mapGenericMetrics(NeweggMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'product_id',
            ]);
        }
        if ($source === 'shein' && class_exists(SheinMetric::class) && Schema::hasTable('shein_metrics')) {
            return $this->mapGenericMetrics(SheinMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'sessions' => 'views',
                'price' => 'price',
                'listing_id' => 'shein_sku_code',
                'rating' => 'rating',
                'reviews' => 'review_count',
            ]);
        }
        if ($source === 'aliexpress' && class_exists(AliexpressMetric::class) && Schema::hasTable('aliexpress_metric')) {
            return $this->mapGenericMetrics(AliexpressMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'sessions' => 'views',
                'price' => 'price',
                'listing_id' => 'product_id',
                'rating' => 'avg_rating',
                'reviews' => 'reviews',
            ]);
        }
        if ($source === 'alibaba' && class_exists(AlibabaMetric::class) && Schema::hasTable('alibaba_metrics')) {
            return $this->mapGenericMetrics(AlibabaMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'product_id',
            ]);
        }
        if ($source === 'faire' && class_exists(FaireMetric::class) && Schema::hasTable('faire_metric')) {
            return $this->mapGenericMetrics(FaireMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'product_id',
            ]);
        }
        if ($source === 'wayfair') {
            return $this->mapWayfairMetrics($normalizeSku);
        }
        if ($source === 'pls' && Schema::hasTable('pls_products')) {
            return $this->mapGenericMetrics(DB::table('pls_products')->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'p_l30',
                'sessions' => 'views',
                'price' => 'price',
            ]);
        }
        if ($source === 'b5cb2b') {
            $map = [];
            if (Schema::hasTable('business_5core_products')) {
                $map = $this->mapGenericMetrics(DB::table('business_5core_products')->orderByDesc('id')->get(), $normalizeSku, [
                    'l30' => 'b5c_l30',
                    'price' => 'price',
                ]);
            }
            if (Schema::hasTable('b5c_b2b_products')) {
                $base = rtrim((string) config('services.b5cb2b.url', 'https://business5core.com'), '/');
                $seen = [];
                foreach (DB::table('b5c_b2b_products')->select('sku', 'listing_id', 'slug')->whereNotNull('sku')->where('sku', '!=', '')->orderByDesc('id')->get() as $row) {
                    $key = $normalizeSku($row->sku);
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $existing = $map[$key] ?? [
                        'l30' => null, 'sessions' => null, 'price' => null,
                        'listing_id' => null, 'lqs' => null, 'rating' => null, 'reviews' => null,
                    ];
                    if ($row->listing_id) {
                        $existing['listing_id'] = (string) $row->listing_id;
                    }
                    $slugPath = trim((string) ($row->slug ?? ''));
                    if ($slugPath !== '') {
                        $existing['listing_url'] = $base.'/products/'.rawurlencode($slugPath);
                    }
                    $map[$key] = $existing;
                }
            }

            return $map;
        }
        if ($source === 'topdawg' && class_exists(TopDawgProduct::class) && Schema::hasTable('topdawg_products')) {
            return $this->mapGenericMetrics(TopDawgProduct::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'r_l30',
                'sessions' => 'views',
                'price' => 'price',
                'listing_id' => 'topdawg_listing_id',
            ]);
        }
        if ($source === 'doba' && class_exists(DobaMetric::class) && Schema::hasTable((new DobaMetric)->getTable())) {
            return $this->mapGenericMetrics(DobaMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'quantity_l30',
                'sessions' => 'impressions',
                'price' => 'self_pick_price',
                'listing_id' => 'item_id',
            ]);
        }

        return [];
    }

    /**
     * Shopify metrics from the shopify_skus rows already matched to each Product Master SKU.
     *
     * @param  array<string, ShopifySku>  $shopifyBySku  normalized product SKU => shopify_skus row
     * @return array<string, array<string, mixed>>
     */
    private function shopifyApiMetrics(array $shopifyBySku): array
    {
        $map = [];
        foreach ($shopifyBySku as $key => $row) {
            $map[$key] = [
                'l30' => $row->quantity,
                'sessions' => null,
                'price' => $row->price,
                'listing_id' => $row->sku,
                'lqs' => null,
                'rating' => null,
                'reviews' => null,
            ];
        }

        return $map;
    }

    /**
     * These shops have no public id template. The saved buyer link is the product page the icons open.
     *
     * @param  array<string, array<string, mixed>>  $map
     * @return array<string, array<string, mixed>>
     */
    private function overlayBuyerLinks(string $slug, array $map, callable $normalizeSku): array
    {
        $tables = [
            'shein' => ['shein_listing_statuses', 'shein_data_views'],
            'wayfair' => ['wayfair_listing_statuses', 'wayfair_data_view'],
            'bestbuy' => ['bestbuy_usa_listing_statuses', 'bestbuy_usa_data_views'],
            'macy' => ['macys_listing_statuses', 'macy_data_view'],
            'doba' => ['doba_listing_statuses', 'doba_data_view'],
            'purchasingpower' => ['purchasing_power_data_views', 'purchasing_power_listing_statuses'],
            'fbmarketplace' => ['fb_marketplace_listing_statuses', 'fb_marketplace_data_views'],
            'topdawg' => ['topdawg_data_views', 'topdawg_listing_statuses'],
        ];
        foreach ($tables[$slug] ?? [] as $table) {
            $map = $this->overlayBuyerLinkTable($slug, $table, $map, $normalizeSku);
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $map
     * @return array<string, array<string, mixed>>
     */
    private function overlayBuyerLinkTable(string $slug, string $table, array $map, callable $normalizeSku): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, 'value')) {
            return $map;
        }

        $seen = [];
        foreach (DB::table($table)->select('sku', 'value')->whereNotNull('sku')->where('sku', '!=', '')->orderByDesc('id')->get() as $row) {
            $key = $normalizeSku($row->sku);
            if ($key === '' || isset($seen[$key]) || ! empty($map[$key]['listing_url'])) {
                continue;
            }
            $state = json_decode((string) $row->value, true);
            if (is_string($state)) {
                $state = json_decode($state, true);
            }
            if (! is_array($state)) {
                continue;
            }
            $buyer = $this->httpUrl((string) ($state['buyer_link'] ?? $state['B Link'] ?? ''));
            if ($buyer === '' || $this->isOwnSite($buyer) || preg_match('/shopify\.com|myshopify\.com/i', $buyer)) {
                $buyer = $this->shopperUrl($slug, (string) ($state['seller_link'] ?? $state['S Link'] ?? ''));
            }
            if ($buyer === '') {
                continue;
            }
            $seen[$key] = true;
            $existing = $map[$key] ?? [
                'l30' => null, 'sessions' => null, 'price' => null,
                'listing_id' => null, 'lqs' => null, 'rating' => null, 'reviews' => null,
            ];
            $existing['listing_url'] = $buyer;
            if (empty($existing['listing_id'])) {
                $id = trim((string) ($state['listing_id'] ?? $state['item_id'] ?? $state['goods_id'] ?? ''));
                $existing['listing_id'] = $id !== '' ? $id : $this->listingLabelFromUrl($buyer);
            }
            $map = $this->putListingLink($map, $key, $existing);
        }

        return $map;
    }

    /**
     * A saved buyer link wins. Otherwise the listing id we already store opens that shop's product page.
     *
     * @param  array<string, array<string, mixed>>  $map
     * @return array<string, array<string, mixed>>
     */
    private function applyKnownProductUrls(string $slug, array $map, callable $normalizeSku): array
    {
        if ($slug === 'shein') {
            $map = $this->overlaySheinGoodsUrls($map, $normalizeSku);
        }
        if ($slug === 'bestbuy') {
            $map = $this->overlayBestBuyProductUrls($map, $normalizeSku);
        }
        if ($slug === 'doba') {
            $map = $this->overlayDobaProductUrls($map, $normalizeSku);
        }

        foreach ($map as $key => $row) {
            if (! empty($row['listing_url'])) {
                continue;
            }
            $id = trim((string) ($row['listing_id'] ?? ''));
            $url = $this->knownProductUrl($slug, $id);
            if ($url === '') {
                continue;
            }
            $row['listing_url'] = $url;
            $map[$key] = $row;
        }

        return $map;
    }

    /**
     * Shein storefront pages need the numeric goods id. A sku code is not that id.
     *
     * @param  array<string, array<string, mixed>>  $map
     * @return array<string, array<string, mixed>>
     */
    private function overlaySheinGoodsUrls(array $map, callable $normalizeSku): array
    {
        $rows = [];
        if (Schema::hasTable('shein_order_metrics') && Schema::hasColumn('shein_order_metrics', 'product_id')) {
            $latestOrders = DB::table('shein_order_metrics')
                ->selectRaw('MAX(id) as id')
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->groupBy('sku');
            $rows[] = DB::table('shein_order_metrics')
                ->select('sku as seller_sku', 'product_id as item_id')
                ->whereIn('id', $latestOrders)
                ->get();
        }
        if (Schema::hasTable('shein_daily_data') && Schema::hasColumn('shein_daily_data', 'item_id')) {
            $latest = DB::table('shein_daily_data')
                ->selectRaw('MAX(id) as id')
                ->whereNotNull('item_id')
                ->where('item_id', '!=', '')
                ->groupBy('seller_sku');
            $rows[] = DB::table('shein_daily_data')
                ->select('seller_sku', 'shein_sku', 'item_id')
                ->whereIn('id', $latest)
                ->get();
        }

        $seen = [];
        foreach ($rows as $set) {
            foreach ($set as $row) {
                $goodsId = trim((string) ($row->item_id ?? ''));
                if (! preg_match('/^\d{5,}$/', $goodsId)) {
                    continue;
                }
                foreach ([$row->seller_sku ?? '', $row->shein_sku ?? ''] as $sku) {
                    $key = $normalizeSku($sku);
                    if ($key === '' || isset($seen[$key]) || ! empty($map[$key]['listing_url'])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $existing = $map[$key] ?? [
                        'l30' => null, 'sessions' => null, 'price' => null,
                        'listing_id' => null, 'lqs' => null, 'rating' => null, 'reviews' => null,
                    ];
                    $existing['listing_url'] = 'https://us.shein.com/'.$goodsId.'-p-'.$goodsId.'.html';
                    if (empty($existing['listing_id'])) {
                        $existing['listing_id'] = $goodsId;
                    }
                    $map = $this->putListingLink($map, $key, $existing);
                }
            }
        }

        return $map;
    }

    /**
     * Best Buy storefront page is /product/{name}/{bsin}. The bsin is bestbuy_price_data.product_sku.
     *
     * @param  array<string, array<string, mixed>>  $map
     * @return array<string, array<string, mixed>>
     */
    private function overlayBestBuyProductUrls(array $map, callable $normalizeSku): array
    {
        if (! Schema::hasTable('bestbuy_price_data')
            || ! Schema::hasColumn('bestbuy_price_data', 'product_sku')
            || ! Schema::hasColumn('bestbuy_price_data', 'product_name')) {
            return $map;
        }

        $seen = [];
        foreach (DB::table('bestbuy_price_data')->select('sku', 'product_sku', 'product_name')->whereNotNull('sku')->where('sku', '!=', '')->orderByDesc('id')->get() as $row) {
            $key = $normalizeSku($row->sku);
            $bsin = strtoupper(trim((string) $row->product_sku));
            $slug = $this->urlSlug((string) $row->product_name);
            if ($key === '' || isset($seen[$key]) || ! empty($map[$key]['listing_url']) || ! preg_match('/^[A-Z0-9]{10}$/', $bsin) || $slug === '') {
                continue;
            }
            $seen[$key] = true;
            $existing = $map[$key] ?? [
                'l30' => null, 'sessions' => null, 'price' => null,
                'listing_id' => null, 'lqs' => null, 'rating' => null, 'reviews' => null,
            ];
            $existing['listing_url'] = 'https://www.bestbuy.com/product/'.$slug.'/'.$bsin;
            if (empty($existing['listing_id'])) {
                $existing['listing_id'] = $bsin;
            }
            $map = $this->putListingLink($map, $key, $existing);
        }

        return $map;
    }

    /**
     * Doba's product page is the seller goods screen. goodsId and catId live on doba_metrics.
     *
     * @param  array<string, array<string, mixed>>  $map
     * @return array<string, array<string, mixed>>
     */
    private function overlayDobaProductUrls(array $map, callable $normalizeSku): array
    {
        if (! Schema::hasTable('doba_metrics')
            || ! Schema::hasColumn('doba_metrics', 'goods_id')
            || ! Schema::hasColumn('doba_metrics', 'cat_id')) {
            return $map;
        }

        $seen = [];
        foreach (DB::table('doba_metrics')->select('sku', 'item_id', 'goods_id', 'cat_id')->whereNotNull('sku')->where('sku', '!=', '')->orderByDesc('id')->get() as $row) {
            $key = $normalizeSku($row->sku);
            $goodsId = trim((string) $row->goods_id);
            $catId = trim((string) $row->cat_id);
            if ($key === '' || isset($seen[$key]) || ! empty($map[$key]['listing_url']) || $goodsId === '' || $catId === '') {
                continue;
            }
            $seen[$key] = true;
            $existing = $map[$key] ?? [
                'l30' => null, 'sessions' => null, 'price' => null,
                'listing_id' => null, 'lqs' => null, 'rating' => null, 'reviews' => null,
            ];
            $existing['listing_url'] = 'https://seller.doba.com/ds/goods/save?goodsId='.rawurlencode($goodsId).'&catId='.rawurlencode($catId);
            if (empty($existing['listing_id'])) {
                $itemId = trim((string) ($row->item_id ?? ''));
                $existing['listing_id'] = $itemId !== '' ? $itemId : $goodsId;
            }
            $map = $this->putListingLink($map, $key, $existing);
        }

        return $map;
    }

    private function urlSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function isOwnSite(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === '' || str_contains($host, '5coremanagement.com') || $host === 'localhost' || str_ends_with($host, '.localhost');
    }

    private function knownProductUrl(string $slug, string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $id)) {
            return preg_match('/shopify\.com|myshopify\.com/i', $id) ? '' : $id;
        }

        return match ($slug) {
            'shein' => preg_match('/^\d{5,}$/', $id) ? 'https://us.shein.com/'.$id.'-p-'.$id.'.html' : '',
            'bestbuy' => preg_match('/^\d{6,}$/', $id) ? 'https://www.bestbuy.com/site/'.$id.'.p' : '',
            'macy' => preg_match('/^\d{5,}$/', $id) ? 'https://www.macys.com/shop/product/?ID='.$id : '',
            'fbmarketplace' => preg_match('/^\d{10,}$/', $id) ? 'https://www.facebook.com/marketplace/item/'.$id.'/' : '',
            default => '',
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $map
     * @param  array<string, mixed>  $row
     * @return array<string, array<string, mixed>>
     */
    private function putListingLink(array $map, string $key, array $row): array
    {
        $map[$key] = $row;
        $compact = preg_replace('/[^A-Z0-9]/', '', $key) ?? '';
        if ($compact !== '' && $compact !== $key && empty($map[$compact]['listing_url'])) {
            $map[$compact] = $row;
        }

        return $map;
    }

    private function shopperUrl(string $slug, string $value): string
    {
        $url = $this->httpUrl($value);
        if ($url === '' || preg_match('/shopify\.com|myshopify\.com/i', $url)) {
            return '';
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $ok = match ($slug) {
            'shein' => str_contains($host, 'shein.com'),
            'wayfair' => str_contains($host, 'wayfair.com') && ! str_contains($host, 'partners.') && ! str_contains($url, 'auth.wayfair'),
            'bestbuy' => str_contains($host, 'bestbuy.com'),
            'macy' => str_contains($host, 'macys.com'),
            'doba' => str_contains($host, 'doba.com') && ! str_contains($host, 'seller.'),
            'purchasingpower' => str_contains($host, 'purchasingpower.com'),
            'fbmarketplace' => str_contains($host, 'facebook.com') || str_contains($host, 'fb.com'),
            'topdawg' => str_contains($host, 'topdawg.com') && ! str_contains($host, 'csp.') && ! str_contains($url, '/supplier/'),
            default => false,
        };

        return $ok ? $url : '';
    }

    private function httpUrl(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, '//')) {
            $value = 'https:'.$value;
        } elseif (preg_match('#^www\.#i', $value)) {
            $value = 'https://'.$value;
        }

        return preg_match('#^https?://#i', $value) ? $value : '';
    }

    private function listingLabelFromUrl(string $url): string
    {
        $parts = parse_url($url) ?: [];
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (['ID', 'id', 'sku', 'item', 'item_id', 'product_id', 'goods_id', 'goodsId'] as $key) {
            $value = trim((string) ($query[$key] ?? ''));
            if ($value !== '') {
                return substr($value, 0, 32);
            }
        }
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $segment = $path === '' ? '' : basename($path);
        $segment = (string) preg_replace('/\.(html|htm|php)$/i', '', $segment);
        if (preg_match('/(\d{5,})/', $segment, $match)) {
            return $match[1];
        }

        return $segment !== '' ? substr($segment, 0, 24) : 'Link';
    }

    /**
     * TikTok LQS scores stay on the sheet. The shop product id lives on the synced product row,
     * and that id is what the desktop and phone icons open.
     *
     * @param  array<string, array<string, mixed>>  $map
     * @return array<string, array<string, mixed>>
     */
    private function overlayTikTokListingIds(string $slug, array $map, callable $normalizeSku): array
    {
        $table = $slug === 'tiktok2' ? 'tiktok_products_two' : 'tiktok_products';
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, 'product_id')) {
            return $map;
        }

        $seen = [];
        foreach (DB::table($table)->select('sku', 'product_id')->whereNotNull('sku')->where('sku', '!=', '')->orderByDesc('id')->get() as $row) {
            $key = $normalizeSku($row->sku);
            $productId = trim((string) ($row->product_id ?? ''));
            if ($key === '' || ! preg_match('/^\d{8,}$/', $productId) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $existing = $map[$key] ?? [
                'l30' => null, 'sessions' => null, 'price' => null,
                'listing_id' => null, 'lqs' => null, 'rating' => null, 'reviews' => null,
            ];
            $existing['listing_id'] = $productId;
            $map[$key] = $existing;
        }

        return $map;
    }

    private function mapEbayStyleMetrics($rows, callable $normalizeSku): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = $normalizeSku($row->sku);
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'l30' => $row->ebay_l30 ?? null,
                'sessions' => $row->views ?? null,
                'price' => $row->ebay_price ?? null,
                'listing_id' => $row->item_id ?? null,
                'lqs' => null,
                'rating' => null,
                'reviews' => null,
            ];
        }

        return $map;
    }

    private function mapTemuStyleMetrics($rows, callable $normalizeSku): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = $normalizeSku($row->sku);
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'l30' => $row->quantity_purchased_l30 ?? null,
                'sessions' => $row->product_impressions_l30 ?? $row->product_clicks_l30 ?? null,
                'price' => $row->base_price ?? $row->temu_sheet_price ?? null,
                'listing_id' => $row->goods_id ?? $row->sku_id ?? null,
                'lqs' => null,
                'rating' => null,
                'reviews' => null,
            ];
        }

        return $map;
    }

    private function mapGenericMetrics($rows, callable $normalizeSku, array $fields): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = $normalizeSku($row->sku ?? '');
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'l30' => isset($fields['l30']) ? ($row->{$fields['l30']} ?? null) : null,
                'sessions' => isset($fields['sessions']) ? ($row->{$fields['sessions']} ?? null) : null,
                'price' => isset($fields['price']) ? ($row->{$fields['price']} ?? null) : null,
                'listing_id' => isset($fields['listing_id']) ? ($row->{$fields['listing_id']} ?? null) : null,
                'lqs' => isset($fields['lqs']) ? ($row->{$fields['lqs']} ?? null) : null,
                'rating' => isset($fields['rating']) ? ($row->{$fields['rating']} ?? null) : null,
                'reviews' => isset($fields['reviews']) ? ($row->{$fields['reviews']} ?? null) : null,
            ];
        }

        return $map;
    }

    /**
     * Wayfair: L30 units from wayfair_daily_data (period l30), price from the uploaded Wayfair prices, and the Wayfair
     * SKU / shopper page from Partner Home listing statuses. Wayfair has no sessions feed, so sessions stay null.
     */
    private function mapWayfairMetrics(callable $normalizeSku): array
    {
        $blank = [
            'l30' => 0, 'sessions' => null, 'price' => null, 'listing_id' => null, 'listing_url' => null,
            'lqs' => null, 'rating' => null, 'reviews' => null,
        ];
        $map = [];

        if (Schema::hasTable('wayfair_daily_data')) {
            foreach (DB::table('wayfair_daily_data')
                ->selectRaw('sku, SUM(COALESCE(quantity, 0)) as units')
                ->where('period', 'l30')->whereNotNull('sku')->where('sku', '!=', '')
                ->groupBy('sku')->get() as $row) {
                $key = $normalizeSku($row->sku);
                if ($key !== '') {
                    $map[$key] = ['l30' => (int) $row->units] + ($map[$key] ?? $blank);
                }
            }
        }

        if (Schema::hasTable('wayfair_pricing_prices')) {
            foreach (DB::table('wayfair_pricing_prices')->select('sku', 'price')->whereNotNull('sku')->where('sku', '!=', '')->get() as $row) {
                $key = $normalizeSku($row->sku);
                if ($key !== '') {
                    $map[$key] = ['price' => $row->price > 0 ? (float) $row->price : null] + ($map[$key] ?? $blank);
                }
            }
        }

        if (Schema::hasTable('wayfair_listing_statuses')) {
            foreach (DB::table('wayfair_listing_statuses')->select('sku', 'value')->whereNotNull('sku')->where('sku', '!=', '')->orderBy('id')->get() as $row) {
                $key = $normalizeSku($row->sku);
                $state = json_decode((string) $row->value, true) ?: [];
                if ($key === '') {
                    continue;
                }
                $id = trim((string) ($state['listing_id'] ?? $state['item_id'] ?? ''));
                if ($id === '' && preg_match('#product-merchandising/([A-Za-z0-9]+)#', (string) ($state['seller_link'] ?? ''), $m)) {
                    $id = $m[1];
                }
                $buyer = trim((string) ($state['buyer_link'] ?? ''));
                $map[$key] = [
                    'listing_id' => $id !== '' ? $id : null,
                    'listing_url' => $buyer !== '' ? $buyer : null,
                ] + ($map[$key] ?? $blank);
            }
        }

        return $map;
    }

    private function mapWalmartMetrics(callable $normalizeSku): array
    {
        $map = [];
        if (class_exists(WalmartMetrics::class) && Schema::hasTable('walmart_metrics')) {
            $map = $this->mapGenericMetrics(WalmartMetrics::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'sku',
            ]);
        }

        if (class_exists(WalmartListingViewsData::class) && Schema::hasTable('walmart_listing_views_data')) {
            foreach (WalmartListingViewsData::query()->orderByDesc('id')->get() as $row) {
                $key = $normalizeSku($row->sku);
                if ($key === '') {
                    continue;
                }
                $existing = $map[$key] ?? [
                    'l30' => null,
                    'sessions' => null,
                    'price' => null,
                    'listing_id' => null,
                    'lqs' => null,
                    'rating' => null,
                    'reviews' => null,
                ];
                $existing['sessions'] = $existing['sessions'] ?? $row->page_views;
                $existing['price'] = $existing['price'] ?? $row->walmart_price;
                $existing['listing_id'] = $existing['listing_id'] ?? $row->item_id;
                $existing['lqs'] = $existing['lqs'] ?? $this->nullableNumber($row->listing_quality ?? null);
                $existing['rating'] = $existing['rating'] ?? $this->nullableNumber($row->ratings ?? null);
                $map[$key] = $existing;
            }
        }

        return $map;
    }

    private function withParentRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['parent'] ?: $row['sku']][] = $row;
        }

        $formatted = [];
        foreach ($groups as $parentKey => $childRows) {
            $totalInv = array_sum(array_column($childRows, 'inv'));
            $totalL30 = array_sum(array_column($childRows, 'l30'));
            $totalSess = array_sum(array_column($childRows, 'sessions'));
            $formatted[] = [
                'sku' => $parentKey,
                'parent' => '',
                'asin' => null,
                'listing_id' => null,
                'listing_url' => null,
                'image' => '',
                'inv' => $totalInv,
                'l30' => $totalL30,
                'sessions' => $totalSess,
                'dil_percent' => $totalInv > 0 ? round(($totalL30 / $totalInv) * 100, 2) : 0,
                'price' => null,
                'rating' => null,
                'reviews' => null,
                'cvr' => $totalSess > 0 ? round(($totalL30 / $totalSess) * 100, 2) : null,
                'lqs' => null,
                'audit_findings' => null,
                'audit_suggestions' => null,
                'has_action' => false,
                'latest_action_text' => null,
                'latest_action_user' => null,
                'latest_action_date' => null,
                'is_parent' => true,
                'shopify_product_id' => null,
                'focus_keyphrase' => null,
                'seo_title' => null,
                'seo_score' => null,
                'seo_rating' => null,
                'readability_score' => null,
                'readability_rating' => null,
                'seo_findings' => [],
            ];
            foreach ($childRows as $child) {
                $formatted[] = $child;
            }
        }

        return $formatted;
    }

    private function saveSnapshot(string $slug, array $rows): void
    {
        if (! Schema::hasTable('lqs_marketplace_history')) {
            return;
        }

        $childRows = collect($rows)->filter(fn ($row) => ! ($row['is_parent'] ?? false));
        $totalInv = 0;
        $totalL30 = 0;
        $totalSess = 0;
        $dilSum = 0;
        $dilCount = 0;
        $lqsSum = 0;
        $lqsCount = 0;
        $ratingSum = 0;
        $ratingCount = 0;
        $lqsBelow9 = 0;

        foreach ($childRows as $row) {
            $inv = (float) ($row['inv'] ?? 0);
            $l30 = (float) ($row['l30'] ?? 0);
            $sess = (float) ($row['sessions'] ?? 0);
            $lqs = (float) ($row['lqs'] ?? 0);
            $rating = (float) ($row['rating'] ?? 0);
            $totalInv += $inv;
            $totalL30 += $l30;
            $totalSess += $sess;
            if ($inv > 0) {
                $dilSum += ($l30 / $inv) * 100;
                $dilCount++;
            }
            if ($lqs > 0) {
                $lqsSum += $lqs;
                $lqsCount++;
            }
            if ($rating > 0) {
                $ratingSum += $rating;
                $ratingCount++;
            }
            if ($lqs > 0 && $lqs < (\App\Support\Lqs\LqsScale::isPercent($slug) ? 90 : 9)) {
                $lqsBelow9++;
            }
        }

        LqsMarketplaceHistory::updateOrCreate(
            ['marketplace' => $slug, 'date' => now()->toDateString()],
            [
                'total_inv' => round($totalInv, 2),
                'total_l30' => round($totalL30, 2),
                'total_sessions' => round($totalSess, 2),
                'avg_dil' => $dilCount > 0 ? round($dilSum / $dilCount, 2) : 0,
                'avg_lqs' => $lqsCount > 0 ? round($lqsSum / $lqsCount, 2) : 0,
                'avg_rating' => $ratingCount > 0 ? round($ratingSum / $ratingCount, 2) : 0,
                'lqs_below_9_count' => $lqsBelow9,
            ]
        );
    }

    private function firstNumber(...$values)
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return $value;
            }
        }

        return 0;
    }

    private function nullableNumber($value)
    {
        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (float) $value;
    }

    private function nullableInt($value)
    {
        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (int) $value;
    }

    private function firstNullableNumber(...$values)
    {
        foreach ($values as $value) {
            $number = $this->nullableNumber($value);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function firstNullableInt(...$values)
    {
        foreach ($values as $value) {
            $number = $this->nullableInt($value);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function shopifySeoScoresBySku(): array
    {
        try {
            return app(LqsShopifySeoService::class)->scoresBySku();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('LqsMarketplacePageService: Shopify SEO scores failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function listingUrl(?string $template, $listingId): ?string
    {
        if (! $template || $listingId === null || $listingId === '') {
            return null;
        }

        return str_replace('{id}', rawurlencode((string) $listingId), $template);
    }
}
