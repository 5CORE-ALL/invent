<?php

namespace App\Support\Marketplace;

/**
 * Match a competitor eBay variation to our SKU.
 *
 * Pack SKUs (MS DBL 4PCS): use that pack's Buy It Now, not the unselected default
 * and not the "$16.75/ea" dropdown label.
 *
 * Color SKUs (PS WHLS WH / PS WHLS BLK): match White / Black on the listing.
 * Short codes must be whole tokens so WH does not match WHLS.
 */
class EbayCompetitorVariationMatcher
{
    /** @var array<string, list<string>> */
    private const COLOR_ALIASES = [
        'WH' => ['WHITE', 'WHT', 'WHI', 'WH'],
        'WHT' => ['WHITE', 'WHT', 'WH'],
        'WHI' => ['WHITE', 'WHI', 'WH'],
        'WHITE' => ['WHITE', 'WHT', 'WHI', 'WH'],
        'BLK' => ['BLACK', 'BLK', 'BK'],
        'BLACK' => ['BLACK', 'BLK', 'BK'],
        'BK' => ['BLACK', 'BLK', 'BK'],
        'RED' => ['RED', 'RD'],
        'RD' => ['RED', 'RD'],
        'BLU' => ['BLUE', 'BLU'],
        'BLUE' => ['BLUE', 'BLU'],
        'GRN' => ['GREEN', 'GRN'],
        'GREEN' => ['GREEN', 'GRN'],
        'GRY' => ['GRAY', 'GREY', 'GRY'],
        'GRAY' => ['GRAY', 'GREY', 'GRY'],
        'GREY' => ['GRAY', 'GREY', 'GRY'],
        'SLV' => ['SILVER', 'SLV'],
        'SILVER' => ['SILVER', 'SLV'],
        'GLD' => ['GOLD', 'GLD'],
        'GOLD' => ['GOLD', 'GLD'],
        'PNK' => ['PINK', 'PNK'],
        'PINK' => ['PINK', 'PNK'],
        'YLW' => ['YELLOW', 'YLW'],
        'YELLOW' => ['YELLOW', 'YLW'],
        'ORG' => ['ORANGE', 'ORG'],
        'ORANGE' => ['ORANGE', 'ORG'],
        'PRP' => ['PURPLE', 'PRP'],
        'PURPLE' => ['PURPLE', 'PRP'],
        'BRN' => ['BROWN', 'BRN'],
        'BROWN' => ['BROWN', 'BRN'],
        'BGE' => ['BEIGE', 'BGE'],
        'BEIGE' => ['BEIGE', 'BGE'],
    ];

    /** @var array<string, string> */
    private const COLOR_CANONICAL = [
        'WH' => 'WHITE', 'WHT' => 'WHITE', 'WHI' => 'WHITE', 'WHITE' => 'WHITE',
        'BLK' => 'BLACK', 'BK' => 'BLACK', 'BLACK' => 'BLACK',
        'RED' => 'RED', 'RD' => 'RED',
        'BLU' => 'BLUE', 'BLUE' => 'BLUE',
        'GRN' => 'GREEN', 'GREEN' => 'GREEN',
        'GRY' => 'GRAY', 'GRAY' => 'GRAY', 'GREY' => 'GRAY',
        'SLV' => 'SILVER', 'SILVER' => 'SILVER',
        'GLD' => 'GOLD', 'GOLD' => 'GOLD',
        'PNK' => 'PINK', 'PINK' => 'PINK',
        'YLW' => 'YELLOW', 'YELLOW' => 'YELLOW',
        'ORG' => 'ORANGE', 'ORANGE' => 'ORANGE',
        'PRP' => 'PURPLE', 'PURPLE' => 'PURPLE',
        'BRN' => 'BROWN', 'BROWN' => 'BROWN',
        'BGE' => 'BEIGE', 'BEIGE' => 'BEIGE',
    ];

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
     * Color words to look for on the listing (WHITE, BLK, …).
     *
     * @return list<string>
     */
    public static function colorNeedles(?string $sku): array
    {
        $needles = [];
        foreach (self::skuParts($sku) as $part) {
            foreach (self::COLOR_ALIASES[$part] ?? [] as $alias) {
                $needles[] = $alias;
            }
        }

        return array_values(array_unique($needles));
    }

    public static function wantsVariationMatch(?string $sku): bool
    {
        return self::extractPackQty($sku) !== null || self::colorNeedles($sku) !== [];
    }

    public static function isColorToken(?string $token): bool
    {
        return isset(self::COLOR_CANONICAL[strtoupper(trim((string) $token))]);
    }

    public static function labelHasDiscriminant(?string $label): bool
    {
        $label = (string) $label;
        if (self::extractPackQty($label) !== null) {
            return true;
        }
        foreach (self::labelTokens($label) as $token) {
            if (self::isColorToken($token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map each family SKU to its matching variation (4PCS → 4PCS BIN, WH → White).
     *
     * @param  list<array<string, mixed>>  $variations
     * @param  list<string>  $skus
     * @return array<string, array<string, mixed>>
     */
    public static function assignToSkus(array $variations, array $skus): array
    {
        $assigned = [];
        $seen = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            $norm = strtoupper(preg_replace('/\s+/', ' ', $sku) ?? $sku);
            if ($sku === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $picked = self::pick($variations, $sku);
            if ($picked && (float) ($picked['price'] ?? 0) > 0) {
                $assigned[$sku] = $picked;
            }
        }

        return $assigned;
    }

    /**
     * Short label for display / title suffix, e.g. "4PCS" or "WHITE".
     */
    public static function shortLabel(array $variation, ?string $sku = null): string
    {
        $qty = self::extractPackQty((string) ($variation['label'] ?? ''));
        if ($qty) {
            return $qty.'PCS';
        }

        $labelTokens = self::labelTokens((string) ($variation['label'] ?? ''));
        foreach (self::colorNeedles($sku) as $needle) {
            if (in_array($needle, $labelTokens, true)) {
                return self::COLOR_CANONICAL[$needle] ?? $needle;
            }
        }

        foreach ($labelTokens as $token) {
            if (isset(self::COLOR_CANONICAL[$token])) {
                return self::COLOR_CANONICAL[$token];
            }
        }

        $label = trim((string) ($variation['label'] ?? ''));
        if ($label === '') {
            return '';
        }

        $first = trim((string) (explode(' / ', $label)[0] ?? ''));
        $first = preg_replace('/\s*[-–—]?\s*\([^)]*\$[^)]*\)\s*$/', '', $first) ?? $first;
        $first = trim($first);
        if ($first === '') {
            return '';
        }

        return strlen($first) <= 80 ? $first : (rtrim(substr($first, 0, 77)).'...');
    }

    /**
     * Stable eBay variation id for LMP rows (so 4 Product options stay 4 rows).
     *
     * @param  array<string, mixed>  $variation
     */
    public static function variationItemId(array $variation, string $listingId): string
    {
        foreach (['id', 'item_id'] as $key) {
            $value = trim((string) ($variation[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            if (preg_match('/^\d+$/', $value) && $value !== $listingId) {
                return $value;
            }
            if (preg_match('/\|(\d+)$/', $value, $match) && $match[1] !== $listingId) {
                return $match[1];
            }
        }

        $label = strtolower(trim((string) ($variation['label'] ?? 'opt')));

        return $listingId.'v'.substr(sha1($label), 0, 10);
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

        $priced = array_values(array_filter(
            $variations,
            fn ($variation) => (float) ($variation['price'] ?? 0) > 0
        ));
        if ($priced === []) {
            return null;
        }

        $packQty = self::extractPackQty($sku);
        if ($packQty !== null) {
            $priced = array_values(array_filter(
                $priced,
                fn ($variation) => self::extractPackQty((string) ($variation['label'] ?? '')) === $packQty
            ));
            if ($priced === []) {
                return null;
            }
            if (count($priced) === 1) {
                return $priced[0];
            }
        }

        $colorNeedles = self::colorNeedles($sku);
        if ($colorNeedles !== []) {
            $best = self::bestScored($priced, $colorNeedles);
            if ($best !== null) {
                return $best;
            }
            if ($packQty !== null) {
                return $priced[0];
            }

            return null;
        }

        if ($packQty !== null) {
            $tokens = self::skuTokens($sku);
            if ($tokens === []) {
                return $priced[0];
            }

            return self::bestScored($priced, $tokens) ?? $priced[0];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $variations
     * @param  list<string>  $needles
     * @return array<string, mixed>|null
     */
    private static function bestScored(array $variations, array $needles): ?array
    {
        $best = null;
        $bestScore = 0;
        foreach ($variations as $variation) {
            $tokens = self::labelTokens((string) ($variation['label'] ?? ''));
            $score = 0;
            foreach ($needles as $needle) {
                if (in_array(strtoupper($needle), $tokens, true)) {
                    $score += 20;
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
     * Extra SKU tokens used to break ties, ignoring pack words.
     *
     * @return list<string>
     */
    public static function skuTokens(?string $sku): array
    {
        $skip = [
            'PCS', 'PC', 'PIECE', 'PIECES', 'PACK', 'PACKS', 'PK',
            'PAIR', 'PAIRS', 'SET', 'SETS', 'OF', 'THE',
        ];

        $tokens = [];
        foreach (self::skuParts($sku) as $part) {
            if (strlen($part) < 3 || in_array($part, $skip, true) || preg_match('/^\d+$/', $part)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return list<string>
     */
    public static function labelTokens(?string $label): array
    {
        $parts = preg_split('/[^A-Z0-9]+/', strtoupper(trim((string) $label))) ?: [];

        return array_values(array_filter($parts, fn ($part) => $part !== ''));
    }

    /**
     * @return list<string>
     */
    private static function skuParts(?string $sku): array
    {
        $parts = preg_split('/[\s\-_]+/', strtoupper(trim((string) $sku))) ?: [];

        return array_values(array_filter($parts, fn ($part) => $part !== ''));
    }
}
