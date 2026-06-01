<?php

namespace App\Support;

/**
 * Temu "Goods ID" values are long numeric strings. Excel/PhpSpreadsheet often parses them as floats
 * (scientific notation / precision loss). Prefer {@see fromSpreadsheetCell}; use normalizeKey for joins.
 */
class TemuGoodsIdHelper
{
    /**
     * Normalize goods_id for array keys / SQL joins (consistent string form).
     */
    public static function normalizeKey($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $s)) {
            return $s;
        }
        // Digits only (strip thousand separators etc.)
        $digits = preg_replace('/[^\d]/', '', $s);
        if ($digits !== '' && strlen($digits) >= 10) {
            return $digits;
        }

        return $s;
    }

    /**
     * Read Goods ID from a PhpSpreadsheet cell without float/scientific corruption.
     */
    public static function fromSpreadsheetCell($cell): ?string
    {
        if ($cell === null) {
            return null;
        }
        // Excel "Number" format usually preserves full ID in formatted output
        $formatted = trim((string) $cell->getFormattedValue());
        if ($formatted !== '' && preg_match('/\d/', $formatted)) {
            $digits = preg_replace('/[^\d]/', '', $formatted);
            if ($digits !== '') {
                return $digits;
            }
        }

        $v = $cell->getValue();
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v)) {
            $t = trim($v);
            if ($t === '') {
                return null;
            }
            if (preg_match('/^\d+$/', $t)) {
                return $t;
            }
            $digits = preg_replace('/[^\d]/', '', $t);

            return $digits !== '' ? $digits : $t;
        }
        if (is_numeric($v)) {
            // Last resort — may lose precision for >~15 digits as float
            return number_format((float) $v, 0, '.', '');
        }

        return trim((string) $v);
    }
}
