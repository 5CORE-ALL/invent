<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonSkuCompetitor extends Model
{
    protected $table = 'amazon_sku_competitors';

    protected $fillable = [
        'sku',
        'asin',
        'marketplace',
        'product_link',
        'image',
        'product_title',
        'seller_name',
        'price',
        'ignored',
        'rating',
        'reviews',
        'extracted_old_price',
        'delivery',
        'stock',
        'stock_quantity',
        'monthly_revenue',
        'monthly_units_sold',
        'buy_box_owner',
        'seller_type_js',
        'sales_data_updated_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ignored' => 'boolean',
        'rating' => 'decimal:2',
        'reviews' => 'integer',
        'extracted_old_price' => 'decimal:2',
        'delivery' => 'array',
        'stock_quantity' => 'integer',
        'monthly_revenue' => 'decimal:2',
        'monthly_units_sold' => 'integer',
        'sales_data_updated_at' => 'datetime',
    ];

    public static function normalizeSkuKey(?string $sku): string
    {
        $s = str_replace("\xC2\xA0", ' ', (string) $sku);
        $collapsed = preg_replace('/\s+/u', ' ', trim($s));
        if ($collapsed === null) {
            $collapsed = preg_replace('/\s+/', ' ', trim($s)) ?? trim($s);
        }

        return strtoupper($collapsed);
    }

    public function scopeWherePositivePrice($query)
    {
        return $query->whereRaw('CAST(price AS DECIMAL(10,2)) > 0');
    }

    public function scopeOrderByNumericPrice($query, string $direction = 'asc')
    {
        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        return $query->orderByRaw('CAST(price AS DECIMAL(10,2)) ' . $dir);
    }

    /**
     * Parse Amazon delivery text/array into a ship cost.
     * FREE / Prime free → 0; "$6.99 delivery..." → 6.99; unknown → null.
     */
    public static function parseShipCost($delivery): ?float
    {
        if ($delivery === null || $delivery === '') {
            return null;
        }

        if (is_array($delivery)) {
            $delivery = implode(', ', array_slice($delivery, 0, 3));
        } elseif (is_string($delivery)) {
            $decoded = json_decode($delivery, true);
            if (is_array($decoded)) {
                $delivery = implode(', ', array_slice($decoded, 0, 3));
            }
        }

        if (is_numeric($delivery)) {
            return max(0.0, (float) $delivery);
        }

        $text = trim((string) $delivery);
        if ($text === '') {
            return null;
        }

        if (preg_match('/\bfree\b/i', $text)) {
            return 0.0;
        }

        if (preg_match('/\$\s*([0-9]+(?:\.[0-9]{1,2})?)/', $text, $m)) {
            return max(0.0, (float) $m[1]);
        }

        return null;
    }

    /**
     * LMP landed = item price + paid delivery. FREE delivery does not add.
     */
    public static function landedPrice($item): ?float
    {
        $price = (float) (is_object($item) ? ($item->price ?? 0) : ($item['price'] ?? 0));
        if ($price <= 0) {
            return null;
        }

        $delivery = is_object($item) ? ($item->delivery ?? null) : ($item['delivery'] ?? null);
        $ship = self::parseShipCost($delivery);
        // Paid ship only; FREE (0) or missing delivery → do not add
        if ($ship !== null && $ship > 0) {
            return round($price + $ship, 2);
        }

        return round($price, 2);
    }

    /**
     * Whether a competitor is excluded from L1. Handles model/array and 1/"1"/true.
     * Reads raw attributes so Eloquent casts / empty() cannot hide ignored=1.
     */
    public static function isIgnored($item): bool
    {
        $v = false;
        if (is_array($item)) {
            $v = $item['ignored'] ?? false;
        } elseif (is_object($item)) {
            if (method_exists($item, 'getAttributes')) {
                $attrs = $item->getAttributes();
                if (array_key_exists('ignored', $attrs)) {
                    $v = $attrs['ignored'];
                } elseif (method_exists($item, 'getRawOriginal') && $item->getRawOriginal('ignored') !== null) {
                    $v = $item->getRawOriginal('ignored');
                } else {
                    $v = $item->ignored ?? false;
                }
            } else {
                $v = $item->ignored ?? false;
            }
        }
        if (is_bool($v)) {
            return $v;
        }
        if (is_numeric($v)) {
            return (int) $v !== 0;
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Competitor ids currently marked ignored (raw DB, not Eloquent).
     *
     * @return array<int, true>
     */
    public static function ignoredIdSet(): array
    {
        $table = (new static)->getTable();
        if (! \Illuminate\Support\Facades\Schema::hasColumn($table, 'ignored')) {
            return [];
        }

        return array_fill_keys(
            \Illuminate\Support\Facades\DB::table($table)
                ->where('ignored', 1)
                ->pluck('id')
                ->all(),
            true
        );
    }

    /**
     * Write ignored to the DB column (tinyint 0/1). Avoids Eloquent boolean-cast
     * dirty-checks that can skip the UPDATE so Ignore looks saved until refresh.
     */
    public static function persistIgnored(int $id, bool $ignored): bool
    {
        if ($id < 1) {
            return false;
        }

        $table = (new static)->getTable();
        $updated = \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->update([
            'ignored' => $ignored ? 1 : 0,
            'updated_at' => now(),
        ]);
        if ($updated > 0) {
            return true;
        }

        return \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->exists();
    }

    /**
     * Persist Ignore onto every row with this ASIN so a sibling copy cannot
     * revive L1 after refresh (same idea as eBay applyIgnoreToSameItemIds).
     */
    public static function persistIgnoredForAsin(string $asin, bool $ignored): int
    {
        $asin = strtoupper(trim($asin));
        if ($asin === '' || ! preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            return 0;
        }

        $table = (new static)->getTable();
        if (! \Illuminate\Support\Facades\Schema::hasColumn($table, 'ignored')) {
            return 0;
        }

        return (int) \Illuminate\Support\Facades\DB::table($table)
            ->whereRaw('UPPER(TRIM(asin)) = ?', [$asin])
            ->update([
                'ignored' => $ignored ? 1 : 0,
                'updated_at' => now(),
            ]);
    }

    /**
     * If any copy of an ASIN is ignored, treat every copy as ignored for L1.
     *
     * @param  iterable<mixed>  $items
     */
    public static function applyIgnoreToSameAsins($items): \Illuminate\Support\Collection
    {
        $collection = collect($items)->values();
        $ignoredAsins = [];
        foreach ($collection as $item) {
            if (! self::isIgnored($item)) {
                continue;
            }
            $asin = strtoupper(trim((string) (is_object($item) ? ($item->asin ?? '') : ($item['asin'] ?? ''))));
            if ($asin !== '') {
                $ignoredAsins[$asin] = true;
            }
        }
        if ($ignoredAsins === []) {
            return $collection;
        }

        return $collection->map(function ($item) use ($ignoredAsins) {
            $asin = strtoupper(trim((string) (is_object($item) ? ($item->asin ?? '') : ($item['asin'] ?? ''))));
            if ($asin === '' || ! isset($ignoredAsins[$asin])) {
                return $item;
            }
            if (is_object($item)) {
                $item->ignored = true;
            } elseif (is_array($item)) {
                $item['ignored'] = 1;
            }

            return $item;
        })->values();
    }

    public static function lowestFromCollection($items)
    {
        // L1 = lowest non-ignored by landed (price + paid delivery; FREE = no add)
        $active = collect($items)->filter(fn ($item) => ! self::isIgnored($item));
        $pool = $active->isNotEmpty() ? $active : collect();

        return $pool->sortBy(fn ($item) => self::landedPrice($item) ?? PHP_FLOAT_MAX)->first();
    }

    public static function sortCollectionByNumericPrice($items)
    {
        // Sort by landed LMP (price + paid delivery) so L1 matches outer/inner badges
        return collect($items)->sortBy(fn ($item) => self::landedPrice($item) ?? PHP_FLOAT_MAX)->values();
    }

    /**
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    public static function buildGroupedLookup(string $marketplace = 'amazon'): array
    {
        $records = self::query()
            ->forMarketplace($marketplace)
            ->wherePositivePrice()
            ->get()
            ->groupBy(fn ($item) => self::normalizeSkuKey($item->sku));

        return [
            'details' => $records->map(fn ($items) => self::sortCollectionByNumericPrice($items)),
            'lowest' => $records->map(fn ($items) => self::lowestFromCollection($items)),
        ];
    }

    /**
     * Get the lowest priced competitor for a given SKU
     * Handles SKUs with line breaks, extra spaces, and case differences
     */
    public static function scopeForMarketplace($query, string $marketplace = 'amazon')
    {
        $key = strtolower(trim($marketplace));
        if (in_array($key, ['amazon', 'amz', 'us'], true)) {
            return $query->whereRaw('LOWER(TRIM(marketplace)) IN (?, ?, ?)', ['amazon', 'amz', 'us']);
        }

        return $query->where('marketplace', $marketplace);
    }

    public static function getLowestPriceForSku($sku, $marketplace = 'amazon')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        $q = self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->forMarketplace($marketplace)
            ->wherePositivePrice();
        if (\Illuminate\Support\Facades\Schema::hasColumn('amazon_sku_competitors', 'ignored')) {
            $q->where(function ($qq) {
                $qq->where('ignored', false)->orWhereNull('ignored');
            });
        }

        // Rank in PHP: paid delivery adds to price; FREE does not
        return self::lowestFromCollection($q->get());
    }

    /**
     * Get all competitors for a given SKU ordered by price
     * Handles SKUs with line breaks, extra spaces, and case differences
     */
    public static function getCompetitorsForSku($sku, $marketplace = 'amazon')
    {
        return self::getCompetitorsForSkus([(string) $sku], $marketplace);
    }

    /**
     * Dedupe competitors by ASIN, keeping lowest price first.
     */
    public static function dedupeByAsin(iterable $competitors): \Illuminate\Support\Collection
    {
        $seen = [];
        $unique = [];

        // Keep the lowest landed copy of each ASIN (merge order must not pick a stale sibling).
        foreach (self::sortCollectionByNumericPrice($competitors) as $competitor) {
            $asin = strtoupper(trim((string) ($competitor->asin ?? '')));
            $key = $asin !== '' ? $asin : 'id:' . ($competitor->id ?? spl_object_id($competitor));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $competitor;
        }

        return collect($unique)->values();
    }

    /**
     * @param  list<string>  $skus
     */
    public static function getCompetitorsForSkus(array $skus, string $marketplace = 'amazon'): \Illuminate\Support\Collection
    {
        $rawSkus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ))));
        $keys = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => self::normalizeSkuKey($sku),
            $rawSkus
        ))));

        if ($keys === []) {
            return collect();
        }

        $keySet = array_fill_keys($keys, true);

        $competitors = self::query()
            ->forMarketplace($marketplace)
            ->wherePositivePrice()
            ->where(function ($q) use ($rawSkus, $keys) {
                foreach ($rawSkus as $sku) {
                    $q->orWhere('sku', $sku);
                }
                foreach ($keys as $key) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$key]);
                    if (str_contains($key, ' ')) {
                        $token = explode(' ', $key, 2)[0];
                        if (strlen($token) >= 3) {
                            $q->orWhere('sku', 'like', $token.'%');
                        }
                    }
                }
            })
            ->get()
            ->filter(static fn ($item) => isset($keySet[self::normalizeSkuKey($item->sku ?? '')]))
            ->values();

        return self::dedupeByAsin($competitors);
    }
}
