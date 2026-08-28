<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokSkuCompetitor extends Model
{
    protected $table = 'tiktok_sku_competitors';

    protected $fillable = [
        'sku',
        'product_id',
        'marketplace',
        'region',
        'product_title',
        'product_link',
        'image',
        'seller_name',
        'brand_name',
        'price',
        'ignored',
        'shipping_cost',
        'min_price',
        'max_price',
        'rating',
        'reviews',
        'sold_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ignored' => 'boolean',
        'shipping_cost' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'rating' => 'decimal:2',
        'reviews' => 'integer',
        'sold_count' => 'integer',
    ];

    public static function normalizeSkuKey(?string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $sku)));
    }

    /**
     * Landed competitor price = item price + shipping (FREE/null => 0).
     */
    public static function landedPrice($item): float
    {
        return (float) ($item->price ?? 0) + (float) ($item->shipping_cost ?? 0);
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

    public function scopeOrderByLandedPrice($query, string $direction = 'asc')
    {
        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        return $query->orderByRaw(
            '(CAST(price AS DECIMAL(10,2)) + CAST(COALESCE(shipping_cost, 0) AS DECIMAL(10,2))) ' . $dir
        );
    }

    public static function sortCollectionByNumericPrice($items)
    {
        return collect($items)->sortBy(fn ($item) => self::landedPrice($item))->values();
    }

    public static function lowestFromCollection($items)
    {
        return collect($items)
            ->filter(fn ($item) => empty($item->ignored))
            ->sortBy(fn ($item) => self::landedPrice($item))
            ->first();
    }

    /**
     * Group competitors by normalized SKU. Used by /tiktok-pricing to attach
     * `lmp_price` / `lmp_entries` / `lmp_entries_total` to each SKU row in one
     * pass. Mirrors AmazonSkuCompetitor::buildGroupedLookup().
     * Sorted / lowest use landed price (price + shipping).
     *
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    public static function buildGroupedLookup(string $marketplace = 'tiktok'): array
    {
        $records = self::where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->get()
            ->groupBy(fn ($item) => self::normalizeSkuKey($item->sku));

        return [
            'details' => $records->map(fn ($items) => self::sortCollectionByNumericPrice($items)),
            'lowest' => $records->map(fn ($items) => self::lowestFromCollection($items)),
        ];
    }

    public static function getLowestPriceForSku($sku, string $marketplace = 'tiktok')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->orderByLandedPrice('asc')
            ->first();
    }

    public static function getCompetitorsForSku($sku, string $marketplace = 'tiktok')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->orderByLandedPrice('asc')
            ->get();
    }

    /**
     * Dedupe competitors by product_id (fallback to row id), keeping lowest landed price first.
     * Mirrors AmazonSkuCompetitor::dedupeByAsin() for TikTok Shop product IDs.
     */
    public static function dedupeByProductId(iterable $competitors): \Illuminate\Support\Collection
    {
        $seen = [];
        $unique = [];

        foreach ($competitors as $competitor) {
            $productId = strtoupper(trim((string) ($competitor->product_id ?? '')));
            $key = $productId !== '' ? $productId : 'id:' . ($competitor->id ?? spl_object_id($competitor));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $competitor;
        }

        return self::sortCollectionByNumericPrice($unique);
    }

    /**
     * @param  list<string>  $skus
     */
    public static function getCompetitorsForSkus(array $skus, string $marketplace = 'tiktok'): \Illuminate\Support\Collection
    {
        $competitors = collect();

        foreach ($skus as $sku) {
            $competitors = $competitors->merge(self::getCompetitorsForSku($sku, $marketplace));
        }

        return self::dedupeByProductId($competitors);
    }
}
