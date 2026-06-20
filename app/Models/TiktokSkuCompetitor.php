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
        'min_price',
        'max_price',
        'rating',
        'reviews',
        'sold_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
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

    public function scopeWherePositivePrice($query)
    {
        return $query->whereRaw('CAST(price AS DECIMAL(10,2)) > 0');
    }

    public function scopeOrderByNumericPrice($query, string $direction = 'asc')
    {
        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        return $query->orderByRaw('CAST(price AS DECIMAL(10,2)) ' . $dir);
    }

    public static function sortCollectionByNumericPrice($items)
    {
        return collect($items)->sortBy(fn ($item) => (float) ($item->price ?? 0))->values();
    }

    public static function lowestFromCollection($items)
    {
        return collect($items)->sortBy(fn ($item) => (float) ($item->price ?? 0))->first();
    }

    /**
     * Group competitors by normalized SKU. Used by /tiktok-pricing to attach
     * `lmp_price` / `lmp_entries` / `lmp_entries_total` to each row in one
     * pass. Mirrors AmazonSkuCompetitor::buildGroupedLookup().
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
            ->orderByNumericPrice('asc')
            ->first();
    }

    public static function getCompetitorsForSku($sku, string $marketplace = 'tiktok')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->orderByNumericPrice('asc')
            ->get();
    }
}
