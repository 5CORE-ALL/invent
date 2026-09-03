<?php

namespace App\Support;

use App\Models\GoogleShoppingBgtPrcRuleSetting;

/**
 * Shopify price → suggested daily budget (BGT PRC).
 * Same slabs as /amazon-ads/all, stored in a Google-only table.
 */
final class GoogleShoppingBgtPrcRule extends GoogleShoppingBgtSlabRule
{
    public static function cacheKey(): string
    {
        return 'google_shopping_bgt_prc_rule_resolved_v1';
    }

    public static function tableName(): string
    {
        return 'google_shopping_bgt_prc_rule_settings';
    }

    public static function modelClass(): string
    {
        return GoogleShoppingBgtPrcRuleSetting::class;
    }

    public static function fromKey(): string
    {
        return 'prc_from';
    }

    public static function toKey(): string
    {
        return 'prc_to';
    }

    public static function slabNoun(): string
    {
        return 'Price';
    }

    public static function treatMissingInputAsZero(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{prc_from: float, prc_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['prc_from' => 151, 'prc_to' => 9999, 'bgt' => 5, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['prc_from' => 101, 'prc_to' => 150, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
            ['prc_from' => 61, 'prc_to' => 100, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
            ['prc_from' => 41, 'prc_to' => 60, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['prc_from' => 0, 'prc_to' => 40, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
        ];
    }

    /**
     * @param  array<int, array{prc_from: float, prc_to: float, bgt: int, label: string, color: string}>  $bands
     * @return array<int, array{prc_from: float, prc_to: float, bgt: int, label: string, color: string}>
     */
    public static function maybeFlipLegacyRedFirst(array $bands): array
    {
        if (count($bands) !== 5) {
            return $bands;
        }
        $labels = array_map(
            static fn (array $b): string => strtolower(trim((string) ($b['label'] ?? ''))),
            $bands
        );
        if ($labels !== ['red', 'yellow', 'blue', 'green', 'pink']) {
            return $bands;
        }

        return array_values(array_reverse($bands));
    }
}
