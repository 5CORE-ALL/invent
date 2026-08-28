<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class NeweggSkuCompetitor extends Model
{
    protected $table = 'newegg_sku_competitors';

    protected $fillable = [
        'sku',
        'product_id',
        'marketplace',
        'product_title',
        'product_link',
        'image',
        'seller_name',
        'price',
        'shipping_cost',
        'ignored',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'ignored' => 'boolean',
    ];

    public static function normalizeSkuKey(?string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $sku)));
    }

    /** Landed competitor price = item price + shipping (FREE/null => 0). */
    public static function landedPrice($item): float
    {
        return (float) ($item->price ?? 0) + (float) ($item->shipping_cost ?? 0);
    }

    public function scopeWherePositivePrice($query)
    {
        return $query->whereRaw('CAST(price AS DECIMAL(10,2)) > 0');
    }

    public function scopeOrderByLandedPrice($query, string $direction = 'asc')
    {
        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        return $query->orderByRaw(
            '(CAST(price AS DECIMAL(10,2)) + CAST(COALESCE(shipping_cost, 0) AS DECIMAL(10,2))) '.$dir
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
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    public static function buildGroupedLookup(string $marketplace = 'newegg'): array
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

    public static function getLowestPriceForSku($sku, string $marketplace = 'newegg')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        $query = self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->wherePositivePrice();
        if (Schema::hasColumn((new static)->getTable(), 'ignored')) {
            $query->where(function ($q) {
                $q->where('ignored', false)->orWhereNull('ignored');
            });
        }

        return $query->orderByLandedPrice('asc')->first();
    }

    public static function getCompetitorsForSku($sku, string $marketplace = 'newegg')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->orderByLandedPrice('asc')
            ->get();
    }

    public static function dedupeByProductId(iterable $competitors): \Illuminate\Support\Collection
    {
        $seen = [];
        $unique = [];

        foreach ($competitors as $competitor) {
            $productId = strtoupper(trim((string) ($competitor->product_id ?? '')));
            $key = $productId !== '' ? $productId : 'id:'.($competitor->id ?? spl_object_id($competitor));
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
    public static function getCompetitorsForSkus(array $skus, string $marketplace = 'newegg'): \Illuminate\Support\Collection
    {
        $competitors = collect();

        foreach ($skus as $sku) {
            $competitors = $competitors->merge(self::getCompetitorsForSku($sku, $marketplace));
        }

        return self::dedupeByProductId($competitors);
    }
}
