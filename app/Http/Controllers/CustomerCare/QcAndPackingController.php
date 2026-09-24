<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QcAndPackingController extends Controller
{
    /**
     * Add image_url onto QC rows. Same sources as SKU lookup:
     * Shopify image, then product_master image_path, main_image, and image1.
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

        foreach ($rows as $i => $row) {
            $key = strtoupper(trim((string) ($row['sku'] ?? '')));
            $rows[$i]['image_url'] = $urls[$key] ?? null;
        }

        return $rows;
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
