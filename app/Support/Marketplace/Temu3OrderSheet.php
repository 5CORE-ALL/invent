<?php

namespace App\Support\Marketplace;

use Carbon\Carbon;

/**
 * Temu Seller Center order export (TSV/CSV/XLSX) used by Temu 3 Analytics.
 * Sample header: Order ID, order status, contribution sku, SKU ID,
 * quantity purchased, purchase date, goods base price, …
 */
class Temu3OrderSheet
{
    public static function normalizeHeader($header): string
    {
        $headerLower = strtolower(trim((string) $header));
        $headerNormalized = preg_replace('/[^a-z0-9_]/', '_', $headerLower);
        $headerNormalized = preg_replace('/_+/', '_', $headerNormalized);
        $headerNormalized = trim((string) $headerNormalized, '_');

        $mapping = [
            'order_id' => 'order_id',
            'order_status' => 'order_status',
            'fulfillment_mode' => 'fulfillment_mode',
            'order_item_id' => 'order_item_id',
            'order_item_status' => 'order_item_status',
            'product_name_by_customer_order' => 'product_name_by_customer_order',
            'product_name' => 'product_name',
            'variation' => 'variation',
            'contribution_sku' => 'contribution_sku',
            'sku_id' => 'sku_id',
            'quantity_purchased' => 'quantity_purchased',
            'quantity_shipped' => 'quantity_shipped',
            'quantity_to_ship' => 'quantity_to_ship',
            'quantity_canceled' => 'quantity_canceled',
            'quantity_cancelled' => 'quantity_canceled',
            'purchase_date' => 'purchase_date',
            'purchase_date_utc_0_' => 'purchase_date',
            'purchase_date_utc_8_' => 'purchase_date',
            'latest_shipping_time' => 'latest_shipping_time',
            'latest_shipping_time_utc_0_' => 'latest_shipping_time',
            'latest_shipping_time_utc_8_' => 'latest_shipping_time',
            'latest_delivery_time' => 'latest_delivery_time',
            'latest_delivery_time_utc_0_' => 'latest_delivery_time',
            'latest_delivery_time_utc_8_' => 'latest_delivery_time',
            'activity_goods_base_price' => 'activity_goods_base_price',
            'goods_base_price' => 'base_price_total',
            'base_price_total' => 'base_price_total',
            'tracking_number' => 'tracking_number',
            'carrier' => 'carrier',
            'order_settlement_status' => 'order_settlement_status',
        ];

        return $mapping[$headerNormalized] ?? $headerNormalized;
    }

    public static function sanitizePrice($value): ?float
    {
        if ($value === null || $value === '' || $value === '?') {
            return null;
        }

        $cleaned = preg_replace('/[$,\s]/', '', (string) $value);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    public static function cleanCell($value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value, " \t\n\r\0\x0B\"");
    }

    public static function cleanId($value): string
    {
        $v = self::cleanCell($value);
        if ($v === '') {
            return '';
        }
        if (is_numeric($v) && preg_match('/[eE]/', $v)) {
            return number_format((float) $v, 0, '.', '');
        }

        return $v;
    }

    /**
     * Seller Center dates look like "Sep 3, 2026, 2:04 am IST(UTC+5)".
     * Parse as IST, then store in the app timezone (Pacific) so L30 windows match Temu 1/2.
     */
    public static function parsePurchaseDate($dateString): ?Carbon
    {
        if ($dateString === null || trim((string) $dateString) === '') {
            return null;
        }

        $raw = trim((string) $dateString);
        $tz = null;

        if (preg_match('/\s+IST\(UTC[+-]\d+\)\s*$/i', $raw)) {
            $tz = 'Asia/Kolkata';
            $raw = trim((string) preg_replace('/\s+IST\(UTC[+-]\d+\)\s*$/i', '', $raw));
        } elseif (preg_match('/\s+UTC[+-]\d+\s*$/i', $raw)) {
            $raw = trim((string) preg_replace('/\s+UTC[+-]\d+\s*$/i', '', $raw));
        }

        if (is_numeric($raw)) {
            $excelEpoch = Carbon::create(1900, 1, 1)->subDays(2);

            return $excelEpoch->copy()->addDays((float) $raw);
        }

        $formats = [
            'M j, Y, g:i a',
            'M d, Y, g:i a',
            'M j, Y g:i a',
            'Y-m-d H:i:s',
            'Y-m-d',
            'm/d/Y H:i:s',
            'm/d/Y',
            'd/m/Y H:i:s',
            'd/m/Y',
            'M d, Y H:i:s',
            'M d, Y',
        ];

        $appTz = 'America/Los_Angeles';
        try {
            if (function_exists('app') && app()->bound('config')) {
                $configured = config('app.timezone');
                if (is_string($configured) && $configured !== '') {
                    $appTz = $configured;
                }
            }
        } catch (\Throwable $e) {
            // Unit tests without a booted container keep Pacific.
        }

        foreach ($formats as $format) {
            try {
                $date = $tz
                    ? Carbon::createFromFormat($format, $raw, $tz)
                    : Carbon::createFromFormat($format, $raw);
                if ($date !== false && ! $date->hasErrors()) {
                    return $date->setTimezone($appTz);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            $parsed = $tz ? Carbon::parse($raw, $tz) : Carbon::parse($raw);

            return $parsed->setTimezone($appTz);
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function isCanceledStatus(?string $status): bool
    {
        $u = strtoupper(trim((string) $status));

        return in_array($u, ['CANCELED', 'CANCELLED'], true);
    }

    public static function shouldExcludeFromSales(array $row): bool
    {
        if (self::isCanceledStatus($row['order_status'] ?? null)
            || self::isCanceledStatus($row['order_item_status'] ?? null)) {
            return true;
        }

        $qty = (int) ($row['quantity_purchased'] ?? 0);
        $canceled = (int) ($row['quantity_canceled'] ?? 0);
        if ($qty <= 0) {
            return true;
        }

        return $canceled > 0 && $canceled >= $qty;
    }

    /**
     * @param  array<string, mixed>  $data  Combined row keyed by normalizeHeader()
     * @return array<string, mixed>|null
     */
    public static function mapInsertRow(array $data): ?array
    {
        $orderId = self::cleanCell($data['order_id'] ?? '');
        if ($orderId === '') {
            return null;
        }

        $purchaseDate = isset($data['purchase_date'])
            ? self::parsePurchaseDate($data['purchase_date'])
            : null;

        return [
            'order_id' => $orderId,
            'order_status' => self::cleanCell($data['order_status'] ?? '') ?: null,
            'fulfillment_mode' => self::cleanCell($data['fulfillment_mode'] ?? '') ?: null,
            'order_item_id' => self::cleanId($data['order_item_id'] ?? '') ?: null,
            'order_item_status' => self::cleanCell($data['order_item_status'] ?? '') ?: null,
            'product_name_by_customer_order' => self::cleanCell($data['product_name_by_customer_order'] ?? '') ?: null,
            'product_name' => self::cleanCell($data['product_name'] ?? '') ?: null,
            'variation' => self::cleanCell($data['variation'] ?? '') ?: null,
            'contribution_sku' => self::cleanCell($data['contribution_sku'] ?? '') ?: null,
            'sku_id' => self::cleanId($data['sku_id'] ?? '') ?: null,
            'quantity_purchased' => isset($data['quantity_purchased']) && $data['quantity_purchased'] !== '' && $data['quantity_purchased'] !== null
                ? (int) $data['quantity_purchased']
                : 0,
            'quantity_to_ship' => isset($data['quantity_to_ship']) && $data['quantity_to_ship'] !== '' && $data['quantity_to_ship'] !== null
                ? (int) $data['quantity_to_ship']
                : 0,
            'quantity_shipped' => isset($data['quantity_shipped']) && $data['quantity_shipped'] !== '' && $data['quantity_shipped'] !== null
                ? (int) $data['quantity_shipped']
                : 0,
            'quantity_canceled' => isset($data['quantity_canceled']) && $data['quantity_canceled'] !== '' && $data['quantity_canceled'] !== null
                ? (int) $data['quantity_canceled']
                : 0,
            'purchase_date' => $purchaseDate?->format('Y-m-d H:i:s'),
            'latest_shipping_time' => isset($data['latest_shipping_time'])
                ? self::parsePurchaseDate($data['latest_shipping_time'])?->format('Y-m-d H:i:s')
                : null,
            'latest_delivery_time' => isset($data['latest_delivery_time'])
                ? self::parsePurchaseDate($data['latest_delivery_time'])?->format('Y-m-d H:i:s')
                : null,
            'activity_goods_base_price' => self::sanitizePrice($data['activity_goods_base_price'] ?? null),
            'base_price_total' => self::sanitizePrice($data['base_price_total'] ?? null),
            'tracking_number' => self::cleanCell($data['tracking_number'] ?? '') ?: null,
            'carrier' => self::cleanCell($data['carrier'] ?? '') ?: null,
            'order_settlement_status' => self::cleanCell($data['order_settlement_status'] ?? '') ?: null,
        ];
    }
}
