<?php

namespace App\Support\Marketplace;

/**
 * Match a competitor eBay variation (Pack: 2PCS / 4PCS / …) to our SKU.
 *
 * The listing-level price is the unselected default (e.g. $39.98). The real
 * LMP price is that variation's Buy It Now (e.g. $63.99 for 4PCS), not the
 * "$16.75/ea" text sellers put in the dropdown label.
 */
class EbayCompetitorVariationMatcher
{
    /**
     * First pack/piece/pair quantity in a SKU or variation label.
     */
    public static function extractPackQty(?string $text): ?int
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/\bpack(?:s)?\s+of\s+(\d+)\b/i', $text, $match)) {
            $qty = (int) $match[1];

            return $qty > 0 ? $qty : null;
        }

        if (preg_match('/(?<!\d)(\d+)\s*[-]?\s*(?:PCS?|PIECES?|PACKS?|PK|PAIRS?|SETS?)\b/i', $text, $match)) {
            $qty = (int) $match[1];

            return $qty > 0 ? $qty : null;
        }

        return null;
    }

    /**
     * Short label for display / title suffix, e.g. "4PCS".
     */
    public static function shortLabel(array $variation): string
    {
        $qty = self::extractPackQty((string) ($variation['label'] ?? ''));
        if ($qty) {
            return $qty.'PCS';
        }

        $label = trim((string) ($variation['label'] ?? ''));
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\s*[-–—]?\s*\([^)]*\$[^)]*\)\s*$/', '', $label);

        return trim((string) $label);
    }

    /**
     * @param  list<array{id?: string, item_id?: string, label?: string, price?: float, shipping_cost?: float, title?: ?string, image?: ?string, link?: ?string}>  $variations
     * @return array<string, mixed>|null
     */
    public static function pick(array $variations, ?string $sku, ?string $variationId = null): ?array
    {
        if ($variations === []) {
            return null;
        }

        $variationId = trim((string) $variationId);
        if ($variationId !== '') {
            foreach ($variations as $variation) {
                if (self::matchesVariationId($variation, $variationId)) {
                    return $variation;
                }
            }
        }

        $packQty = self::extractPackQty($sku);
        if ($packQty === null) {
            return null;
        }

        $matches = [];
        foreach ($variations as $variation) {
            $labelQty = self::extractPackQty((string) ($variation['label'] ?? ''));
            if ($labelQty === $packQty && (float) ($variation['price'] ?? 0) > 0) {
                $matches[] = $variation;
            }
        }

        if ($matches === []) {
            return null;
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        $tokens = self::skuTokens($sku);
        if ($tokens === []) {
            return $matches[0];
        }

        $best = $matches[0];
        $bestScore = -1;
        foreach ($matches as $variation) {
            $haystack = strtoupper((string) ($variation['label'] ?? ''));
            $score = 0;
            foreach ($tokens as $token) {
                if (str_contains($haystack, $token)) {
                    $score += 10;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $variation;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $variation
     */
    public static function matchesVariationId(array $variation, string $variationId): bool
    {
        $variationId = trim($variationId);
        if ($variationId === '') {
            return false;
        }

        foreach (['id', 'item_id'] as $key) {
            $value = trim((string) ($variation[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            if ($value === $variationId) {
                return true;
            }
            if (preg_match('/\|'.preg_quote($variationId, '/').'$/', $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extra SKU tokens used to break ties (color / DBL / RED), ignoring pack words.
     *
     * @return list<string>
     */
    public static function skuTokens(?string $sku): array
    {
        $parts = preg_split('/[\s\-_]+/', strtoupper(trim((string) $sku))) ?: [];
        $skip = [
            'PCS', 'PC', 'PIECE', 'PIECES', 'PACK', 'PACKS', 'PK',
            'PAIR', 'PAIRS', 'SET', 'SETS', 'OF', 'THE',
        ];

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if (strlen($part) < 3 || in_array($part, $skip, true) || preg_match('/^\d+$/', $part)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }
}
