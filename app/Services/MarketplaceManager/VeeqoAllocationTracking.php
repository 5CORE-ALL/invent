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
            $tracking = null;
            foreach (array_merge(self::trackingNumbersFrom($shipment), self::trackingNumbersFrom($row)) as $tn) {
                if (! isset($exclude[$tn])) {
                    $tracking = $tn;
                    break;
                }
            }
            if ($tracking === null) {
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
        foreach (['allocations', 'allocation'] as $key) {
            $raw = $order[$key] ?? null;
            if (is_array($raw)) {
                $buckets = array_merge($buckets, array_is_list($raw) ? $raw : [$raw]);
            }
        }
        foreach (['shipments', 'parcels'] as $key) {
            $raw = $order[$key] ?? null;
            if (! is_array($raw)) {
                continue;
            }
            foreach (array_is_list($raw) ? $raw : [$raw] as $shipment) {
                if (is_array($shipment)) {
                    $buckets[] = ['shipment' => $shipment];
                }
            }
        }

        $seen = [];
        foreach ($buckets as $row) {
            if (! is_array($row)) {
                continue;
            }
            $tn = self::trackingNumberFrom(is_array($row['shipment'] ?? null) ? $row['shipment'] : $row)
                ?? self::trackingNumberFrom($row);
            if ($tn !== null) {
                $seen[$tn] = true;
            }
        }
        $walk = static function ($node) use (&$walk, &$buckets, &$seen): void {
            if (! is_array($node)) {
                return;
            }
            $tn = self::trackingNumberFrom($node);
            if ($tn !== null && ! isset($seen[$tn])) {
                $seen[$tn] = true;
                $buckets[] = ['shipment' => $node];
            }
            foreach ($node as $k => $v) {
                if (is_array($v) && ! in_array((string) $k, ['customer', 'billing_address', 'shipping_address'], true)) {
                    $walk($v);
                }
            }
        };
        $walk($order);

        return $buckets;
    }

    public static function normalizeTracking(string $raw): string
    {
        return strtoupper(preg_replace('/\s+/', '', $raw) ?? $raw);
    }

    public static function trackingNumberFrom(array $row): ?string
    {
        $all = self::trackingNumbersFrom($row);

        return $all[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function trackingNumbersFrom(array $row): array
    {
        $out = [];
        $push = static function (mixed $raw) use (&$out): void {
            if (is_array($raw)) {
                $raw = $raw['tracking_number'] ?? $raw['number'] ?? $raw['value'] ?? null;
            }
            $tn = self::normalizeTracking((string) $raw);
            if ($tn !== '' && ! in_array($tn, $out, true)) {
                $out[] = $tn;
            }
        };
        foreach ([
            $row['tracking_number'] ?? null,
            $row['trackingNumber'] ?? null,
            $row['tracking'] ?? null,
            $row['shipment_tracking_number'] ?? null,
            $row['mail_tracking_number'] ?? null,
        ] as $raw) {
            $push($raw);
        }
        foreach (['tracking_numbers', 'trackingNumbers'] as $key) {
            $list = $row[$key] ?? null;
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $item) {
                $push($item);
            }
        }

        return $out;
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
                if (self::keyLooksLikeSku($key)) {
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
                if (self::keyLooksLikeSku($key)) {
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

    public static function keyLooksLikeSku(string $key): bool
    {
        $key = strtolower(trim($key));

        return in_array($key, [
            'sku',
            'sku_code',
            'skucode',
            'seller_sku',
            'seller_part_number',
            'sellernumber',
            'part_number',
            'product_sku',
        ], true) || str_ends_with($key, '_sku') || str_ends_with($key, 'sku_code');
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
