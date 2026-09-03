<?php

namespace App\Support;

use App\Models\GoogleShoppingBgtViewsRuleSetting;

/**
 * Shopify View L7 → suggested daily budget (Bgt Views).
 * Same slabs as /amazon-ads/all, stored in a Google-only table.
 */
final class GoogleShoppingBgtViewsRule extends GoogleShoppingBgtSlabRule
{
    public static function cacheKey(): string
    {
        return 'google_shopping_bgt_views_rule_resolved_v1';
    }

    public static function tableName(): string
    {
        return 'google_shopping_bgt_views_rule_settings';
    }

    public static function modelClass(): string
    {
        return GoogleShoppingBgtViewsRuleSetting::class;
    }

    public static function fromKey(): string
    {
        return 'views_from';
    }

    public static function toKey(): string
    {
        return 'views_to';
    }

    public static function slabNoun(): string
    {
        return 'Views';
    }

    /**
     * @return array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['views_from' => 351, 'views_to' => 9999, 'bgt' => 6, 'label' => 'Purple', 'color' => '#7c3aed'],
            ['views_from' => 281, 'views_to' => 350, 'bgt' => 5, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['views_from' => 211, 'views_to' => 280, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
            ['views_from' => 141, 'views_to' => 210, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
            ['views_from' => 71, 'views_to' => 140, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['views_from' => 0, 'views_to' => 70, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
        ];
    }

    /**
     * @param  array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>  $bands
     * @return array<int, array{views_from: float, views_to: float, bgt: int, label: string, color: string}>
     */
    public static function maybeFlipLegacyRedFirst(array $bands): array
    {
        if (count($bands) !== 6) {
            return $bands;
        }
        $labels = array_map(
            static fn (array $b): string => strtolower(trim((string) ($b['label'] ?? ''))),
            $bands
        );
        if ($labels !== ['red', 'yellow', 'blue', 'green', 'pink', 'purple']) {
            return $bands;
        }

        return array_values(array_reverse($bands));
    }
}
