<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QcAndPackingController extends Controller
{
    /**
     * Add image_url and supplier onto QC rows.
     * Image: Shopify, then product_master image_path, main_image, and image1.
     * Supplier: latest to-order name, then manufacturing, ready-to-ship, and transit.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function attachImages(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku !== '') {
                $skus[$sku] = $sku;
            }
        }
        $urls = $this->urlsBySku(array_values($skus));
        $suppliers = $this->supplierBySku(array_values($skus));

        foreach ($rows as $i => $row) {
            $key = strtoupper(trim((string) ($row['sku'] ?? '')));
            $rows[$i]['image_url'] = $urls[$key] ?? null;
            $rows[$i]['supplier'] = $suppliers[$key] ?? '';
        }

        return $rows;
    }

    /**
     * Latest supplier name for each SKU: to-order, then manufacturing, ready-to-ship, and transit.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    private function supplierBySku(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $out = array_fill_keys($skus, '');
        $this->fillSupplier($out, $skus, 'to_order_analysis', 'sku', 'supplier_name');
        $this->fillSupplier($out, $skus, 'mfrg_progress', 'sku', 'supplier');
        $this->fillSupplier($out, $skus, 'ready_to_ship', 'sku', 'supplier');
        $this->fillSupplier($out, $skus, 'transit_container_details', 'our_sku', 'supplier_name');

        return $out;
    }

    /**
     * @param  array<string, string>  $out
     * @param  list<string>  $skus
     */
    private function fillSupplier(array &$out, array $skus, string $table, string $skuColumn, string $nameColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $skuColumn) || ! Schema::hasColumn($table, $nameColumn)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $query = DB::table($table)->select($skuColumn, $nameColumn);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $query->whereNotNull($nameColumn)
            ->whereRaw('TRIM('.$nameColumn.") != ''")
            ->whereRaw('UPPER(TRIM('.$skuColumn.")) IN ({$placeholders})", $skus);
        if (Schema::hasColumn($table, 'updated_at')) {
            $query->orderByDesc('updated_at');
        }
        if (Schema::hasColumn($table, 'id')) {
            $query->orderByDesc('id');
        }

        foreach ($query->get() as $row) {
            $key = strtoupper(trim((string) ($row->{$skuColumn} ?? '')));
            $name = trim((string) ($row->{$nameColumn} ?? ''));
            if ($key !== '' && $name !== '' && isset($out[$key]) && $out[$key] === '') {
                $out[$key] = $name;
            }
        }
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    private function urlsBySku(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $shopifyBySku = [];
        if (Schema::hasTable('shopify_skus')) {
            $shopRows = DB::table('shopify_skus')
                ->select('sku', 'image_src')
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $skus)
                ->get();
            foreach ($shopRows as $shopRow) {
                $key = strtoupper(trim((string) $shopRow->sku));
                if ($key !== '' && ! isset($shopifyBySku[$key])) {
                    $shopifyBySku[$key] = $shopRow->image_src;
                }
            }
        }

        $productsBySku = [];
        if (Schema::hasTable('product_master')) {
            $products = DB::table('product_master')
                ->select('sku', 'Values', 'main_image', 'image1')
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $skus)
                ->get();
            foreach ($products as $product) {
                $productsBySku[strtoupper(trim((string) $product->sku))] = $product;
            }
        }

        $urls = [];
        foreach ($skus as $sku) {
            $product = $productsBySku[$sku] ?? null;
            $values = [];
            if ($product && is_string($product->Values ?? null) && trim($product->Values) !== '') {
                $decoded = json_decode($product->Values, true);
                if (is_array($decoded)) {
                    $values = $decoded;
                }
            }
            $url = $this->normalizeImage($shopifyBySku[$sku] ?? null)
                ?? $this->normalizeImage($values['image_path'] ?? null)
                ?? $this->normalizeImage($product?->main_image ?? null)
                ?? $this->normalizeImage($product?->image1 ?? null);
            if ($url !== null) {
                $urls[$sku] = $url;
            }
        }

        return $urls;
    }

    private function normalizeImage(mixed $path): ?string
    {
        $value = trim((string) ($path ?? ''));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(https?:)?\/\//i', $value) || str_starts_with($value, 'data:')) {
            return $value;
        }

        return '/'.ltrim($value, '/');
    }
}
