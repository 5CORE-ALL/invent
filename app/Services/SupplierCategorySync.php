<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Category is derived from the SKU's resolved supplier (suppliers.category_id → categories.name).
 * Same read path everywhere: ToOrderSupplierSync::resolveSupplierForSku() then supplier → category map.
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

        $supplierBySku = self::batchResolveSupplierForSkus(array_keys($normalized));
        $out = [];
        foreach (array_keys($normalized) as $skuKey) {
            $out[$skuKey] = self::categoryForSupplierName($supplierBySku[$skuKey] ?? '', $categoryMap);
        }

        return $out;
    }

    /**
     * @param  list<string>  $skuKeys  already uppercased trimmed keys
     * @return array<string, string>
     */
    private static function batchResolveSupplierForSkus(array $skuKeys): array
    {
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
        if ($remaining !== [] && \Illuminate\Support\Facades\Schema::hasTable('ready_to_ship')) {
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
        if ($remaining !== [] && \Illuminate\Support\Facades\Schema::hasTable('transit_container_details')) {
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

    /**
     * Update suppliers.category_id for the SKU's resolved supplier.
     *
     * @return array{updated: int, skipped_no_supplier: bool, skipped_supplier_not_found: bool}
     */
    public static function setCategoryForSku(string $sku, string $categoryName): array
    {
        $skuUpper = strtoupper(trim($sku));
        $categoryName = trim($categoryName);

        if ($skuUpper === '' || $categoryName === '') {
            return ['updated' => 0, 'skipped_no_supplier' => true, 'skipped_supplier_not_found' => false];
        }

        $categoryId = DB::table('categories')
            ->whereRaw('TRIM(LOWER(name)) = ?', [strtolower($categoryName)])
            ->value('id');

        if (! $categoryId) {
            throw new \InvalidArgumentException('Unknown category: '.$categoryName);
        }

        $supplierName = ToOrderSupplierSync::resolveSupplierForSku($sku);
        if ($supplierName === '') {
            return ['updated' => 0, 'skipped_no_supplier' => true, 'skipped_supplier_not_found' => false];
        }

        $count = (int) DB::table('suppliers')
            ->whereRaw('TRIM(UPPER(name)) = ?', [strtoupper($supplierName)])
            ->update(['category_id' => $categoryId]);

        return [
            'updated' => $count,
            'skipped_no_supplier' => false,
            'skipped_supplier_not_found' => $count === 0,
        ];
    }
}
