<?php

namespace App\Support;

use App\Models\GoogleShoppingBgtAcosLtRuleSetting;

/**
 * Campaign lifetime ACOS % (all-time Spend ÷ all-time Sales × 100) → suggested daily budget part (BGT ACOS LT), added to SBGT.
 * Stored in a Google-only table. The default slabs add 0, so SBGT does not change until you set values.
 * From / To / BGT may be negative.
 */
final class GoogleShoppingBgtAcosLtRule extends GoogleShoppingBgtSlabRule
{
    public static function cacheKey(): string
    {
        return 'google_shopping_bgt_acos_lt_rule_resolved_v1';
    }

    public static function tableName(): string
    {
        return 'google_shopping_bgt_acos_lt_rule_settings';
    }

    public static function modelClass(): string
    {
        return GoogleShoppingBgtAcosLtRuleSetting::class;
    }

    public static function fromKey(): string
    {
        return 'acos_lt_from';
    }

    public static function toKey(): string
    {
        return 'acos_lt_to';
    }

    public static function slabNoun(): string
    {
        return 'ACOS LT';
    }

    public static function treatMissingInputAsZero(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{acos_lt_from: float, acos_lt_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['acos_lt_from' => 0, 'acos_lt_to' => 10, 'bgt' => 0, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['acos_lt_from' => 10, 'acos_lt_to' => 20, 'bgt' => 0, 'label' => 'Green', 'color' => '#28a745'],
            ['acos_lt_from' => 20, 'acos_lt_to' => 30, 'bgt' => 0, 'label' => 'Blue', 'color' => '#2563eb'],
            ['acos_lt_from' => 30, 'acos_lt_to' => 40, 'bgt' => 0, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['acos_lt_from' => 40, 'acos_lt_to' => 9999, 'bgt' => 0, 'label' => 'Red', 'color' => '#a00211'],
        ];
    }
}
