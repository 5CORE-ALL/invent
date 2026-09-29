<?php

namespace App\Support;

use App\Models\ShopifySku;

/**
 * Dil as shown on CP Master (/product-master):
 * round(OV L30 sold / Inventory × 100). Inventory 0 or a missing value is not a Dil.
 */
class CpMasterDil
{
    public static function percent($ovl30, $inv): ?int
    {
        if ($ovl30 === null || $ovl30 === '' || $inv === null || $inv === '') {
            return null;
        }
        if (! is_numeric($ovl30) || ! is_numeric($inv)) {
            return null;
        }
        $inventory = (float) $inv;
        if ($inventory <= 0) {
            return null;
        }

        return (int) round(((float) $ovl30 / $inventory) * 100);
    }

    /**
     * Slab match. 0–0 is OV L30 sold = 0. A sale that rounds to 0% stays above 0
     * so it is not counted as 0 sold.
     */
    public static function slabPercent($ovl30, $inv): ?float
    {
        $rounded = self::percent($ovl30, $inv);
        if ($rounded === null) {
            return null;
        }
        if ($rounded === 0 && is_numeric($ovl30) && (float) $ovl30 !== 0.0) {
            return ((float) $ovl30 / (float) $inv) * 100;
        }

        return (float) $rounded;
    }

    /**
     * Overlay shopify OV L30 / Inv the same way CP Master matches SKUs,
     * then set cp_dil. Exact SQL joins miss hyphen / space variants and
     * those rows were counted as Dil 0.
     */
    public static function hydrate(iterable $rows): void
    {
        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row->resolved_sku ?? ''));
            if ($sku === '' || str_starts_with(strtoupper($sku), 'PARENT')) {
                continue;
            }
            $skus[$sku] = $sku;
        }

        $map = $skus === []
            ? collect()
            : ShopifySku::mapByProductSkus(array_values($skus));

        foreach ($rows as $row) {
            $sku = trim((string) ($row->resolved_sku ?? ''));
            if ($sku !== '' && str_starts_with(strtoupper($sku), 'PARENT')) {
                $row->cp_dil = null;
                continue;
            }
            $shop = ($sku !== '' && isset($map[$sku])) ? $map[$sku] : null;
            if ($shop !== null) {
                $row->shopify_inv = $shop->inv;
                $row->shopify_qty = $shop->quantity;
            }
            $row->cp_dil = self::percent($row->shopify_qty ?? null, $row->shopify_inv ?? null);
        }
    }
}
