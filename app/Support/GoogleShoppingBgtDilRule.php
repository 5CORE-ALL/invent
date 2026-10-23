<?php

namespace App\Support;

use App\Models\GoogleShoppingBgtDilRuleSetting;

/**
 * Campaign Dil% (OV L30 ÷ INV × 100) → suggested daily budget part (BGT DIL), added to SBGT.
 * Stored in a Google-only table. The default slabs add 0, so SBGT does not change until you set values.
 * From / To / BGT may be negative.
 */
final class GoogleShoppingBgtDilRule extends GoogleShoppingBgtSlabRule
{
    public static function cacheKey(): string
    {
        return 'google_shopping_bgt_dil_rule_resolved_v1';
    }

    public static function tableName(): string
    {
        return 'google_shopping_bgt_dil_rule_settings';
    }

    public static function modelClass(): string
    {
        return GoogleShoppingBgtDilRuleSetting::class;
    }

    public static function fromKey(): string
    {
        return 'dil_from';
    }

    public static function toKey(): string
    {
        return 'dil_to';
    }

    public static function slabNoun(): string
    {
        return 'Dil';
    }

    public static function treatMissingInputAsZero(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{dil_from: float, dil_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['dil_from' => 50, 'dil_to' => 9999, 'bgt' => 0, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['dil_from' => 25, 'dil_to' => 50, 'bgt' => 0, 'label' => 'Green', 'color' => '#28a745'],
            ['dil_from' => 0, 'dil_to' => 25, 'bgt' => 0, 'label' => 'Red', 'color' => '#a00211'],
        ];
    }
}
