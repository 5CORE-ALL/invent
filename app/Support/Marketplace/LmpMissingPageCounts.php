<?php

namespace App\Support\Marketplace;

use App\Models\ShopifySku;
use App\Services\LmpSkuGroupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * LMP M. counts that follow the analytics badge:
 * child SKU, Shopify INV > 0, and no LMP price on that channel.
 * Pages with no LMP data count every in-stock child.
 */
class LmpMissingPageCounts
{
    /**
     * Channels whose grid LMP is the SKU's own row, not the Sku Link group.
     *
     * @var array<string, true>
     */
    private static array $ownLmpOnly = [
        'ebay3' => true,
        'aliexpress' => true,
    ];

    /**
     * @return array<string, int>
     */
    public static function all(): array
    {
        @ini_set('memory_limit', '1024M');

        $universe = self::inStockChildren();
        $base = count($universe);
        $out = [];
        foreach (array_keys(LmpMissingChannelCounts::analytics()) as $key) {
            $out[$key] = $base;
        }
        if ($base === 0) {
            return $out;
        }

        $groups = new LmpSkuGroupService();
        try {
            $groups->prepareForSkus(array_keys($universe));
        } catch (\Throwable $e) {
            Log::warning('LmpMissingPageCounts sku-link load failed: '.$e->getMessage());
        }

        $loaded = [];
        foreach (LmpMissingChannelCounts::analytics() as $key => $meta) {
            $comp = $meta['competitor'] ?? null;
            if (! is_array($comp) || empty($comp['table'])) {
                continue;
            }

            $sig = ($comp['table'] ?? '').'|'.($comp['sku'] ?? '').'|'.($comp['price'] ?? '').'|'.($comp['marketplace'] ?? '');
            if (! isset($loaded[$sig])) {
                $loaded[$sig] = self::skusWithLmp($comp);
            }
            $has = $loaded[$sig];
            if (! isset(self::$ownLmpOnly[$key])) {
                $has = self::expandLinked($has, $groups, $universe);
            }
            $out[$key] = self::missingIn($universe, $has, isset(self::$ownLmpOnly[$key]) && $key === 'ebay3');
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $universe
     * @param  array<string, true>  $hasLmp
     */
    public static function missingIn(array $universe, array $hasLmp, bool $openBoxBase = false): int
    {
        $n = 0;
        foreach ($universe as $sku => $_) {
            if (! self::skuHasLmp((string) $sku, $hasLmp, $openBoxBase)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @return array<string, true>
     */
    private static function inStockChildren(): array
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'sku')) {
            return [];
        }

        try {
            $query = DB::table('product_master')->whereNotNull('sku')->where('sku', '!=', '');
            if (Schema::hasColumn('product_master', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            $skus = $query->pluck('sku');
        } catch (\Throwable $e) {
            Log::warning('LmpMissingPageCounts product master failed: '.$e->getMessage());

            return [];
        }

        $children = [];
        foreach ($skus as $raw) {
            $sku = self::norm((string) $raw);
            if ($sku === '' || str_contains($sku, 'PARENT')) {
                continue;
            }
            $children[$sku] = true;
        }

        if ($children === [] || ! Schema::hasTable('shopify_skus') || ! Schema::hasColumn('shopify_skus', 'inv')) {
            return [];
        }

        try {
            $shopifySkus = DB::table('shopify_skus')
                ->where('inv', '>', 0)
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->pluck('sku');
        } catch (\Throwable $e) {
            Log::warning('LmpMissingPageCounts shopify inv failed: '.$e->getMessage());

            return [];
        }

        $inStock = [];
        foreach ($shopifySkus as $raw) {
            $sku = self::norm((string) $raw);
            if ($sku === '') {
                continue;
            }
            $inStock[$sku] = true;
            $compact = str_replace(' ', '', $sku);
            if ($compact !== '') {
                $inStock[$compact] = true;
            }
        }

        $kept = [];
        foreach ($children as $sku => $_) {
            $compact = str_replace(' ', '', $sku);
            if (isset($inStock[$sku]) || ($compact !== '' && isset($inStock[$compact]))) {
                $kept[$sku] = true;
            }
        }

        return $kept;
    }

    /**
     * @param  array<string, mixed>  $comp
     * @return array<string, true>
     */
    private static function skusWithLmp(array $comp): array
    {
        $table = (string) ($comp['table'] ?? '');
        $skuCol = (string) ($comp['sku'] ?? 'sku');
        $priceCol = (string) ($comp['price'] ?? 'price');
        if ($table === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $skuCol)) {
            return [];
        }

        try {
            $q = DB::table($table)->whereNotNull($skuCol)->where($skuCol, '!=', '');
            if (Schema::hasColumn($table, $priceCol)) {
                $q->where($priceCol, '>', 0);
            }
            if (! empty($comp['marketplace']) && Schema::hasColumn($table, 'marketplace')) {
                $q->where('marketplace', $comp['marketplace']);
            }
            if (Schema::hasColumn($table, 'ignored')) {
                $q->where(function ($qq) {
                    $qq->where('ignored', false)->orWhereNull('ignored');
                });
            }
            $rows = $q->pluck($skuCol);
        } catch (\Throwable $e) {
            Log::warning('LmpMissingPageCounts LMP load failed ('.$table.'): '.$e->getMessage());

            return [];
        }

        $has = [];
        foreach ($rows as $raw) {
            $sku = self::norm((string) $raw);
            if ($sku === '') {
                continue;
            }
            $has[$sku] = true;
            $compact = str_replace(' ', '', $sku);
            if ($compact !== '') {
                $has[$compact] = true;
            }
        }

        return $has;
    }

    /**
     * @param  array<string, true>  $has
     * @param  array<string, true>  $universe
     * @return array<string, true>
     */
    private static function expandLinked(array $has, LmpSkuGroupService $groups, array $universe): array
    {
        if ($has === []) {
            return $has;
        }

        $out = $has;
        foreach (array_keys($universe) as $sku) {
            if (self::skuHasLmp((string) $sku, $out, false)) {
                continue;
            }
            try {
                $group = $groups->groupContaining((string) $sku);
            } catch (\Throwable $e) {
                $group = [];
            }
            foreach ($group as $member) {
                if (self::skuHasLmp(self::norm((string) $member), $has, false)) {
                    $out[(string) $sku] = true;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $has
     */
    private static function skuHasLmp(string $sku, array $has, bool $openBoxBase): bool
    {
        if ($sku !== '' && isset($has[$sku])) {
            return true;
        }
        $compact = str_replace(' ', '', $sku);
        if ($compact !== '' && isset($has[$compact])) {
            return true;
        }
        if ($openBoxBase && str_contains($sku, 'OPEN BOX')) {
            $base = self::norm(str_ireplace('OPEN BOX', '', $sku));
            if ($base !== '' && (isset($has[$base]) || isset($has[str_replace(' ', '', $base)]))) {
                return true;
            }
        }

        return false;
    }

    private static function norm(string $sku): string
    {
        $sku = ShopifySku::normalizeSkuForShopifyLookup($sku);

        return strtoupper(preg_replace('/\s+/', ' ', trim($sku)) ?? '');
    }
}
