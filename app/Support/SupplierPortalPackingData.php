<?php

namespace App\Support;

use App\Models\ProductMaster;
use Illuminate\Support\Facades\Schema;

class SupplierPortalPackingData
{
    public const CATEGORIES = [
        'master_carton_designs',
    ];

    public const FIELDS = [
        'packing_box_spec' => 'Box / carton',
        'packing_units_ctn' => 'Units/CTN',
        'packing_fragile' => 'Fragile',
        'packing_seal_method' => 'Seal',
        'packing_instructions' => 'Design Instructions',
        'packing_sheet_url' => 'Sheet URL',
        'packing_cdr_path' => 'CDR',
    ];

    public const CTN_PKG_KEY = 'ctn_instructions';

    public const CTN_PKG_LABEL = 'ctn pkg';

    public static function usesPackingGrid(string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        if (! Schema::hasTable('product_master')) {
            return [];
        }

        $select = ['id', 'parent', 'sku', 'Values'];
        $products = ProductMaster::query()
            ->select($select)
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $out = [];
        foreach ($products as $product) {
            $sku = trim((string) ($product->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $values = $product->Values;
            if (is_string($values)) {
                $values = json_decode($values, true);
            }
            if (! is_array($values)) {
                $values = [];
            }

            $row = [
                'id' => $product->id,
                'Parent' => trim((string) ($product->parent ?? '')),
                'SKU' => $sku,
                'status' => trim((string) ($values['status'] ?? '')),
                self::CTN_PKG_KEY => mb_substr(trim((string) ($values[self::CTN_PKG_KEY] ?? '')), 0, 100),
            ];
            foreach (array_keys(self::FIELDS) as $key) {
                $row[$key] = trim((string) ($values[$key] ?? ''));
            }
            $out[] = $row;
        }

        return $out;
    }
}
