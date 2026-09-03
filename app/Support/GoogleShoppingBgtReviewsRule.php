<?php

namespace App\Support;

use App\Models\GoogleShoppingBgtReviewsRuleSetting;

/**
 * Product star rating → suggested daily budget (Bgt Reviews).
 * Same slabs as /amazon-ads/all, stored in a Google-only table.
 */
final class GoogleShoppingBgtReviewsRule extends GoogleShoppingBgtSlabRule
{
    public static function cacheKey(): string
    {
        return 'google_shopping_bgt_reviews_rule_resolved_v1';
    }

    public static function tableName(): string
    {
        return 'google_shopping_bgt_reviews_rule_settings';
    }

    public static function modelClass(): string
    {
        return GoogleShoppingBgtReviewsRuleSetting::class;
    }

    public static function fromKey(): string
    {
        return 'rev_from';
    }

    public static function toKey(): string
    {
        return 'rev_to';
    }

    public static function slabNoun(): string
    {
        return 'Reviews';
    }

    public static function minBgt(): int
    {
        return 1;
    }

    public static function treatMissingInputAsZero(): bool
    {
        return false;
    }

    public static function fillGapsBetweenBands(): bool
    {
        return true;
    }

    /**
     * @return array<int, array{rev_from: float, rev_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['rev_from' => 2.99, 'rev_to' => 3.5, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
            ['rev_from' => 3.51, 'rev_to' => 4.0, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['rev_from' => 4.01, 'rev_to' => 4.5, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
            ['rev_from' => 4.51, 'rev_to' => 5.0, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
        ];
    }
}
