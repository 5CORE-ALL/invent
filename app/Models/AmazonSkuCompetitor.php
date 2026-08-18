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

    public static function lowestFromCollection($items)
    {
        // L1 = lowest non-ignored by landed (price + paid delivery; FREE = no add)
        $active = collect($items)->filter(fn ($item) => empty($item->ignored));
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
        $normalizedSku = self::normalizeSkuKey($sku);

        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->forMarketplace($marketplace)
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
