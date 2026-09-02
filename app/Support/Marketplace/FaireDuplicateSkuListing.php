<?php

namespace App\Support\Marketplace;

/**
 * One SKU can exist on more than one Faire product (live + stale duplicate).
 * Last-page updateOrCreate was keeping the dead listing's wholesale price.
 */
final class FaireDuplicateSkuListing
{
    /**
     * @param  array{product_id?: string, qty?: int|null, price?: float|null, lifecycle?: string}  $incoming
     * @param  array{product_id?: string, qty?: int|null, price?: float|null, lifecycle?: string}|null  $currentBest
     */
    public static function preferIncoming(array $incoming, ?array $currentBest): bool
    {
        if ($currentBest === null) {
            return true;
        }

        $inId = trim((string) ($incoming['product_id'] ?? ''));
        $curId = trim((string) ($currentBest['product_id'] ?? ''));
        if ($inId !== '' && $inId === $curId) {
            return true;
        }

        return self::score($incoming) > self::score($currentBest);
    }

    /**
     * In-stock listings outrank published-but-empty duplicates.
     *
     * @param  array{product_id?: string, qty?: int|null, price?: float|null, lifecycle?: string}  $listing
     */
    public static function score(array $listing): int
    {
        $qty = max(0, (int) ($listing['qty'] ?? 0));
        $price = (float) ($listing['price'] ?? 0);
        $lifecycle = strtolower(str_replace([' ', '-'], '_', trim((string) ($listing['lifecycle'] ?? ''))));
        $published = in_array($lifecycle, ['published', 'live', 'active', 'for_sale'], true);

        $score = 0;
        if ($qty > 0) {
            $score += 1_000_000 + $qty;
        }
        if ($published) {
            $score += 10_000;
        }
        if ($price > 0) {
            $score += 1_000;
        }

        return $score;
    }

    /**
     * Faire v2 may omit top-level wholesale_price and only send prices[].
     *
     * @param  array<string, mixed>|null  $variant
     */
    public static function wholesaleMinor(?array $variant): ?int
    {
        if (! is_array($variant)) {
            return null;
        }

        $direct = data_get($variant, 'wholesale_price.amount_minor')
            ?? data_get($variant, 'wholesale_price_cents');
        if (is_numeric($direct) && (float) $direct > 0) {
            return (int) round((float) $direct);
        }

        $usa = null;
        $any = null;
        $prices = $variant['prices'] ?? null;
        if (is_array($prices)) {
            foreach ($prices as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $minor = data_get($row, 'wholesale_price.amount_minor')
                    ?? data_get($row, 'wholesale_price_cents');
                if (! is_numeric($minor) || (float) $minor <= 0) {
                    continue;
                }
                $minor = (int) round((float) $minor);
                $country = strtoupper(trim((string) data_get($row, 'geo_constraint.country', '')));
                if (in_array($country, ['USA', 'US'], true)) {
                    $usa = $minor;
                    break;
                }
                $any ??= $minor;
            }
        }

        $picked = $usa ?? $any;
        if ($picked !== null) {
            return $picked;
        }

        return is_numeric($direct) ? (int) round((float) $direct) : null;
    }
}
