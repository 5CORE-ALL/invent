<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical per-SKU writes for to_order_analysis (and forecast where needed).
 * Bulk / inline edits must use these helpers so only the targeted SKU row changes.
 */
class ToOrderSkuFieldSync
{
    /**
     * @param  non-empty-string  $dbColumn  to_order_analysis column name
     */
    public static function upsertToOrderField(string $sku, string $dbColumn, mixed $value, ?string $parent = null): void
    {
        if (! Schema::hasTable('to_order_analysis') || ! Schema::hasColumn('to_order_analysis', $dbColumn)) {
            throw new \RuntimeException('Missing to_order_analysis.'.$dbColumn);
        }

        $skuUpper = strtoupper(trim($sku));
        if ($skuUpper === '') {
            return;
        }

        $parentTrim = $parent !== null ? trim($parent) : '';
        $parentNorm = strtoupper($parentTrim);
        $now = now();

        $updated = 0;
        if ($parentNorm !== '') {
            $updated = (int) DB::table('to_order_analysis')
                ->whereNull('deleted_at')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                ->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [$parentNorm])
                ->update([$dbColumn => $value, 'updated_at' => $now]);
        }

        if ($updated === 0) {
            $updated = (int) DB::table('to_order_analysis')
                ->whereNull('deleted_at')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                ->update([$dbColumn => $value, 'updated_at' => $now]);
        }

        if ($updated === 0) {
            DB::table('to_order_analysis')->insert([
                'sku' => $sku,
                'parent' => $parentTrim !== '' ? $parentTrim : null,
                $dbColumn => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public static function setMoqForSku(string $sku, mixed $value, ?string $parent = null): void
    {
        $valueNum = is_numeric($value) ? (int) $value : null;
        self::upsertToOrderField($sku, 'approved_qty', $valueNum, $parent);
        self::syncForecastMoqForSku($sku, $valueNum, $parent);
    }

    public static function setExecForSku(string $sku, mixed $execName, ?string $parent = null): void
    {
        $val = trim((string) $execName);
        self::upsertToOrderField($sku, 'exec', $val !== '' ? $val : null, $parent);
    }

    public static function setAdvanceDateForSku(string $sku, mixed $value, ?string $parent = null): void
    {
        self::upsertToOrderField($sku, 'advance_date', $value !== '' && $value !== null ? $value : null, $parent);
    }

    public static function setDateApprForSku(string $sku, mixed $value, ?string $parent = null): void
    {
        self::upsertToOrderField($sku, 'date_apprvl', $value !== '' && $value !== null ? $value : null, $parent);
    }

    public static function setStageForSku(string $sku, mixed $value, ?string $parent = null): void
    {
        self::upsertToOrderField($sku, 'stage', $value !== '' && $value !== null ? $value : null, $parent);
    }

    public static function setNrlForSku(string $sku, mixed $value, ?string $parent = null): void
    {
        self::upsertToOrderField($sku, 'nrl', $value !== '' && $value !== null ? $value : null, $parent);
    }

    public static function setOrderQtyForSku(string $sku, mixed $value, ?string $parent = null): void
    {
        $num = is_numeric($value) ? (int) $value : null;
        self::upsertToOrderField($sku, 'order_qty', $num, $parent);
    }

    private static function syncForecastMoqForSku(string $sku, ?int $valueNum, ?string $parent = null): void
    {
        if (! Schema::hasTable('forecast_analysis')) {
            return;
        }

        $skuUpper = strtoupper(trim($sku));
        if ($skuUpper === '') {
            return;
        }

        $parentTrim = $parent !== null ? trim($parent) : '';
        $parentNorm = strtoupper($parentTrim);
        $now = now();

        $updated = 0;
        if ($parentNorm !== '') {
            $updated = (int) DB::table('forecast_analysis')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                ->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [$parentNorm])
                ->update(['approved_qty' => $valueNum, 'updated_at' => $now]);
        }

        if ($updated === 0) {
            $updated = (int) DB::table('forecast_analysis')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                ->update(['approved_qty' => $valueNum, 'updated_at' => $now]);
        }

        if ($updated === 0) {
            DB::table('forecast_analysis')->insert([
                'sku' => $sku,
                'parent' => $parentTrim !== '' ? $parentTrim : null,
                'approved_qty' => $valueNum,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
