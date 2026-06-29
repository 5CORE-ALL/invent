<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparisonData extends Model
{
    protected $table = 'comparison_data';

    protected $fillable = [
        'sku',
        'parent',
        'sheet_data',
        'google_sheet_url',
        'google_sheet_tab',
        'updated_by',
    ];

    protected $casts = [
        'sheet_data' => 'array',
    ];

    public static function defaultSheetCells(): array
    {
        $labels = [
            'Product Photo',
            'Person Name Review',
            'Supplier Link',
            'Product Name / Supplier SKU',
            'Load Bearing Unit (LB)',
            'Material',
            'Stool Height (inch)',
            'Seat Dimension (inch)',
            'Seat Thickness (inch)',
            'Qty',
            'Supplier Price (usd)',
            'RMB',
            'NW (LB) / GW per pcs (LB)',
        ];

        return array_map(fn ($label) => ['', '', $label, '', '', ''], $labels);
    }

    public static function normalizeCells(array $cells): array
    {
        $maxCols = 0;
        foreach ($cells as $row) {
            if (! is_array($row)) {
                continue;
            }
            $maxCols = max($maxCols, count($row));
        }

        $maxCols = max($maxCols, 6);
        $normalized = [];

        foreach ($cells as $row) {
            if (! is_array($row)) {
                $row = [$row];
            }
            $row = array_map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value), $row);
            while (count($row) < $maxCols) {
                $row[] = '';
            }
            $normalized[] = array_slice($row, 0, $maxCols);
        }

        return $normalized;
    }

    /**
     * @return array{cells: array<string, string>, rows: array<string, string>, cols: array<string, string>}
     */
    public static function defaultSheetFormats(): array
    {
        return [
            'cells' => [],
            'rows' => [],
            'cols' => [],
        ];
    }

    /**
     * @return array{cells: array<string, string>, rows: array<string, string>, cols: array<string, string>}
     */
    public static function normalizeFormats(?array $formats): array
    {
        $formats = is_array($formats) ? $formats : [];
        $normalizeMap = function (?array $map): array {
            $out = [];
            foreach ($map ?? [] as $key => $color) {
                $normalizedColor = self::normalizeColor((string) $color);
                if ($normalizedColor !== '') {
                    $out[(string) $key] = $normalizedColor;
                }
            }

            return $out;
        };

        return [
            'cells' => $normalizeMap($formats['cells'] ?? []),
            'rows' => $normalizeMap($formats['rows'] ?? []),
            'cols' => $normalizeMap($formats['cols'] ?? []),
        ];
    }

    /**
     * Manual sheet formats override auto-computed keys with the same target.
     *
     * @return array{cells: array<string, string>, rows: array<string, string>, cols: array<string, string>}
     */
    public static function mergeManualAndAutoFormats(?array $manual, array $auto): array
    {
        $manual = self::normalizeFormats($manual);
        $auto = self::normalizeFormats($auto);

        return [
            'cells' => array_merge($auto['cells'], $manual['cells']),
            'rows' => array_merge($auto['rows'], $manual['rows']),
            'cols' => array_merge($auto['cols'], $manual['cols']),
        ];
    }

    public static function normalizeColor(string $color): string
    {
        $color = trim($color);
        if ($color === '') {
            return '';
        }

        if (preg_match('/^#([0-9a-f]{3})$/i', $color, $matches)) {
            $hex = strtolower($matches[1]);

            return '#' . $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (preg_match('/^#([0-9a-f]{6})$/i', $color, $matches)) {
            return '#' . strtolower($matches[1]);
        }

        return '';
    }
}
