<?php

namespace App\Support\Marketplace;

/**
 * Active Channel PLS row: sales fields from pls_sales.
 */
class PlsActiveChannelSales
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array{
     *     l30_sales: float,
     *     l60_sales: float,
     *     l30_orders: int,
     *     l60_orders: int,
     *     qty: int,
     *     y_sales: float,
     *     l7_sales: float
     * }  $live
     * @return array<string, mixed>
     */
    public static function applyToRow(array $row, array $live): array
    {
        $l30 = (float) $live['l30_sales'];
        $l60 = (float) $live['l60_sales'];

        $row['Channel '] = 'PLS';
        $row['L30 Sales'] = (int) round($l30);
        $row['L-60 Sales'] = (int) round($l60);
        $row['L30 Orders'] = (int) $live['l30_orders'];
        $row['L60 Orders'] = (int) $live['l60_orders'];
        $row['Qty'] = (int) $live['qty'];
        $row['Y Sales'] = round((float) $live['y_sales'], 2);
        $row['L7 Sales'] = round((float) $live['l7_sales'], 2);

        if ($l60 > 0) {
            $row['Growth'] = round((($l30 - $l60) / $l60) * 100, 2).'%';
        } elseif (! isset($row['Growth']) || $row['Growth'] === '' || $row['Growth'] === null) {
            $row['Growth'] = '0%';
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public static function stubRow(array $meta, array $live): array
    {
        $row = [
            'Channel ' => 'PLS',
            'alias' => $meta['alias'] ?? null,
            'type' => $meta['type'] ?? 'B2C',
            'sheet_link' => $meta['sheet_link'] ?? null,
            'missing_link' => $meta['missing_link'] ?? '/pls-pricing',
            'channel_percentage' => $meta['channel_percentage'] ?? '',
            'base' => $meta['base'] ?? 0,
            'target' => $meta['target'] ?? 0,
            'Gprofit%' => '0%',
            'gprofitL60' => '0%',
            'G Roi' => 0,
            'G RoiL60' => 0,
            'Total PFT' => 0,
            'N PFT' => '0%',
            'N ROI' => 0,
            'cogs' => 0,
            'Total Ad Spend' => 0,
            'Ads%' => '0%',
            'TACOS %' => '0%',
            'TACOS' => '0%',
            'Today Sales' => 0,
            'P-Sales' => 0,
            'Map' => 0,
            'Miss' => 0,
            'NMap' => 0,
            'Total Views' => 0,
            'W/Ads' => $meta['w_ads'] ?? 0,
            'NR' => $meta['nr'] ?? 0,
            'Update' => $meta['update'] ?? 0,
        ];

        return self::applyToRow($row, $live);
    }
}
