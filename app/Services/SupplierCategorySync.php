<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Category for pipeline SKUs:
 * - Write: to_order_analysis.category_name (per SKU — bulk edit affects only chosen rows)
 * - Read: SKU override on to_order_analysis → fallback to supplier's category (suppliers.category_id)
 */
class SupplierCategorySync
{
    /**
     * @return array<string, string> normalized supplier name => category name
     */
    public static function supplierCategoryMap(): array
    {
        $categoryById = DB::table('categories')->pluck('name', 'id');
        $map = [];

        foreach (DB::table('suppliers')->select('name', 'category_id')->get() as $sup) {
            $name = strtoupper(trim((string) ($sup->name ?? '')));
            if ($name === '' || empty($sup->category_id)) {
                continue;
            }

            $catName = $categoryById[$sup->category_id] ?? null;
            if ($catName && ! isset($map[$name])) {
                $map[$name] = (string) $catName;
            }
        }

        return $map;
    }

    public static function categoryForSupplierName(string $supplierName, ?array $map = null): string
    {
        $name = strtoupper(trim($supplierName));
        if ($name === '') {
            return '';
        }

        $map = $map ?? self::supplierCategoryMap();

        return $map[$name] ?? '';
    }

    public static function resolveCategoryForSku(string $sku, ?array $map = null): string
    {
        $skuUpper = strtoupper(trim($sku));
        if ($skuUpper === '') {
            return '';
        }

        $override = self::skuCategoryOverride($skuUpper);
        if ($override !== null) {
            return $override;
        }

        $supplier = ToOrderSupplierSync::resolveSupplierForSku($sku);

        return self::categoryForSupplierName($supplier, $map);
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string> normalized SKU => category name
     */
    public static function resolveCategoryMapForSkus(array $skus): array
    {
        $categoryMap = self::supplierCategoryMap();
        $normalized = [];
        foreach ($skus as $sku) {
            $key = strtoupper(trim($sku));
            if ($key !== '') {
                $normalized[$key] = true;
            }
        }

        if ($normalized === []) {
            return [];
        }

        $skuKeys = array_keys($normalized);
        $overrides = self::batchSkuCategoryOverrides($skuKeys);
        $supplierBySku = self::batchResolveSupplierForSkus(
            array_values(array_filter($skuKeys, fn ($k) => ! isset($overrides[$k])))
        );

        $out = [];
        foreach ($skuKeys as $skuKey) {
            if (isset($overrides[$skuKey])) {
                $out[$skuKey] = $overrides[$skuKey];
                continue;
            }
            $out[$skuKey] = self::categoryForSupplierName($supplierBySku[$skuKey] ?? '', $categoryMap);
        }

        return $out;
    }

    /**
     * Persist category on the SKU row only (does not change suppliers.category_id).
     *
     * @return array{updated: int, applied: bool, unchanged: bool}
     */
    public static function setCategoryForSku(string $sku, string $categoryName, ?string $parent = null): array
    {
        $skuUpper = strtoupper(trim($sku));
        $categoryName = trim($categoryName);

        if ($skuUpper === '' || $categoryName === '') {
            return ['updated' => 0, 'applied' => false, 'unchanged' => false];
        }

        $categoryExists = DB::table('categories')
            ->whereRaw('TRIM(LOWER(name)) = ?', [strtolower($categoryName)])
            ->exists();

        if (! $categoryExists) {
            throw new \InvalidArgumentException('Unknown category: '.$categoryName);
        }

        if (! Schema::hasColumn('to_order_analysis', 'category_name')) {
            throw new \RuntimeException('Missing to_order_analysis.category_name — run migrations.');
        }

        $parentTrim = $parent !== null ? trim($parent) : '';
        $parentNorm = strtoupper($parentTrim);
        $now = now();

        $existingQuery = DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
        if ($parentNorm !== '') {
            $existingQuery->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [$parentNorm]);
        }
        $existing = $existingQuery->value('category_name');

        if ($existing !== null && strcasecmp(trim((string) $existing), $categoryName) === 0) {
            return ['updated' => 0, 'applied' => true, 'unchanged' => true];
        }

        $rowUpdated = 0;
        if ($parentNorm !== '') {
            $rowUpdated = (int) DB::table('to_order_analysis')
                ->whereNull('deleted_at')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                ->whereRaw('TRIM(UPPER(COALESCE(parent, \'\'))) = ?', [$parentNorm])
                ->update([
                    'category_name' => $categoryName,
                    'updated_at' => $now,
                ]);
        }

        if ($rowUpdated === 0) {
            $rowUpdated = (int) DB::table('to_order_analysis')
                ->whereNull('deleted_at')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
                ->update([
                    'category_name' => $categoryName,
                    'updated_at' => $now,
                ]);
        }

        if ($rowUpdated === 0) {
            DB::table('to_order_analysis')->insert([
                'sku' => $sku,
                'parent' => $parentTrim !== '' ? $parentTrim : null,
                'category_name' => $categoryName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [
            'updated' => $rowUpdated > 0 ? $rowUpdated : 1,
            'applied' => true,
            'unchanged' => false,
        ];
    }

    private static function skuCategoryOverride(string $skuUpper): ?string
    {
        if (! Schema::hasColumn('to_order_analysis', 'category_name')) {
            return null;
        }

        $rows = DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->whereRaw('TRIM(UPPER(sku)) = ?', [$skuUpper])
            ->whereNotNull('category_name')
            ->whereRaw("TRIM(category_name) != ''")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['category_name']);

        foreach ($rows as $row) {
            $name = trim((string) ($row->category_name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $skuKeys  uppercased trimmed keys
     * @return array<string, string>
     */
    private static function batchSkuCategoryOverrides(array $skuKeys): array
    {
        if ($skuKeys === [] || ! Schema::hasColumn('to_order_analysis', 'category_name')) {
            return [];
        }

        $wanted = array_fill_keys($skuKeys, true);
        $out = [];

        foreach (DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->whereNotNull('category_name')
            ->whereRaw("TRIM(category_name) != ''")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'category_name']) as $row) {
            $key = strtoupper(trim((string) $row->sku));
            if ($key === '' || ! isset($wanted[$key]) || isset($out[$key])) {
                continue;
            }
            $out[$key] = trim((string) $row->category_name);
        }

        return $out;
    }

    /**
     * @param  list<string>  $skuKeys  already uppercased trimmed keys
     * @return array<string, string>
     */
    private static function batchResolveSupplierForSkus(array $skuKeys): array
    {
        if ($skuKeys === []) {
            return [];
        }

        $out = array_fill_keys($skuKeys, '');

        foreach (DB::table('to_order_analysis')
            ->whereNull('deleted_at')
            ->whereNotNull('supplier_name')
            ->whereRaw("TRIM(supplier_name) != ''")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['sku', 'supplier_name']) as $row) {
            $key = strtoupper(trim((string) $row->sku));
            if ($key !== '' && isset($out[$key]) && $out[$key] === '') {
                $out[$key] = trim((string) $row->supplier_name);
            }
        }

        $remaining = array_keys(array_filter($out, fn ($v) => $v === ''));
        if ($remaining !== []) {
            foreach (DB::table('mfrg_progress')
                ->whereNull('deleted_at')
                ->whereNotNull('supplier')
                ->whereRaw("TRIM(supplier) != ''")
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['sku', 'supplier']) as $row) {
                $key = strtoupper(trim((string) $row->sku));
                if ($key !== '' && isset($out[$key]) && $out[$key] === '') {
                    $out[$key] = trim((string) $row->supplier);
                }
            }
        }

        $remaining = array_keys(array_filter($out, fn ($v) => $v === ''));
        if ($remaining !== [] && Schema::hasTable('ready_to_ship')) {
            foreach (DB::table('ready_to_ship')
                ->whereNull('deleted_at')
                ->whereNotNull('supplier')
                ->whereRaw("TRIM(supplier) != ''")
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['sku', 'supplier']) as $row) {
                $key = strtoupper(trim((string) $row->sku));
                if ($key !== '' && isset($out[$key]) && $out[$key] === '') {
                    $out[$key] = trim((string) $row->supplier);
                }
            }
        }

        $remaining = array_keys(array_filter($out, fn ($v) => $v === ''));
        if ($remaining !== [] && Schema::hasTable('transit_container_details')) {
            foreach (DB::table('transit_container_details')
                ->whereNull('deleted_at')
                ->whereNotNull('supplier_name')
                ->whereRaw("TRIM(supplier_name) != ''")
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['our_sku', 'supplier_name']) as $row) {
                $key = strtoupper(trim((string) $row->our_sku));
                if ($key !== '' && isset($out[$key]) && $out[$key] === '') {
                    $out[$key] = trim((string) $row->supplier_name);
                }
            }
        }

        return $out;
    }
}
