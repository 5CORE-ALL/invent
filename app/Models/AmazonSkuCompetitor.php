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
        'rating',
        'reviews',
        'extracted_old_price',
        'delivery',
        'monthly_revenue',
        'monthly_units_sold',
        'buy_box_owner',
        'seller_type_js',
        'sales_data_updated_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'rating' => 'decimal:2',
        'reviews' => 'integer',
        'extracted_old_price' => 'decimal:2',
        'delivery' => 'array',
        'monthly_revenue' => 'decimal:2',
        'monthly_units_sold' => 'integer',
        'sales_data_updated_at' => 'datetime',
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
    public static function buildGroupedLookup(string $marketplace = 'amazon'): array
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

    /**
     * Get the lowest priced competitor for a given SKU
     * Handles SKUs with line breaks, extra spaces, and case differences
     */
    public static function getLowestPriceForSku($sku, $marketplace = 'amazon')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->orderByNumericPrice('asc')
            ->first();
    }

    /**
     * Get all competitors for a given SKU ordered by price
     * Handles SKUs with line breaks, extra spaces, and case differences
     */
    public static function getCompetitorsForSku($sku, $marketplace = 'amazon')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->orderByNumericPrice('asc')
            ->get();
    }

    /**
     * Dedupe competitors by ASIN, keeping lowest price first.
     */
    public static function dedupeByAsin(iterable $competitors): \Illuminate\Support\Collection
    {
        $seen = [];
        $unique = [];

        foreach ($competitors as $competitor) {
            $asin = strtoupper(trim((string) ($competitor->asin ?? '')));
            $key = $asin !== '' ? $asin : 'id:' . ($competitor->id ?? spl_object_id($competitor));
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
    public static function getCompetitorsForSkus(array $skus, string $marketplace = 'amazon'): \Illuminate\Support\Collection
    {
        $competitors = collect();

        foreach ($skus as $sku) {
            $competitors = $competitors->merge(self::getCompetitorsForSku($sku, $marketplace));
        }

        return self::dedupeByAsin($competitors);
    }
}
