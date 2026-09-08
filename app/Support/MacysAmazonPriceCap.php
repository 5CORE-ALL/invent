<?php

namespace App\Support;

use App\Models\AmazonDatasheet;

/**
 * Macys live price push must not go below the Amazon datasheet price (A Price).
 * Dil / rule SPRICE is kept when it is at or above A Price.
 */
class MacysAmazonPriceCap
{
    public static function cap(float $price, ?float $amazonPrice): float
    {
        $price = round($price, 2);
        $amazon = $amazonPrice !== null ? round((float) $amazonPrice, 2) : 0.0;
        if ($price > 0 && $amazon > 0 && $price < $amazon) {
            return $amazon;
        }

        return $price;
    }

    public static function amazonPriceForSku(string $sku): float
    {
        $sku = strtoupper(trim(str_replace("\xC2\xA0", ' ', $sku)));
        if ($sku === '') {
            return 0.0;
        }

        $row = AmazonDatasheet::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->orderByDesc('updated_at')
            ->first(['price']);

        if (! $row || ! is_numeric($row->price)) {
            return 0.0;
        }

        $price = round((float) $row->price, 2);

        return $price > 0 ? $price : 0.0;
    }

    /**
     * @return array{price: float, amazon_price: float, capped: bool, floored: bool}
     */
    public static function applyForSku(string $sku, float $price): array
    {
        $requested = round($price, 2);
        $amazon = self::amazonPriceForSku($sku);
        $applied = self::cap($requested, $amazon);
        $floored = $amazon > 0 && $requested > 0 && $applied > $requested + 0.0001;

        return [
            'price' => $applied,
            'amazon_price' => $amazon,
            'capped' => $floored,
            'floored' => $floored,
        ];
    }

    public static function capForSku(string $sku, float $price): float
    {
        return self::applyForSku($sku, $price)['price'];
    }
}
