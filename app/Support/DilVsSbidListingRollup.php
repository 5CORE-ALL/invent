<?php

namespace App\Support;

/**
 * One Dil vs SBid input set per eBay listing (item_id).
 * INV / OV L30 are per SKU and are summed (family Dil).
 * Views, eBay sold and L7 views are listing-level on eBay 2 — the same
 * number is stored on every variation — so those use one copy, not a sum.
 * Std NPFT % is the average of SKUs that have a Std Prc.
 */
final class DilVsSbidListingRollup
{
    /**
     * @param  list<array{sku?:string,quantity?:float|int|null,inv?:float|int|null,views?:float|int|null,ebay_l30?:float|int|null,ebay_l60?:float|int|null,l7_views?:float|int|null,npft?:float|null}>  $members
     * @return array{sku:string,quantity:?float,inv:?float,views:float,ebay_l30:float,ebay_l60:float,l7_views:float,npft:?float}
     */
    public static function combine(array $members): array
    {
        $qty = 0.0;
        $inv = 0.0;
        $hasQty = false;
        $hasInv = false;
        $views = 0.0;
        $l30 = 0.0;
        $l60 = 0.0;
        $l7 = 0.0;
        $npftSum = 0.0;
        $npftN = 0;
        $sku = '';

        foreach ($members as $m) {
            if (! is_array($m)) {
                continue;
            }
            if ($sku === '') {
                $sku = trim((string) ($m['sku'] ?? ''));
            }
            if (isset($m['quantity']) && is_numeric($m['quantity'])) {
                $qty += (float) $m['quantity'];
                $hasQty = true;
            }
            if (isset($m['inv']) && is_numeric($m['inv'])) {
                $inv += (float) $m['inv'];
                $hasInv = true;
            }
            $views = max($views, (float) ($m['views'] ?? 0));
            $l30 = max($l30, (float) ($m['ebay_l30'] ?? 0));
            $l60 = max($l60, (float) ($m['ebay_l60'] ?? 0));
            $l7 = max($l7, (float) ($m['l7_views'] ?? 0));
            if (isset($m['npft']) && is_numeric($m['npft'])) {
                $npftSum += (float) $m['npft'];
                $npftN++;
            }
        }

        return [
            'sku' => $sku,
            'quantity' => $hasQty ? $qty : null,
            'inv' => $hasInv ? $inv : null,
            'views' => $views,
            'ebay_l30' => $l30,
            'ebay_l60' => $l60,
            'l7_views' => $l7,
            'npft' => $npftN > 0 ? $npftSum / $npftN : null,
        ];
    }
}
