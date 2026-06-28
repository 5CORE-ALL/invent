<?php

namespace App\Support\Badges;

use App\Models\BadgeData;
use App\Models\TeamMemberKpi;

class BadgeDataCatalog
{
    public const KEY_PREFIX = 'badge:';

    /**
     * @return array<string, string>
     */
    public static function pageTitles(): array
    {
        return [
            'all-marketplace-master' => 'All Marketplace Master',
            'forecast-analysis' => 'Forecast Analysis',
            'on-sea-transit' => 'On Sea Transit',
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function fieldLabels(): array
    {
        return [
            'all-marketplace-master' => [
                'channels' => 'Channels',
                'l30_sales' => 'Sales',
                'y_sales' => 'Y Sales',
                'l30_orders' => 'Orders',
                'gprofit_pct' => 'GPFT',
                'g_roi' => 'G ROI',
                'ad_spend' => 'Spend',
                'ads_pct' => 'TACOS',
                'total_views' => 'Views',
                'cvr_pct' => 'CVR',
                'net_profit' => 'NPFT $',
                'npft_pct' => 'NPFT %',
                'n_roi' => 'NROI',
                'clicks' => 'Clicks',
                'map' => 'Map',
                'nmap' => 'N Map',
                'missing_l' => 'Missing L',
                'inventory_value_amazon' => 'Inv',
                'inv_at_lp' => 'Inv@LP',
                'tat' => 'TAT',
                'avg_rating' => 'Reviews',
                'total_reviews' => 'Review count',
                'seller_avg_rating' => 'Seller rating',
                'seller_total_reviews' => 'Seller review count',
            ],
            'on-sea-transit' => [
                'pre_load' => 'Pre-Load',
                'on_sea' => 'On Sea',
                'landed' => 'Landed',
                'transit' => 'Transit',
                'total_value' => 'Total value',
                'due' => 'Due',
                'value' => 'Value',
            ],
            'forecast-analysis' => [
                'total_msl_c' => 'MSL LP',
                'total_msl_sp_amz' => 'MSL SP',
                'total_inv_value' => 'INV',
                'total_lp_value' => 'LP',
                'total_order_value' => 'Ord',
                'total_minimal_msl' => 'Missing',
                'total_mip_value' => 'MIP',
                'total_r2s_value' => 'R2S',
                'total_transit_value' => 'Trn',
                'total_cbm' => 'CBM',
                'zero_stock_pct' => 'Zero stock %',
            ],
        ];
    }

    public static function makeKey(string $pageName, string $field): string
    {
        return self::KEY_PREFIX.$pageName.'|'.$field;
    }

    /**
     * @return array{page: string, field: string}|null
     */
    public static function parseKey(?string $stored): ?array
    {
        if (! $stored || ! str_starts_with($stored, self::KEY_PREFIX)) {
            return null;
        }

        $rest = substr($stored, strlen(self::KEY_PREFIX));
        $pos = strpos($rest, '|');
        if ($pos === false) {
            return null;
        }

        return [
            'page' => substr($rest, 0, $pos),
            'field' => substr($rest, $pos + 1),
        ];
    }

    public static function labelFor(string $pageName, string $field): string
    {
        $page = self::pageTitles()[$pageName] ?? self::humanize($pageName);
        $fieldLabel = self::fieldLabels()[$pageName][$field] ?? self::humanize($field);

        return $page.' · '.$fieldLabel;
    }

    public static function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function allCatalogOptions(): array
    {
        $out = [];

        foreach (BadgeData::query()->orderBy('page_name')->get() as $row) {
            $data = is_array($row->data) ? $row->data : [];
            $updated = $row->updated_at?->toDateTimeString();

            foreach ($data as $field => $value) {
                if (! is_scalar($value) && $value !== null) {
                    continue;
                }

                $out[] = self::optionPayload($row->page_name, (string) $field, $value, $updated);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function optionPayload(
        string $page,
        string $field,
        mixed $value = null,
        ?string $updatedAt = null
    ): array {
        if ($value === null) {
            $value = BadgeData::dataForPage($page)[$field] ?? null;
        }

        return [
            'key' => self::makeKey($page, $field),
            'page' => $page,
            'field' => $field,
            'page_label' => self::pageTitles()[$page] ?? self::humanize($page),
            'field_label' => self::fieldLabels()[$page][$field] ?? self::humanize($field),
            'label' => self::labelFor($page, $field),
            'value' => $value,
            'value_display' => self::formatDisplay($page, $field, $value),
            'updated_at' => $updatedAt,
        ];
    }

    public static function formatDisplay(string $page, string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($page === 'all-marketplace-master') {
            return self::formatAllMarketplaceMaster($field, $value);
        }

        if ($page === 'on-sea-transit') {
            return self::formatOnSeaTransit($field, $value);
        }

        if ($page === 'forecast-analysis') {
            return self::formatForecastAnalysis($field, $value);
        }

        if (is_numeric($value)) {
            return is_float($value + 0) && floor((float) $value) != (float) $value
                ? number_format((float) $value, 2)
                : number_format((float) $value);
        }

        return (string) $value;
    }

    private static function formatAllMarketplaceMaster(string $field, mixed $value): string
    {
        $n = (float) $value;

        return match ($field) {
            'channels', 'l30_orders', 'clicks', 'map', 'nmap', 'missing_l', 'total_reviews', 'seller_total_reviews'
                => number_format((int) round($n)),
            'y_sales' => $n > 0 ? '$'.number_format((int) round($n)) : 'NYS',
            'l30_sales', 'ad_spend', 'net_profit', 'inventory_value_amazon', 'inv_at_lp'
                => '$'.number_format((int) round($n)),
            'gprofit_pct', 'ads_pct', 'npft_pct' => number_format($n, 1).'%',
            'g_roi', 'n_roi' => number_format((int) round($n)).'%',
            'cvr_pct' => $value === null ? '-' : number_format($n, 2).'%',
            'tat' => $n > 0 ? number_format($n, 2) : '0',
            'avg_rating', 'seller_avg_rating' => number_format($n, 1).' ★',
            'total_views' => number_format((int) round($n)),
            default => self::formatNumericFallback($value),
        };
    }

    private static function formatOnSeaTransit(string $field, mixed $value): string
    {
        if (in_array($field, ['pre_load', 'on_sea', 'landed', 'transit'], true)) {
            return number_format((int) round((float) $value));
        }

        return '$'.number_format((float) $value, 0);
    }

    private static function formatForecastAnalysis(string $field, mixed $value): string
    {
        if ($field === 'zero_stock_pct') {
            return number_format((int) round((float) $value)).'%';
        }

        if ($field === 'total_cbm') {
            return number_format((int) round((float) $value));
        }

        $n = (float) $value;
        if (! is_finite($n)) {
            return '0';
        }

        return '$'.number_format((int) round($n / 1000)).'K';
    }

    private static function formatNumericFallback(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        $n = (float) $value;

        return floor($n) == $n
            ? number_format((int) $n)
            : number_format($n, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function resolveAssignments(TeamMemberKpi $record): array
    {
        $assigned = [];

        for ($k = 1; $k <= 5; $k++) {
            $stored = $record->{"kpi_{$k}_value"};
            $parsed = self::parseKey($stored);
            if (! $parsed) {
                continue;
            }

            $live = BadgeData::dataForPage($parsed['page'])[$parsed['field']] ?? null;
            $assigned[] = array_merge(
                self::optionPayload($parsed['page'], $parsed['field'], $live),
                [
                    'slot' => $k,
                    'label' => $record->{"kpi_{$k}_label"} ?: self::labelFor($parsed['page'], $parsed['field']),
                ]
            );
        }

        return $assigned;
    }

    public static function isValidCatalogKey(string $key): bool
    {
        $parsed = self::parseKey($key);
        if (! $parsed) {
            return false;
        }

        $data = BadgeData::dataForPage($parsed['page']);

        return array_key_exists($parsed['field'], $data);
    }
}
