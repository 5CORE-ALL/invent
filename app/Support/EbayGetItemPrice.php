<?php

namespace App\Support;

/**
 * Live listing price from Trading GetItem / GetSellerList payloads.
 * CurrentPrice is what seller hub shows; StartPrice can stay at an older Dil stamp.
 */
class EbayGetItemPrice
{
    /**
     * @param  array<string, mixed>  $payload  Full API array or the Item node
     */
    public static function fromGetItemPayload(array $payload, string $sku = ''): ?float
    {
        $item = is_array($payload['Item'] ?? null) ? $payload['Item'] : $payload;

        return self::fromItem($item, $sku);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromItem(array $item, string $sku = ''): ?float
    {
        $sku = trim($sku);
        $vars = $item['Variations']['Variation'] ?? null;
        if ($vars !== null && $sku !== '') {
            $list = (is_array($vars) && (isset($vars['SKU']) || isset($vars['StartPrice']) || isset($vars['SellingStatus'])))
                ? [$vars]
                : (is_array($vars) ? $vars : []);
            foreach ($list as $variation) {
                if (! is_array($variation)) {
                    continue;
                }
                if (strcasecmp(trim((string) ($variation['SKU'] ?? '')), $sku) !== 0) {
                    continue;
                }
                $price = self::parseMoney($variation['SellingStatus']['CurrentPrice'] ?? null)
                    ?? self::parseMoney($variation['StartPrice'] ?? null);
                if ($price !== null) {
                    return $price;
                }
            }
        }

        return self::parseMoney($item['SellingStatus']['CurrentPrice'] ?? null)
            ?? self::parseMoney($item['StartPrice'] ?? null)
            ?? self::parseMoney($item['BuyItNowPrice'] ?? null);
    }

    public static function parseMoney(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            $n = round((float) $raw, 2);

            return $n > 0 ? $n : null;
        }
        if (is_array($raw)) {
            foreach (['@content', '#text', '_', '#', 'value', 'Value'] as $key) {
                if (isset($raw[$key]) && is_numeric($raw[$key])) {
                    $n = round((float) $raw[$key], 2);

                    return $n > 0 ? $n : null;
                }
            }
            if (isset($raw[0]) && is_numeric($raw[0])) {
                $n = round((float) $raw[0], 2);

                return $n > 0 ? $n : null;
            }
            foreach ($raw as $key => $val) {
                if ($key === '@attributes') {
                    continue;
                }
                if (is_numeric($val)) {
                    $n = round((float) $val, 2);

                    return $n > 0 ? $n : null;
                }
            }
        }
        if (is_string($raw) && preg_match('/[\d.]+/', $raw, $m)) {
            $n = round((float) $m[0], 2);

            return $n > 0 ? $n : null;
        }

        return null;
    }
}
