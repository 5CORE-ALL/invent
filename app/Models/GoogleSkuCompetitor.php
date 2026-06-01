<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleSkuCompetitor extends Model
{
    protected $table = 'google_sku_competitors';

    protected $fillable = [
        'sku',
        'product_id',
        'source',
        'marketplace',
        'search_query',
        'product_link',
        'product_title',
        'image',
        'price',
        'rating',
        'reviews',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'rating' => 'decimal:2',
        'reviews' => 'integer',
    ];

    public static function normalizeSkuKey(?string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $sku)));
    }

    public function scopeWherePositivePrice($query)
    {
        return $query->where('price', '>', 0);
    }

    public function scopeOrderByNumericPrice($query, string $direction = 'asc')
    {
        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        return $query->orderByRaw('CAST(price AS DECIMAL(10,2)) ' . $dir);
    }

    public static function lowestFromCollection($items)
    {
        return collect($items)->sortBy(fn ($item) => (float) ($item->price ?? 0))->first();
    }

    public static function sortCollectionByNumericPrice($items)
    {
        return collect($items)->sortBy(fn ($item) => (float) ($item->price ?? 0))->values();
    }

    /**
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    public static function buildGroupedLookup(string $marketplace = 'google'): array
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

    public static function getCompetitorsForSku($sku, $marketplace = 'google')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->orderByNumericPrice('asc')
            ->get();
    }
}
