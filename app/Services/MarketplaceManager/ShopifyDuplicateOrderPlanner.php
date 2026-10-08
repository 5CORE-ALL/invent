<?php

namespace App\Services\MarketplaceManager;

/**
 * Decides which Shopify copies of one marketplace order are duplicates to cancel.
 *
 * A copy is only cancelled when it is unfulfilled and every copy carries the same line
 * items; anything else (two fulfilled copies, different items, copies days apart, a large
 * group from a generic tag) is left for manual review.
 */
final class ShopifyDuplicateOrderPlanner
{
    /** Order-ref tags written by the Marketplace Manager push services, e.g. "amazon-113-7876038-6872205". */
    public const TAG_PREFIXES = [
        'aliexpress-', 'alibaba-', 'amazon-', 'doba-', 'ebay1-', 'ebay2-', 'ebay3-', 'faire-',
        'newegg-', 'reverb-', 'shein-', 'temu-', 'temu2-', 'temu3-', 'tiktok-', 'tiktok2-',
        'topdawg-', 'wayfair-',
    ];

    public const B5C_B2B_TAG = 'business 5 core (b2b)';

    public const MAX_GROUP_SIZE = 3;

    public const MAX_SPREAD_HOURS = 48;

    /**
     * Marketplace order keys for a Shopify order, e.g. ["amazon-113-7876038-6872205"].
     *
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    public static function groupKeys(array $order): array
    {
        $keys = [];
        $tags = array_map(
            fn ($t) => strtolower(trim((string) $t)),
            preg_split('/\s*,\s*/', (string) ($order['tags'] ?? '')) ?: []
        );

        foreach ($tags as $tag) {
            foreach (self::TAG_PREFIXES as $prefix) {
                if (! str_starts_with($tag, $prefix)) {
                    continue;
                }
                $ref = substr($tag, strlen($prefix));
                if (self::looksLikeOrderRef($ref)) {
                    $keys[$prefix.$ref] = true;
                }
            }
        }

        if (in_array(self::B5C_B2B_TAG, $tags, true)) {
            $ref = strtolower(trim((string) ($order['source_identifier'] ?? '')));
            if (self::looksLikeOrderRef($ref)) {
                $keys['b5cb2b:'.$ref] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Marketplace slug + order ref from the order-ref tags, ref in its original case,
     * e.g. [['slug' => 'temu', 'ref' => 'PO-211-123']].
     *
     * @param  array<string, mixed>  $order
     * @return list<array{slug: string, ref: string}>
     */
    public static function orderRefs(array $order): array
    {
        $prefixes = self::TAG_PREFIXES;
        usort($prefixes, fn ($a, $b) => strlen($b) <=> strlen($a));

        $out = [];
        foreach (preg_split('/\s*,\s*/', (string) ($order['tags'] ?? '')) ?: [] as $tag) {
            $tag = trim((string) $tag);
            foreach ($prefixes as $prefix) {
                if (stripos($tag, $prefix) !== 0) {
                    continue;
                }
                $ref = substr($tag, strlen($prefix));
                if (self::looksLikeOrderRef(strtolower($ref))) {
                    $out[strtolower($prefix.$ref)] = ['slug' => rtrim($prefix, '-'), 'ref' => $ref];
                }
                break;
            }
        }

        return array_values($out);
    }

    /**
     * @param  list<array<string, mixed>>  $orders  Non-cancelled Shopify orders sharing one key.
     * @param  array<string, true>  $referencedIds  Shopify order ids stored on local marketplace rows.
     * @return array{keeper: ?string, cancel: list<string>, review: ?string}
     */
    public static function plan(array $orders, array $referencedIds = []): array
    {
        $byId = [];
        foreach ($orders as $order) {
            $id = (string) ($order['id'] ?? '');
            if ($id !== '' && empty($order['cancelled_at'])) {
                $byId[$id] = $order;
            }
        }
        if (count($byId) < 2) {
            return ['keeper' => null, 'cancel' => [], 'review' => null];
        }
        if (count($byId) > self::MAX_GROUP_SIZE) {
            return ['keeper' => null, 'cancel' => [], 'review' => count($byId).' orders share this tag'];
        }

        $signatures = array_unique(array_map([self::class, 'lineItemSignature'], $byId));
        if (count($signatures) > 1) {
            return ['keeper' => null, 'cancel' => [], 'review' => 'copies have different line items'];
        }

        $times = array_filter(array_map(fn ($o) => strtotime((string) ($o['created_at'] ?? '')) ?: null, $byId));
        if (count($times) === count($byId) && (max($times) - min($times)) > self::MAX_SPREAD_HOURS * 3600) {
            return ['keeper' => null, 'cancel' => [], 'review' => 'copies created more than '.self::MAX_SPREAD_HOURS.'h apart'];
        }

        $touched = array_keys(array_filter($byId, fn ($o) => self::isTouched($o)));
        if (count($touched) > 1) {
            return ['keeper' => null, 'cancel' => [], 'review' => 'more than one copy is fulfilled'];
        }

        if (count($touched) === 1) {
            $keeper = (string) $touched[0];
        } else {
            $ids = array_keys($byId);
            usort($ids, function ($a, $b) use ($byId, $referencedIds) {
                [$a, $b] = [(string) $a, (string) $b];
                $ra = isset($referencedIds[$a]) ? 0 : 1;
                $rb = isset($referencedIds[$b]) ? 0 : 1;
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }
                $ta = strtotime((string) ($byId[$a]['created_at'] ?? '')) ?: PHP_INT_MAX;
                $tb = strtotime((string) ($byId[$b]['created_at'] ?? '')) ?: PHP_INT_MAX;

                return $ta === $tb ? strcmp($a, $b) : $ta <=> $tb;
            });
            $keeper = (string) $ids[0];
        }

        $cancel = array_values(array_map('strval', array_filter(array_keys($byId), fn ($id) => (string) $id !== $keeper)));

        return ['keeper' => $keeper, 'cancel' => $cancel, 'review' => null];
    }

    /**
     * Fulfilled, partly fulfilled or otherwise worked on — never cancelled automatically.
     *
     * @param  array<string, mixed>  $order
     */
    public static function isTouched(array $order): bool
    {
        $status = strtolower(trim((string) ($order['fulfillment_status'] ?? '')));

        return $status !== '' && $status !== 'unfulfilled' && $status !== 'null';
    }

    /**
     * SKU (or title) and quantity per line, ignoring variant ids — the slim retries drop them.
     *
     * @param  array<string, mixed>  $order
     */
    public static function lineItemSignature(array $order): string
    {
        $lines = [];
        foreach ((array) ($order['line_items'] ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = strtolower(trim((string) ($line['sku'] ?? '')));
            if ($sku === '') {
                $sku = 'title:'.strtolower(trim((string) ($line['title'] ?? '')));
            }
            $lines[$sku] = ($lines[$sku] ?? 0) + (int) ($line['quantity'] ?? 0);
        }
        ksort($lines);

        $parts = [];
        foreach ($lines as $sku => $qty) {
            $parts[] = $sku.'x'.$qty;
        }

        return implode('|', $parts);
    }

    private static function looksLikeOrderRef(string $ref): bool
    {
        return strlen($ref) >= 4 && preg_match('/\d/', $ref) === 1 && ! str_contains($ref, ' ');
    }
}
