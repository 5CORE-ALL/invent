<?php

namespace App\Support\Marketplace;

use App\Models\ChannelMaster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * LMP Missing counts for /lmp-missing-data.
 *
 * Rows are analytics pages. Count = SKUs on that page with no LMP data
 * (same meaning as the LMP M. badge). Live page visits can overwrite the
 * computed count via storeReported().
 */
class LmpMissingChannelCounts
{
    public const TOTAL_CACHE_KEY = 'lmp_missing_total_v1';

    public const ROWS_CACHE_KEY = 'lmp_missing_rows_v1';

    public const REPORTED_CACHE_PREFIX = 'lmp_missing_reported_v1:';

    /**
     * Analytics pages shown on /lmp-missing-data.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $analytics = [
        'amazon' => [
            'label' => 'Amazon',
            'url' => '/amazon-tabulator-view',
            'aliases' => ['amazon', 'amz', 'amzfbm'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'amazon_sku_competitors', 'sku' => 'sku', 'price' => 'price', 'marketplace' => 'amazon'],
        ],
        'ebay' => [
            'label' => 'eBay',
            'url' => '/ebay-tabulator-view',
            'aliases' => ['ebay', 'ebay1', 'ebayone'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'ebay_sku_competitors', 'sku' => 'sku', 'price' => 'total_price', 'marketplace' => 'ebay'],
        ],
        'ebay2' => [
            'label' => 'eBay 2',
            'url' => '/ebay2-tabulator-view',
            'aliases' => ['ebay2', 'ebaytwo'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'ebay_sku_competitors', 'sku' => 'sku', 'price' => 'total_price', 'marketplace' => 'ebay'],
        ],
        'ebay3' => [
            'label' => 'eBay 3',
            'url' => '/ebay3-tabulator-view',
            'aliases' => ['ebay3', 'ebaythree'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'ebay_sku_competitors', 'sku' => 'sku', 'price' => 'total_price', 'marketplace' => 'ebay'],
        ],
        'shopifyb2c' => [
            'label' => 'Shopify B2C',
            'url' => '/shopify-b2c-pricing',
            'aliases' => ['shopifyb2c', 'shopify'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'google_sku_competitors', 'sku' => 'sku', 'price' => 'price', 'marketplace' => 'google'],
        ],
        'shopifyb2b' => [
            'label' => 'Shopify B2B',
            'url' => '/shopify-b2b-pricing',
            'aliases' => ['shopifyb2b', 'shopifywholesale', 'business5core'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'google_sku_competitors', 'sku' => 'sku', 'price' => 'price', 'marketplace' => 'google'],
        ],
        'macys' => [
            'label' => 'Macys',
            'url' => '/macys-pricing',
            'aliases' => ['macys', 'macy'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'macy_sku_competitors', 'sku' => 'sku', 'price' => 'total_price', 'marketplace' => 'macy'],
        ],
        'depop' => [
            'label' => 'Depop',
            'url' => '/depop/pricing',
            'aliases' => ['depop'],
            'sku_table' => 'depop_pricing',
            'sku_col' => 'sku',
        ],
        'vinted' => [
            'label' => 'Vinted',
            'url' => '/vinted/pricing',
            'aliases' => ['vinted'],
            'sku_table' => 'vinted_pricing',
            'sku_col' => 'sku',
        ],
        'purchasingpower' => [
            'label' => 'Purchasing Power',
            'url' => '/purchasing-power-pricing',
            'aliases' => ['purchasingpower'],
            'sku_table' => 'purchasing_power_products',
            'sku_col' => 'sku',
        ],
        'wayfair' => [
            'label' => 'Wayfair',
            'url' => '/wayfair-pricing',
            'aliases' => ['wayfair'],
            'sku_table' => 'wayfair_data_view',
            'sku_col' => 'sku',
        ],
        'reverb' => [
            'label' => 'Reverb',
            'url' => '/reverb-pricing',
            'aliases' => ['reverb'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'reverb_sku_competitors', 'sku' => 'sku', 'price' => 'total_price', 'marketplace' => 'reverb'],
        ],
        'topdawg' => [
            'label' => 'TopDawg',
            'url' => '/topdawg-pricing',
            'aliases' => ['topdawg'],
            'sku_table' => 'topdawg_data_views',
            'sku_col' => 'sku',
        ],
        'temu' => [
            'label' => 'Temu',
            'url' => '/temu-decrease',
            'aliases' => ['temu'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'temu_lmp', 'sku' => 'sku', 'price' => 'lmp'],
        ],
        'temu2' => [
            'label' => 'Temu 2',
            'url' => '/temu2-decrease',
            'aliases' => ['temu2', 'temutwo'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'temu_lmp', 'sku' => 'sku', 'price' => 'lmp'],
        ],
        'doba' => [
            'label' => 'Doba Paid',
            'url' => '/doba-tabulator',
            'aliases' => ['doba'],
            'sku_table' => 'doba_data_view',
            'sku_col' => 'sku',
        ],
        'dobawithoutship' => [
            'label' => 'Doba Pre-Paid',
            'url' => '/doba_withoutship',
            'aliases' => ['dobawithoutship', 'dobaprepaid'],
            'sku_table' => 'doba_withoutship_data_view',
            'sku_col' => 'sku',
        ],
        'walmart' => [
            'label' => 'Walmart',
            'url' => '/walmart-sheet-upload',
            'aliases' => ['walmart'],
            'sku_table' => 'walmart_data_view',
            'sku_col' => 'sku',
        ],
        'aliexpress' => [
            'label' => 'Aliexpress',
            'url' => '/aliexpress-pricing',
            'aliases' => ['aliexpress'],
            'sku_table' => 'aliexpress_pricing_prices',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'aliexpress_lmp_data_sheet', 'sku' => 'sku', 'price' => 'lmp'],
        ],
        'faire' => [
            'label' => 'Faire',
            'url' => '/faire-pricing',
            'aliases' => ['faire'],
            'sku_table' => 'faire_data_views',
            'sku_col' => 'sku',
        ],
        'tiktok' => [
            'label' => 'TikTok 1 Shop',
            'url' => '/tiktok-pricing',
            'aliases' => ['tiktok', 'tiktokshop'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'tiktok_sku_competitors', 'sku' => 'sku', 'price' => 'price', 'marketplace' => 'tiktok'],
        ],
        'tiktok2' => [
            'label' => 'TikTok 2 Shop',
            'url' => '/tiktok-2-pricing',
            'aliases' => ['tiktok2', 'tiktokshop2'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
            'competitor' => ['table' => 'tiktok_sku_competitors', 'sku' => 'sku', 'price' => 'price', 'marketplace' => 'tiktok'],
        ],
        'mercariwship' => [
            'label' => 'Mercari w Ship',
            'url' => '/mercari-with-ship-tabulator-view',
            'aliases' => ['mercariwship'],
            'sku_table' => 'mercari_wship_price_sold_data',
            'sku_col' => 'sku',
        ],
        'fbmarketplace' => [
            'label' => 'Fb Marketplace',
            'url' => '/fb-marketplace-tabulator-view',
            'aliases' => ['fbmarketplace', 'facebookmarketplace'],
            'sku_table' => 'product_master',
            'sku_col' => 'sku',
        ],
        'pls' => [
            'label' => 'PLS',
            'url' => '/pls-pricing',
            'aliases' => ['pls'],
            'sku_table' => 'pls_data_views',
            'sku_col' => 'sku',
        ],
        'mercariwoship' => [
            'label' => 'Mercari w/o Ship',
            'url' => '/mercari-without-ship-tabulator-view',
            'aliases' => ['mercariwoship'],
            'sku_table' => 'mercari_wo_ship_data_views',
            'sku_col' => 'sku',
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function analytics(): array
    {
        return self::$analytics;
    }

    public static function normalize(string $channel): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($channel)) ?? '');
    }

    public static function resolveKey(string $channel): ?string
    {
        $key = self::normalize($channel);
        if ($key === '') {
            return null;
        }
        if (isset(self::$analytics[$key])) {
            return $key;
        }
        foreach (self::$analytics as $id => $meta) {
            foreach ($meta['aliases'] ?? [] as $alias) {
                if (self::normalize((string) $alias) === $key) {
                    return $id;
                }
            }
            if (self::normalize((string) ($meta['label'] ?? '')) === $key) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function masterRows(bool $useCache = true): array
    {
        if ($useCache) {
            try {
                $cached = Cache::get(self::ROWS_CACHE_KEY);
                if (is_array($cached)) {
                    return $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $rows = self::computeMasterRows();
        try {
            Cache::put(self::ROWS_CACHE_KEY, $rows, now()->addMinutes(10));
            Cache::put(self::TOTAL_CACHE_KEY, (int) collect($rows)->sum('lmp_missing'), now()->addMinutes(10));
        } catch (\Throwable $e) {
            // ignore
        }

        return $rows;
    }

    public static function totalMissing(bool $useCache = true): int
    {
        if ($useCache) {
            try {
                $cached = Cache::get(self::TOTAL_CACHE_KEY);
                if ($cached !== null) {
                    return (int) $cached;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return (int) collect(self::masterRows($useCache))->sum('lmp_missing');
    }

    public static function storeReported(string $channel, int $count): void
    {
        $key = self::resolveKey($channel);
        if ($key === null) {
            return;
        }
        $count = max(0, $count);
        try {
            Cache::put(self::REPORTED_CACHE_PREFIX.$key, $count, now()->addDay());
            Cache::forget(self::ROWS_CACHE_KEY);
            $total = self::totalMissing(false);
            Cache::put(self::TOTAL_CACHE_KEY, $total, now()->addMinutes(30));
        } catch (\Throwable $e) {
            Log::warning('LmpMissingChannelCounts storeReported failed: '.$e->getMessage());
        }
    }

    public static function cachedTotalOrZero(): int
    {
        try {
            $cached = Cache::get(self::TOTAL_CACHE_KEY);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function computeMasterRows(): array
    {
        $masters = self::channelMasterByAlias();
        $rows = [];

        foreach (self::$analytics as $key => $meta) {
            $master = self::matchMaster($masters, $meta['aliases'] ?? [], $meta['label'] ?? $key);
            $reported = self::reportedCount($key);
            $count = $reported !== null ? $reported : self::computeMissing($meta);

            $rows[] = [
                'id' => $master['id'] ?? $key,
                'key' => $key,
                'image' => $master['logo'] ?? null,
                'channel' => $meta['label'],
                'analytics_url' => url($meta['url']),
                'lmp_missing' => $count,
                'count_source' => $reported !== null ? 'page' : 'computed',
            ];
        }

        return $rows;
    }

    private static function reportedCount(string $key): ?int
    {
        try {
            $cached = Cache::get(self::REPORTED_CACHE_PREFIX.$key);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function computeMissing(array $meta): int
    {
        $skuTable = (string) ($meta['sku_table'] ?? '');
        $skuCol = (string) ($meta['sku_col'] ?? 'sku');
        if ($skuTable === '' || ! Schema::hasTable($skuTable) || ! Schema::hasColumn($skuTable, $skuCol)) {
            return 0;
        }

        try {
            $skuQuery = DB::table($skuTable)->whereNotNull($skuCol)->where($skuCol, '!=', '');
            if ($skuTable === 'product_master' && Schema::hasColumn($skuTable, 'deleted_at')) {
                $skuQuery->whereNull('deleted_at');
            }
            $skus = $skuQuery->pluck($skuCol);
        } catch (\Throwable $e) {
            Log::warning('LmpMissingChannelCounts sku pluck failed ('.$skuTable.'): '.$e->getMessage());

            return 0;
        }

        $normalized = [];
        foreach ($skus as $raw) {
            $n = self::normSku((string) $raw);
            if ($n === '' || str_starts_with($n, 'PARENT')) {
                continue;
            }
            $normalized[$n] = true;
        }
        $total = count($normalized);
        if ($total === 0) {
            return 0;
        }

        $comp = $meta['competitor'] ?? null;
        if (! is_array($comp) || empty($comp['table']) || ! Schema::hasTable((string) $comp['table'])) {
            return $total;
        }

        $compTable = (string) $comp['table'];
        $compSku = (string) ($comp['sku'] ?? 'sku');
        $priceCol = (string) ($comp['price'] ?? 'price');
        if (! Schema::hasColumn($compTable, $compSku)) {
            return $total;
        }

        try {
            $q = DB::table($compTable)->whereNotNull($compSku)->where($compSku, '!=', '');
            if (Schema::hasColumn($compTable, $priceCol)) {
                $q->where($priceCol, '>', 0);
            }
            if (! empty($comp['marketplace']) && Schema::hasColumn($compTable, 'marketplace')) {
                $q->where('marketplace', $comp['marketplace']);
            }
            if (Schema::hasColumn($compTable, 'ignored')) {
                $q->where(function ($qq) {
                    $qq->where('ignored', false)->orWhereNull('ignored');
                });
            }
            $lmpSkus = $q->pluck($compSku);
        } catch (\Throwable $e) {
            Log::warning('LmpMissingChannelCounts competitor pluck failed ('.$compTable.'): '.$e->getMessage());

            return $total;
        }

        $hasLmp = [];
        foreach ($lmpSkus as $raw) {
            $n = self::normSku((string) $raw);
            if ($n !== '') {
                $hasLmp[$n] = true;
            }
        }

        $missing = 0;
        foreach ($normalized as $sku => $_) {
            if (! isset($hasLmp[$sku])) {
                $missing++;
            }
        }

        return $missing;
    }

    private static function normSku(string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($sku)) ?? '');
    }

    /**
     * @return array<string, array{id:mixed,logo:?string,channel:string}>
     */
    private static function channelMasterByAlias(): array
    {
        if (! Schema::hasTable('channel_master')) {
            return [];
        }
        $hasLogo = Schema::hasColumn('channel_master', 'logo');
        $cols = ['id', 'channel'];
        if ($hasLogo) {
            $cols[] = 'logo';
        }

        $map = [];
        try {
            $rows = ChannelMaster::query()
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->get($cols);
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($rows as $row) {
            $name = (string) $row->channel;
            $map[self::normalize($name)] = [
                'id' => $row->id,
                'logo' => $hasLogo ? ($row->logo ?? null) : null,
                'channel' => $name,
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, array{id:mixed,logo:?string,channel:string}>  $masters
     * @param  list<string>  $aliases
     * @return array{id:mixed,logo:?string,channel:string}|null
     */
    private static function matchMaster(array $masters, array $aliases, string $label): ?array
    {
        foreach (array_merge($aliases, [$label]) as $alias) {
            $k = self::normalize((string) $alias);
            if ($k !== '' && isset($masters[$k])) {
                return $masters[$k];
            }
        }

        return null;
    }
}
