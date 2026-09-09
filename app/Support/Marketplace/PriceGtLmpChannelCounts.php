<?php

namespace App\Support\Marketplace;

use App\Http\Controllers\MarketPlace\AliexpressController;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use App\Models\AmazonSkuCompetitor;
use App\Models\ChannelMaster;
use App\Models\ShopifySku;
use App\Models\TiktokSkuCompetitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Price > LMP (red triangle) counts for /price-gt-lmp.
 *
 * Always computed live from price + inventory + competitor LMP tables.
 * Same rule as the analytics badge: INV > 0, not a PARENT row, price > 0,
 * landed LMP > 0, and price > LMP. Sku Link groups share the lowest LMP.
 */
class PriceGtLmpChannelCounts
{
    public const TOTAL_CACHE_KEY = 'price_gt_lmp_total_v2';

    public const ROWS_CACHE_KEY = 'price_gt_lmp_rows_v2';

    public static function resolveKey(string $channel): ?string
    {
        return LmpMissingChannelCounts::resolveKey($channel);
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
            Cache::put(self::ROWS_CACHE_KEY, $rows, now()->addMinutes(2));
            Cache::put(self::TOTAL_CACHE_KEY, (int) collect($rows)->sum('price_gt_lmp'), now()->addMinutes(2));
        } catch (\Throwable $e) {
            // ignore
        }

        return $rows;
    }

    public static function totalCount(bool $useCache = true): int
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

        return (int) collect(self::masterRows($useCache))->sum('price_gt_lmp');
    }

    /**
     * Kept so analytics pages can still POST. Live rows ignore these reports.
     */
    public static function storeReported(string $channel, int $count): void
    {
        // no-op — counts are always computed from the database
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

    public static function temuListingPrice(float $base): float
    {
        if (! ($base > 0)) {
            return 0.0;
        }

        return $base <= 26.99 ? round($base + 2.99, 2) : round($base, 2);
    }

    public static function temuRecoveryLmp(float $price): float
    {
        if (! ($price > 0)) {
            return 0.0;
        }
        if ($price <= 27) {
            return round(($price * 0.85) + 2.99, 2);
        }

        return round($price * 0.85, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function computeMasterRows(): array
    {
        $masters = self::channelMasterByAlias();
        $counts = PriceGtLmpPageCounts::all();
        $rows = [];

        foreach (LmpMissingChannelCounts::analytics() as $key => $meta) {
            $master = self::matchMaster($masters, $meta['aliases'] ?? [], $meta['label'] ?? $key);

            $rows[] = [
                'id' => $master['id'] ?? $key,
                'key' => $key,
                'image' => $master['logo'] ?? null,
                'channel' => $meta['label'],
                'analytics_url' => url($meta['url']),
                'price_gt_lmp' => (int) ($counts[$key] ?? 0),
                'count_source' => 'live',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, float>  $inv
     * @param  array<string, string>  $linkRoots
     */
    private static function computePriceGtLmp(string $key, array $meta, array $inv, array $linkRoots): int
    {
        if ($key === 'amazon') {
            return self::computeAmazonPriceGtLmp();
        }
        if ($key === 'aliexpress') {
            return self::computeAliexpressPriceGtLmp();
        }

        $prices = self::loadPriceMap($key);
        if ($prices === []) {
            return 0;
        }
        $lmps = self::expandLmp(self::loadLmpMap($key, $meta), $linkRoots);
        if ($lmps === []) {
            return 0;
        }

        $n = 0;
        foreach ($prices as $sku => $price) {
            if (! ($price > 0) || str_starts_with($sku, 'PARENT')) {
                continue;
            }
            if (! (($inv[$sku] ?? 0) > 0)) {
                continue;
            }
            $lmp = $lmps[$sku] ?? 0.0;
            if ($lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Same Product Master + datasheet + landed LMP rule as /amazon-tabulator-view.
     */
    private static function computeAmazonPriceGtLmp(): int
    {
        try {
            return app(OverallAmazonController::class)->countPriceGtLmp();
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpChannelCounts Amazon count failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Same rows and red-triangle rule as /aliexpress-pricing (default SKUs + INV > 0).
     */
    private static function computeAliexpressPriceGtLmp(): int
    {
        try {
            $rows = app(AliexpressController::class)->buildPricingRows(false);
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpChannelCounts AE rows failed: '.$e->getMessage());

            return 0;
        }

        $n = 0;
        foreach ($rows as $row) {
            if (is_array($row) && self::rowHasRedTriangle($row, 'price')) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Same rule as public/js/price-gt-lmp-badge.js hasRedTriangle().
     *
     * @param  array<string, mixed>  $row
     */
    public static function rowHasRedTriangle(array $row, string $priceField = 'price'): bool
    {
        if (self::isParentRow($row)) {
            return false;
        }
        if (! (self::rowInv($row) > 0)) {
            return false;
        }
        $price = self::rowFirstPositive($row, array_merge([$priceField], [
            'eBay Price', 'Price', 'price', 'MC Price', 'api_price', 'doba Price', 'self_pick_price',
        ]));
        $lmp = self::rowLmp($row);

        return $price > 0 && $lmp > 0 && $price > $lmp;
    }

    /**
     * @return array<string, float>
     */
    private static function loadPriceMap(string $key): array
    {
        $src = self::priceSource($key);
        if ($src === null) {
            return [];
        }
        $table = $src['table'];
        $skuCol = $src['sku'];
        $priceCol = $src['price'];
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $skuCol) || ! Schema::hasColumn($table, $priceCol)) {
            return [];
        }

        try {
            $rows = DB::table($table)
                ->whereNotNull($skuCol)
                ->where($skuCol, '!=', '')
                ->where($priceCol, '>', 0)
                ->get([$skuCol, $priceCol]);
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpChannelCounts price load failed ('.$table.'): '.$e->getMessage());

            return [];
        }

        $map = [];
        $adjust = $src['adjust'] ?? null;
        foreach ($rows as $row) {
            $sku = self::normSku((string) $row->{$skuCol});
            if ($sku === '' || str_starts_with($sku, 'PARENT')) {
                continue;
            }
            $price = (float) $row->{$priceCol};
            if ($adjust === 'temu') {
                $price = self::temuListingPrice($price);
            }
            if ($price > 0 && (! isset($map[$sku]) || $price > $map[$sku])) {
                $map[$sku] = $price;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, float>
     */
    private static function loadLmpMap(string $key, array $meta): array
    {
        if ($key === 'amazon') {
            return self::loadAmazonLmpMap();
        }
        if (in_array($key, ['temu', 'temu2', 'temu3'], true)) {
            return self::loadSheetLmpMap('temu_lmp', true);
        }
        if (in_array($key, ['tiktok', 'tiktok2'], true)) {
            return self::loadTiktokLmpMap();
        }

        $comp = $meta['competitor'] ?? null;
        if (! is_array($comp) || empty($comp['table'])) {
            return [];
        }

        return self::loadCompetitorLmpMap($comp);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function isParentRow(array $row): bool
    {
        if (! empty($row['is_parent_summary']) || ! empty($row['is_parent']) || ! empty($row['is_parent_row'])) {
            return true;
        }
        if (! empty($row['_children']) && is_array($row['_children'])) {
            return true;
        }
        $sku = strtoupper(trim((string) ($row['(Child) sku'] ?? ($row['sku'] ?? ($row['Sku'] ?? ($row['SKU'] ?? ''))))));

        return str_contains($sku, 'PARENT');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowInv(array $row): float
    {
        foreach (['inventory', 'INV', 'inv', 'Inv', 'QTY AVAIL', 'qty_avail'] as $field) {
            if (! array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                continue;
            }
            $n = (float) $row[$field];
            if (is_finite($n)) {
                return $n;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $fields
     */
    private static function rowFirstPositive(array $row, array $fields): float
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                continue;
            }
            $n = (float) $row[$field];
            if (is_finite($n) && $n > 0) {
                return $n;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowLmp(array $row): float
    {
        $entries = $row['lmp_entries'] ?? null;
        if (is_array($entries) && $entries !== []) {
            $lowest = 0.0;
            foreach ($entries as $entry) {
                if (! is_array($entry) || self::entryIgnored($entry['ignored'] ?? null)) {
                    continue;
                }
                $p = $entry['landed_price'] ?? ($entry['total_price'] ?? ($entry['price'] ?? ($entry['lmp'] ?? 0)));
                $n = is_numeric($p) ? (float) $p : 0.0;
                if ($n > 0 && ($lowest <= 0 || $n < $lowest)) {
                    $lowest = $n;
                }
            }

            return $lowest;
        }

        return self::rowFirstPositive($row, ['lmp_price', 'lmp', 'LMP', 'LMP 1', 'lmp_1']);
    }

    /**
     * @return array<string, float>
     */
    private static function loadAmazonLmpMap(): array
    {
        if (! Schema::hasTable('amazon_sku_competitors')) {
            return [];
        }
        try {
            $lowest = AmazonSkuCompetitor::buildGroupedLookup('amazon')['lowest'] ?? collect();
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpChannelCounts amazon LMP failed: '.$e->getMessage());

            return [];
        }

        $map = [];
        foreach ($lowest as $row) {
            if (! $row) {
                continue;
            }
            $sku = self::normSku((string) ($row->sku ?? ''));
            $lmp = AmazonSkuCompetitor::landedPrice($row);
            if ($sku !== '' && $lmp !== null && $lmp > 0) {
                if (! isset($map[$sku]) || $lmp < $map[$sku]) {
                    $map[$sku] = $lmp;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, float>
     */
    private static function loadTiktokLmpMap(): array
    {
        if (! Schema::hasTable('tiktok_sku_competitors')) {
            return [];
        }
        try {
            $lowest = TiktokSkuCompetitor::buildGroupedLookup('tiktok')['lowest'] ?? collect();
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpChannelCounts tiktok LMP failed: '.$e->getMessage());

            return [];
        }

        $map = [];
        foreach ($lowest as $row) {
            if (! $row) {
                continue;
            }
            $sku = self::normSku((string) ($row->sku ?? ''));
            $lmp = TiktokSkuCompetitor::landedPrice($row);
            if ($sku !== '' && $lmp > 0) {
                if (! isset($map[$sku]) || $lmp < $map[$sku]) {
                    $map[$sku] = $lmp;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, float>
     */
    private static function loadSheetLmpMap(string $table, bool $temuRecovery): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
            return [];
        }
        $cols = ['sku'];
        foreach (['lmp', 'lmp_entries'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                $cols[] = $col;
            }
        }

        try {
            $rows = DB::table($table)->whereNotNull('sku')->where('sku', '!=', '')->get($cols);
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpChannelCounts sheet LMP failed ('.$table.'): '.$e->getMessage());

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $sku = self::normSku((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $lmp = self::sheetRowLmp($row, $temuRecovery);
            if ($lmp > 0 && (! isset($map[$sku]) || $lmp < $map[$sku])) {
                $map[$sku] = $lmp;
            }
        }

        return $map;
    }

    private static function sheetRowLmp(object $row, bool $temuRecovery): float
    {
        $lowest = 0.0;
        $entries = $row->lmp_entries ?? null;
        if (is_string($entries)) {
            $decoded = json_decode($entries, true);
            $entries = is_array($decoded) ? $decoded : null;
        }
        if (is_array($entries) && $entries !== []) {
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                if (self::entryIgnored($entry['ignored'] ?? null)) {
                    continue;
                }
                $eff = self::sheetEntryLanded($entry, $temuRecovery);
                if ($eff > 0 && ($lowest <= 0 || $eff < $lowest)) {
                    $lowest = $eff;
                }
            }
        }
        if ($lowest <= 0) {
            $fallback = (float) ($row->lmp ?? 0);
            $lowest = $fallback > 0 ? $fallback : 0.0;
        }
        if ($lowest > 0 && $temuRecovery) {
            return self::temuRecoveryLmp($lowest);
        }

        return $lowest;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private static function sheetEntryLanded(array $entry, bool $temuDefaultShip): float
    {
        $price = $entry['price'] ?? ($entry['landed_price'] ?? ($entry['total_price'] ?? ($entry['lmp'] ?? 0)));
        $p = is_numeric($price) ? (float) $price : 0.0;
        if (! ($p > 0)) {
            return 0.0;
        }
        $delivery = $entry['delivery'] ?? ($entry['ship'] ?? ($entry['shipping'] ?? 0));
        $d = (is_numeric($delivery) && (float) $delivery > 0) ? (float) $delivery : 0.0;
        if ($temuDefaultShip && $d <= 0 && $p < 27) {
            $d = 2.99;
        }

        return round($p + $d, 2);
    }

    /**
     * @param  array<string, mixed>  $comp
     * @return array<string, float>
     */
    private static function loadCompetitorLmpMap(array $comp): array
    {
        $table = (string) ($comp['table'] ?? '');
        $skuCol = (string) ($comp['sku'] ?? 'sku');
        $priceCol = (string) ($comp['price'] ?? 'price');
        if ($table === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $skuCol)) {
            return [];
        }

        $landedExpr = self::competitorLandedSql($table, $priceCol);
        if ($landedExpr === null) {
            return [];
        }

        try {
            $q = DB::table($table)->whereNotNull($skuCol)->where($skuCol, '!=', '');
            if (! empty($comp['marketplace']) && Schema::hasColumn($table, 'marketplace')) {
                $q->where('marketplace', $comp['marketplace']);
            }
            if (Schema::hasColumn($table, 'ignored')) {
                $q->where(function ($qq) {
                    $qq->where('ignored', false)->orWhereNull('ignored');
                });
            }
            $rows = $q->selectRaw($skuCol.' as sku, '.$landedExpr.' as lmp')->get();
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpChannelCounts competitor LMP failed ('.$table.'): '.$e->getMessage());

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $sku = self::normSku((string) $row->sku);
            $lmp = (float) ($row->lmp ?? 0);
            if ($sku === '' || ! ($lmp > 0)) {
                continue;
            }
            if (! isset($map[$sku]) || $lmp < $map[$sku]) {
                $map[$sku] = $lmp;
            }
        }

        return $map;
    }

    private static function competitorLandedSql(string $table, string $priceCol): ?string
    {
        $hasPrice = Schema::hasColumn($table, $priceCol);
        $hasLanded = Schema::hasColumn($table, 'landed_price');
        $hasTotal = Schema::hasColumn($table, 'total_price');
        $hasShip = Schema::hasColumn($table, 'shipping_cost');
        if ($hasLanded && $hasTotal && $hasPrice) {
            return 'COALESCE(NULLIF(CAST(landed_price AS DECIMAL(12,2)), 0), NULLIF(CAST(total_price AS DECIMAL(12,2)), 0), CAST('.$priceCol.' AS DECIMAL(12,2)))';
        }
        if ($hasTotal) {
            return 'CAST(total_price AS DECIMAL(12,2))';
        }
        if ($hasPrice && $hasShip) {
            return '(CAST('.$priceCol.' AS DECIMAL(12,2)) + CAST(COALESCE(shipping_cost, 0) AS DECIMAL(12,2)))';
        }
        if ($hasPrice) {
            return 'CAST('.$priceCol.' AS DECIMAL(12,2))';
        }

        return null;
    }

    /**
     * @return array<string, float>
     */
    private static function loadInventoryMap(): array
    {
        if (! Schema::hasTable('shopify_skus') || ! Schema::hasColumn('shopify_skus', 'inv')) {
            return [];
        }
        try {
            $rows = DB::table('shopify_skus')->where('inv', '>', 0)->whereNotNull('sku')->where('sku', '!=', '')->get(['sku', 'inv']);
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpChannelCounts inventory load failed: '.$e->getMessage());

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $sku = self::normSku((string) $row->sku);
            if ($sku !== '' && ! str_starts_with($sku, 'PARENT')) {
                $map[$sku] = (float) $row->inv;
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private static function skuLinkRoots(): array
    {
        if (! Schema::hasTable('lmp_sku_links')) {
            return [];
        }
        try {
            $pairs = DB::table('lmp_sku_links')->get(['sku_norm', 'linked_sku_norm']);
        } catch (\Throwable $e) {
            return [];
        }

        $parent = [];
        $find = static function (string $x) use (&$parent, &$find): string {
            $parent[$x] = $parent[$x] ?? $x;
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }

            return $parent[$x];
        };
        foreach ($pairs as $pair) {
            $a = strtoupper(trim((string) ($pair->sku_norm ?? '')));
            $b = strtoupper(trim((string) ($pair->linked_sku_norm ?? '')));
            if ($a === '' || $b === '') {
                continue;
            }
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$ra] = $rb;
            }
        }

        $roots = [];
        foreach (array_keys($parent) as $sku) {
            $roots[$sku] = $find($sku);
        }

        return $roots;
    }

    /**
     * @param  array<string, float>  $lmpBySku
     * @param  array<string, string>  $rootBySku
     * @return array<string, float>
     */
    private static function expandLmp(array $lmpBySku, array $rootBySku): array
    {
        if ($lmpBySku === [] || $rootBySku === []) {
            return $lmpBySku;
        }
        $minByRoot = [];
        foreach ($lmpBySku as $sku => $lmp) {
            if (! ($lmp > 0)) {
                continue;
            }
            $root = $rootBySku[$sku] ?? $sku;
            if (! isset($minByRoot[$root]) || $lmp < $minByRoot[$root]) {
                $minByRoot[$root] = $lmp;
            }
        }
        $out = $lmpBySku;
        foreach ($rootBySku as $sku => $root) {
            if (isset($minByRoot[$root])) {
                $out[$sku] = $minByRoot[$root];
            }
        }

        return $out;
    }

    /**
     * @return array{table: string, sku: string, price: string, adjust?: string}|null
     */
    private static function priceSource(string $key): ?array
    {
        return match ($key) {
            'amazon' => ['table' => 'amazon_datsheets', 'sku' => 'sku', 'price' => 'price'],
            'ebay' => ['table' => 'ebay_metrics', 'sku' => 'sku', 'price' => 'ebay_price'],
            'ebay2' => ['table' => 'ebay_2_metrics', 'sku' => 'sku', 'price' => 'ebay_price'],
            'ebay3' => ['table' => 'ebay_3_metrics', 'sku' => 'sku', 'price' => 'ebay_price'],
            'shopifyb2c' => ['table' => 'shopify_skus', 'sku' => 'sku', 'price' => 'price'],
            'shopifyb2b' => ['table' => 'store_listing_prices', 'sku' => 'sku', 'price' => 'selling_price'],
            'macys' => ['table' => 'macys_price_data', 'sku' => 'sku', 'price' => 'price'],
            'reverb' => ['table' => 'reverb_products', 'sku' => 'sku', 'price' => 'price'],
            'temu' => ['table' => 'temu_metrics', 'sku' => 'sku', 'price' => 'base_price', 'adjust' => 'temu'],
            'temu2' => ['table' => 'temu2_pricing', 'sku' => 'sku', 'price' => 'base_price', 'adjust' => 'temu'],
            'temu3' => ['table' => 'temu3_pricing', 'sku' => 'sku', 'price' => 'base_price', 'adjust' => 'temu'],
            'aliexpress' => ['table' => 'aliexpress_pricing_prices', 'sku' => 'sku', 'price' => 'price'],
            'tiktok' => ['table' => 'tiktok_products', 'sku' => 'sku', 'price' => 'price'],
            'tiktok2' => ['table' => 'tiktok_products_two', 'sku' => 'sku', 'price' => 'price'],
            default => null,
        };
    }

    private static function normSku(string $sku): string
    {
        return ShopifySku::normalizeSkuForShopifyLookup($sku);
    }

    private static function entryIgnored(mixed $v): bool
    {
        if ($v === true || $v === 1 || $v === '1') {
            return true;
        }
        if (is_string($v)) {
            return in_array(strtolower(trim($v)), ['true', 'yes', 'on'], true);
        }

        return false;
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
            $k = LmpMissingChannelCounts::normalize($name);
            $map[$k] = [
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
            $k = LmpMissingChannelCounts::normalize((string) $alias);
            if ($k !== '' && isset($masters[$k])) {
                return $masters[$k];
            }
        }

        return null;
    }
}
