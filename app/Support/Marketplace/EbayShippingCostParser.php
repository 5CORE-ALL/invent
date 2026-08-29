<?php

namespace App\Support\Marketplace;

/**
 * Parse eBay / SerpApi shipping payloads into a cost + whether that cost is confirmed.
 * Missing shipping must not be treated as FREE — callers keep the stored amount when unknown.
 */
class EbayShippingCostParser
{
    /**
     * @return array{cost: float, known: bool}
     */
    public static function fromProduct(array $product): array
    {
        $shipping = $product['shipping'] ?? null;
        if (is_string($shipping)) {
            return self::fromText($shipping);
        }
        if (is_array($shipping)) {
            $parsed = self::fromShippingObject($shipping);
            if ($parsed['known']) {
                return $parsed;
            }
        }

        return ['cost' => 0.0, 'known' => false];
    }

    /**
     * @return array{cost: float, known: bool}
     */
    public static function fromShippingObject(array $shipping): array
    {
        $options = $shipping['options'] ?? $shipping['shipping_options'] ?? [];
        $bestPaid = null;
        $sawFree = false;

        if (is_array($options)) {
            foreach ($options as $option) {
                if (! is_array($option)) {
                    continue;
                }
                $parsed = self::fromOption($option);
                if (! $parsed['known']) {
                    continue;
                }
                if ($parsed['cost'] > 0) {
                    if ($bestPaid === null || $parsed['cost'] < $bestPaid) {
                        $bestPaid = $parsed['cost'];
                    }
                } else {
                    $sawFree = true;
                }
            }
        }

        if ($bestPaid !== null) {
            return ['cost' => $bestPaid, 'known' => true];
        }
        if ($sawFree) {
            return ['cost' => 0.0, 'known' => true];
        }

        foreach (['cost', 'price', 'shipping_cost', 'shippingCost'] as $key) {
            if (! isset($shipping[$key])) {
                continue;
            }
            $parsed = self::fromMoney($shipping[$key]);
            if ($parsed['known']) {
                return $parsed;
            }
        }

        if (isset($shipping['value']) || isset($shipping['extracted']) || isset($shipping['raw'])) {
            return self::fromMoney($shipping);
        }

        return ['cost' => 0.0, 'known' => false];
    }

    /**
     * @return array{cost: float, known: bool}
     */
    public static function fromOption(array $option): array
    {
        $type = (string) ($option['shippingCostType'] ?? $option['type'] ?? '');
        if (strcasecmp($type, 'FREE') === 0) {
            return ['cost' => 0.0, 'known' => true];
        }

        foreach (['shippingCost', 'shipping_cost', 'price', 'cost'] as $key) {
            if (! isset($option[$key])) {
                continue;
            }
            $parsed = self::fromMoney($option[$key]);
            if ($parsed['known']) {
                return $parsed;
            }
        }

        if (! empty($option['free'])) {
            return ['cost' => 0.0, 'known' => true];
        }

        foreach (['price', 'cost', 'via'] as $key) {
            $raw = $option[$key] ?? null;
            if (is_string($raw)) {
                $parsed = self::fromText($raw);
                if ($parsed['known']) {
                    return $parsed;
                }
            }
        }

        return ['cost' => 0.0, 'known' => false];
    }

    /**
     * @return array{cost: float, known: bool}
     */
    public static function fromMoney(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['cost' => 0.0, 'known' => false];
        }
        if (is_int($value) || is_float($value)) {
            return ['cost' => round((float) $value, 2), 'known' => true];
        }
        if (is_string($value)) {
            return self::fromText($value);
        }
        if (! is_array($value)) {
            return ['cost' => 0.0, 'known' => false];
        }

        foreach (['value', 'amount', 'extracted'] as $key) {
            if (! array_key_exists($key, $value)) {
                continue;
            }
            $inner = $value[$key];
            if (is_numeric($inner)) {
                return ['cost' => round((float) $inner, 2), 'known' => true];
            }
            if (is_string($inner)) {
                $parsed = self::fromText($inner);
                if ($parsed['known']) {
                    return $parsed;
                }
            }
        }

        if (isset($value['raw']) && is_string($value['raw'])) {
            return self::fromText($value['raw']);
        }

        return ['cost' => 0.0, 'known' => false];
    }

    /**
     * @return array{cost: float, known: bool}
     */
    public static function fromText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['cost' => 0.0, 'known' => false];
        }

        // "Free returns" is not shipping. Do not treat it as FREE shipping.
        if (preg_match('/free\s+returns?\b/i', $text) && ! preg_match('/shipping|delivery/i', $text)) {
            return ['cost' => 0.0, 'known' => false];
        }

        if (preg_match('/\bfree\b/i', $text) && ! preg_match('/\d/', $text)) {
            return ['cost' => 0.0, 'known' => true];
        }

        if (preg_match('/([\d,.]+)/', $text, $matches)) {
            return ['cost' => round((float) str_replace(',', '', $matches[1]), 2), 'known' => true];
        }

        return ['cost' => 0.0, 'known' => false];
    }

    /**
     * @return array{cost: float, known: bool}
     */
    public static function fromHtml(string $html): array
    {
        $paidPatterns = [
            '/"shippingCost"\s*:\s*\{\s*"value"\s*:\s*"?(?P<v>[\d.]+)"?/',
            '/"convertedShippingCost"\s*:\s*\{\s*"value"\s*:\s*"?(?P<v>[\d.]+)"?/',
            '/"logisticsCost"\s*:\s*\{\s*"value"\s*:\s*"?(?P<v>[\d.]+)"?/',
            '/US\s*\$\s*(?P<v>[\d,.]+)\s*eBay International Shipping/i',
            '/eBay International Shipping[^$]{0,80}\$\s*(?P<v>[\d,.]+)/i',
        ];

        foreach ($paidPatterns as $pattern) {
            if (! preg_match($pattern, $html, $matches)) {
                continue;
            }
            $amount = round((float) str_replace(',', '', $matches['v']), 2);
            if ($amount > 0) {
                return ['cost' => $amount, 'known' => true];
            }
        }

        if (preg_match('/"shippingCostType"\s*:\s*"FREE"/i', $html)
            || preg_match('/"shippingCost"\s*:\s*\{\s*"value"\s*:\s*"?0(?:\.0+)?"?/', $html)
        ) {
            return ['cost' => 0.0, 'known' => true];
        }

        return ['cost' => 0.0, 'known' => false];
    }

    /**
     * Keep a previously stored paid shipping when the live fetch could not confirm cost.
     *
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    public static function preferExisting(array $live, mixed $existingShipping): array
    {
        $known = (bool) ($live['shipping_known'] ?? false);
        $shipping = round((float) ($live['shipping_cost'] ?? 0), 2);
        $existing = is_numeric($existingShipping) ? round((float) $existingShipping, 2) : null;

        if (! $known && $shipping <= 0 && $existing !== null && $existing > 0) {
            $shipping = $existing;
        }

        $live['shipping_cost'] = $shipping;
        $live['total_price'] = round((float) ($live['price'] ?? 0) + $shipping, 2);

        return $live;
    }
}
