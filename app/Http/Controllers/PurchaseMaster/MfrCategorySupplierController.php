<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;

/**
 * "MFR Category - Supplier" page: one row per category (the MFR category of /category.list) with the
 * suppliers linked to it. Editing the links (add / delete) goes through the existing
 * /category/{id}/suppliers endpoints of CategoryController, the same ones /category.list uses.
 */
class MfrCategorySupplierController extends Controller
{
    public function index()
    {
        return view('purchase-master.category.mfr_category_supplier');
    }

    public function data()
    {
        // category id => [{id, name}], from the comma separated suppliers.category_id column
        $byCategory = [];
        $suppliers = Supplier::query()
            ->whereNotNull('category_id')
            ->where('category_id', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'company', 'category_id']);

        foreach ($suppliers as $supplier) {
            $label = trim((string) ($supplier->name ?? ''));
            if ($label === '') {
                $label = trim((string) ($supplier->company ?? ''));
            }
            if ($label === '') {
                $label = 'Supplier #'.$supplier->id;
            }

            foreach (preg_split('/\s*,\s*/', (string) $supplier->category_id, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $rawId) {
                $categoryId = (int) trim($rawId);
                if ($categoryId > 0) {
                    $byCategory[$categoryId][$supplier->id] = ['id' => (int) $supplier->id, 'name' => $label];
                }
            }
        }

        $rows = Category::query()->orderBy('name')->get(['id', 'name'])->map(function ($category) use ($byCategory) {
            $linked = array_values($byCategory[(int) $category->id] ?? []);

            return [
                'id' => (int) $category->id,
                'mfr_category' => (string) $category->name,
                'suppliers' => $linked,
                'suppliers_text' => implode(', ', array_column($linked, 'name')),
                'count' => count($linked),
            ];
        })->values();

        return response()->json($rows);
    }
}
