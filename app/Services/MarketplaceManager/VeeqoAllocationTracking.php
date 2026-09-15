<?php

namespace App\Services\MarketplaceManager;

/**
 * Pick the Veeqo allocation/shipment that belongs to a SKU.
 * One order can have many labels — never return the first tracking for every SKU.
 */
final class VeeqoAllocationTracking
{
    /**
     * @param  list<string>  $excludeTrackings
     * @return array{tracking: string, carrier_hint: string, shipment: array<string, mixed>, bucket: array<string, mixed>}|null
     */
    public static function pick(array $order, string $sku = '', array $excludeTrackings = []): ?array
    {
        $matcher = new ShopifyFulfillmentTrackingMatcher;
        $want = $matcher->normalizeSku($sku);
        $exclude = [];
        foreach ($excludeTrackings as $tn) {
            $key = self::normalizeTracking((string) $tn);
            if ($key !== '') {
                $exclude[$key] = true;
            }
        }

        $openHit = null;
        $anyBucketHasSku = false;
        foreach (self::buckets($order) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $shipment = is_array($row['shipment'] ?? null) ? $row['shipment'] : $row;
            $tracking = self::trackingNumberFrom($shipment) ?? self::trackingNumberFrom($row);
            if ($tracking === null) {
                continue;
            }
            if (isset($exclude[$tracking])) {
                continue;
            }

            $hasSkuFields = self::payloadHasSkuFields($row) || self::payloadHasSkuFields($shipment);
            if ($hasSkuFields) {
                $anyBucketHasSku = true;
            }
            $matches = $want !== '' && (
                self::payloadContainsSku($row, $want) || self::payloadContainsSku($shipment, $want)
            );
            if ($want !== '' && $hasSkuFields && ! $matches) {
                continue;
            }

            $hit = [
                'tracking' => $tracking,
                'carrier_hint' => self::carrierHint($shipment, $row),
                'shipment' => $shipment,
                'bucket' => $row,
            ];
            if ($matches) {
                return $hit;
            }
            $openHit ??= $hit;
        }

        if ($want === '') {
            return $openHit;
        }
        if ($openHit !== null && ! $anyBucketHasSku) {
            return $openHit;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function buckets(array $order): array
    {
        $buckets = [];
        if (isset($order['allocations']) && is_array($order['allocations'])) {
            $buckets = array_merge($buckets, $order['allocations']);
        }
        if (isset($order['shipments']) && is_array($order['shipments'])) {
            foreach ($order['shipments'] as $shipment) {
                $buckets[] = ['shipment' => $shipment];
            }
        }

        return $buckets;
    }

    public static function normalizeTracking(string $raw): string
    {
        return strtoupper(preg_replace('/\s+/', '', $raw) ?? $raw);
    }

    public static function trackingNumberFrom(array $row): ?string
    {
        $candidates = [
            $row['tracking_number'] ?? null,
            $row['trackingNumber'] ?? null,
            $row['tracking'] ?? null,
            $row['shipment_tracking_number'] ?? null,
            $row['mail_tracking_number'] ?? null,
        ];
        foreach ($candidates as $raw) {
            if (is_array($raw)) {
                $raw = $raw['tracking_number'] ?? $raw['number'] ?? $raw['value'] ?? null;
            }
            $tn = self::normalizeTracking((string) $raw);
            if ($tn !== '') {
                return $tn;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function payloadHasSkuFields(array $payload): bool
    {
        $found = false;
        $walk = static function ($node) use (&$walk, &$found): void {
            if ($found || ! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    $walk($v);

                    continue;
                }
                $key = strtolower((string) $k);
                if (
                    in_array($key, ['sku', 'seller_sku', 'seller_part_number', 'sellernumber', 'part_number'], true)
                    || str_ends_with($key, '_sku')
                ) {
                    if (trim((string) $v) !== '') {
                        $found = true;

                        return;
                    }
                }
            }
        };
        $walk($payload);

        return $found;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function payloadContainsSku(array $payload, string $want): bool
    {
        $matcher = new ShopifyFulfillmentTrackingMatcher;
        $found = false;
        $walk = static function ($node) use (&$walk, &$found, $matcher, $want): void {
            if ($found || ! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    $walk($v);

                    continue;
                }
                $key = strtolower((string) $k);
                if (
                    in_array($key, ['sku', 'seller_sku', 'seller_part_number', 'sellernumber', 'part_number'], true)
                    || str_ends_with($key, '_sku')
                ) {
                    if ($matcher->skusEqual((string) $v, $want)) {
                        $found = true;

                        return;
                    }
                }
            }
        };
        $walk($payload);

        return $found;
    }

    /**
     * @param  array<string, mixed>  $shipment
     * @param  array<string, mixed>  $parent
     */
    public static function carrierHint(array $shipment, array $parent): string
    {
        foreach ([
            $shipment['carrier']['name'] ?? null,
            $shipment['carrier_name'] ?? null,
            $shipment['service_carrier'] ?? null,
            $parent['carrier']['name'] ?? null,
        ] as $c) {
            $c = trim((string) $c);
            if ($c !== '') {
                return $c;
            }
        }

        return '';
    }
}
