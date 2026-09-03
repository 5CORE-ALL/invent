<?php

namespace App\Support;

use App\Models\GoogleShoppingBgtCvrRuleSetting;

/**
 * Google Shopping CVR L30 (sold ÷ clicks × 100) → suggested daily budget (Bgt Cvr).
 * Same slabs as /amazon-ads/all, stored in a Google-only table.
 */
final class GoogleShoppingBgtCvrRule extends GoogleShoppingBgtSlabRule
{
    public static function cacheKey(): string
    {
        return 'google_shopping_bgt_cvr_rule_resolved_v1';
    }

    public static function tableName(): string
    {
        return 'google_shopping_bgt_cvr_rule_settings';
    }

    public static function modelClass(): string
    {
        return GoogleShoppingBgtCvrRuleSetting::class;
    }

    public static function fromKey(): string
    {
        return 'cvr_from';
    }

    public static function toKey(): string
    {
        return 'cvr_to';
    }

    public static function slabNoun(): string
    {
        return 'CVR';
    }

    /**
     * @return array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>
     */
    public static function defaultBands(): array
    {
        return [
            ['cvr_from' => 20, 'cvr_to' => 9999, 'bgt' => 6, 'label' => 'Purple', 'color' => '#7c3aed'],
            ['cvr_from' => 16, 'cvr_to' => 20, 'bgt' => 5, 'label' => 'Pink', 'color' => '#e83e8c'],
            ['cvr_from' => 12, 'cvr_to' => 16, 'bgt' => 4, 'label' => 'Green', 'color' => '#28a745'],
            ['cvr_from' => 8, 'cvr_to' => 12, 'bgt' => 3, 'label' => 'Blue', 'color' => '#2563eb'],
            ['cvr_from' => 4, 'cvr_to' => 8, 'bgt' => 2, 'label' => 'Yellow', 'color' => '#ffc107'],
            ['cvr_from' => 0, 'cvr_to' => 4, 'bgt' => 1, 'label' => 'Red', 'color' => '#a00211'],
        ];
    }

    /**
     * @param  array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>  $bands
     * @return array<int, array{cvr_from: float, cvr_to: float, bgt: int, label: string, color: string}>
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
