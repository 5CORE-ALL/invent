<?php

namespace App\Support;

use App\Models\InstructionsItemPkg;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\SupplierPortalAsset;
use Illuminate\Support\Facades\Schema;

class SupplierPortalDimWtData
{
    public const CATEGORIES = [
        'inner_box_designs',
    ];

    public const COVER_CATEGORIES = [
        'inner_box_cover',
    ];

    public const SKU_CATEGORIES = [
        'assembly_designs',
        'operations_manual',
        'dos_and_donts',
    ];

    public static function usesDimWtGrid(string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
    }

    public static function usesDimWtCoverGrid(string $category): bool
    {
        return in_array($category, self::COVER_CATEGORIES, true);
    }

    public static function usesDimWtSkuGrid(string $category): bool
    {
        return in_array($category, self::SKU_CATEGORIES, true);
    }

    /**
     * Slim rows from /dim-wt-master: img, parent, sku, item pkg.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        if (! Schema::hasTable('product_master')) {
            return [];
        }

        $select = ['id', 'parent', 'sku', 'Values', 'main_image'];
        $products = ProductMaster::query()
            ->select($select)
            ->orderBy('parent')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku')
            ->get();

        $pkgById = collect();
        if (Schema::hasTable('instructions_item_pkg') && $products->isNotEmpty()) {
            $pkgById = InstructionsItemPkg::query()
                ->whereIn('product_master_id', $products->pluck('id'))
                ->get()
                ->keyBy('product_master_id');
        }

        $shopifyBySku = collect();
        if (Schema::hasTable('shopify_skus') && class_exists(ShopifySku::class)) {
            $shopifyBySku = ShopifySku::query()
                ->select(['sku', 'image_src'])
                ->get()
                ->keyBy(function ($item) {
                    return str_replace("\u{00a0}", ' ', trim((string) ($item->sku ?? '')));
                });
        }

        $filesByCatSku = self::filesByCategorySku();

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

            $normalizedSku = str_replace("\u{00a0}", ' ', $sku);
            $shopifyImage = optional($shopifyBySku->get($normalizedSku))->image_src ?? null;
            $localImage = trim((string) ($values['image_path'] ?? $product->main_image ?? ''));
            if ($shopifyImage) {
                $image = $shopifyImage;
            } elseif ($localImage !== '') {
                $image = str_starts_with($localImage, 'http') ? $localImage : '/'.ltrim($localImage, '/');
            } else {
                $image = null;
            }

            $pkg = $pkgById->get($product->id);
            $files = [];
            foreach (self::SKU_CATEGORIES as $cat) {
                $files[$cat] = $filesByCatSku[$cat][$normalizedSku] ?? [];
            }
            $out[] = [
                'id' => $product->id,
                'Parent' => trim((string) ($product->parent ?? '')),
                'SKU' => $sku,
                'image_path' => $image,
                'instructions_item_pkg' => $pkg && $pkg->instructions !== null
                    ? (string) $pkg->instructions
                    : '',
                'item_pkg_cover' => self::resolveCoverUrl($values),
                'files' => $files,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    public static function filesByCategorySku(): array
    {
        $out = [];
        foreach (self::SKU_CATEGORIES as $cat) {
            $out[$cat] = [];
        }
        if (! Schema::hasTable('supplier_portal_assets')) {
            return $out;
        }

        $assets = SupplierPortalAsset::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($assets as $asset) {
            $cat = SupplierPortalAsset::resolveCategoryKey((string) $asset->category);
            if ($cat === null || ! isset($out[$cat])) {
                continue;
            }
            $sku = str_replace("\u{00a0}", ' ', trim((string) ($asset->sku ?? '')));
            if ($sku === '') {
                continue;
            }
            $out[$cat][$sku][] = self::filePayload($asset);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function filePayload(SupplierPortalAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'title' => (string) $asset->title,
            'file_name' => (string) $asset->file_name,
            'url' => $asset->publicUrl(),
            'view' => route('supplier-portal.show', $asset, false),
            'download' => route('supplier-portal.download', $asset, false),
            'is_image' => $asset->isImage(),
            'ext' => $asset->extensionLabel(),
        ];
    }

    /**
     * Same source as /dim-wt-master Itm pkg Cover.
     *
     * @param  array<string, mixed>  $values
     */
    public static function resolveCoverUrl(array $values): ?string
    {
        $direct = trim((string) ($values['item_pkg_cover'] ?? ''));
        if ($direct !== '') {
            return self::publicFileUrl($direct);
        }

        $raw = $values['packing_images'] ?? [];
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $first = $raw[0] ?? null;
        if (is_string($first) && trim($first) !== '') {
            return self::publicFileUrl($first);
        }
        if (is_array($first) && ! empty($first['path'])) {
            return self::publicFileUrl((string) $first['path']);
        }

        return null;
    }

    public static function publicFileUrl(?string $path): ?string
    {
        $s = trim((string) $path);
        if ($s === '') {
            return null;
        }
        if (str_starts_with($s, 'http://') || str_starts_with($s, 'https://') || str_starts_with($s, 'data:')) {
            return $s;
        }

        return '/'.ltrim($s, '/');
    }
}
