<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DescriptionWithImagesFormatter
{
    /**
     * @return array{html: string, text_html: string, images: array<int, string>}
     */
    public static function buildHtmlWithImages(
        string $descriptionPlain,
        string $identifier,
        ?string $skuHint = null,
        string $altText = 'Product Image',
        int $maxImages = 12,
        array $preferredImages = []
    ): array {
        $descriptionPlain = trim($descriptionPlain);
        $images = self::normalizeProvidedImages($preferredImages, $maxImages);
        if ($images === []) {
            $images = self::resolveImageUrls($identifier, $skuHint, $maxImages);
        }

        $textInner = '<p>'.nl2br(htmlspecialchars($descriptionPlain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</p>';

        if ($images === []) {
            $html = '<div class="product-description"><div class="product-text">'.$textInner.'</div></div>';

            return ['html' => $html, 'text_html' => $textInner, 'images' => []];
        }

        $imageParts = [];
        foreach ($images as $idx => $url) {
            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $n = $idx + 1;
            $safeAlt = htmlspecialchars(trim($altText) !== '' ? "{$altText} {$n}" : "Product Image {$n}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $imageParts[] = '<img src="'.$safeUrl.'" alt="'.$safeAlt.'" style="max-width:100%; height:auto;">';
        }
        $imageBlock = '<div class="product-images">'.implode("\n", $imageParts).'</div>';
        $html = '<div class="product-description"><div class="product-text">'.$textInner.'</div>'.$imageBlock.'</div>';

        return ['html' => $html, 'text_html' => $textInner, 'images' => $images];
    }

    /**
     * @return array<int, string>
     */
    public static function resolveImageUrls(string $identifier, ?string $skuHint = null, int $maxImages = 12): array
    {
        $maxImages = max(1, min(20, (int) $maxImages));
        $candidates = self::candidateIdentifiers($identifier, $skuHint);
        $urls = self::fromProductMaster($candidates, $maxImages);
        if ($urls !== []) {
            return $urls;
        }

        return self::fromShopifyCatalog($candidates, $maxImages);
    }

    /**
     * @param  array<int, string>  $candidates
     * @return array<int, string>
     */
    private static function fromProductMaster(array $candidates, int $maxImages): array
    {
        if (! Schema::hasTable('product_master')) {
            return [];
        }
        if (! Schema::hasColumn('product_master', 'sku') && ! Schema::hasColumn('product_master', 'parent')) {
            return [];
        }

        $query = DB::table('product_master');
        $query->where(function ($q) use ($candidates) {
            foreach ($candidates as $candidate) {
                if (Schema::hasColumn('product_master', 'sku')) {
                    $q->orWhere('sku', $candidate);
                }
                if (Schema::hasColumn('product_master', 'parent')) {
                    $q->orWhere('parent', $candidate);
                }
            }
        });

        $row = $query->first();
        if (! $row) {
            return [];
        }

        $imageColumns = [
            'image_path',
            'main_image',
            'image1', 'image2', 'image3', 'image4', 'image5', 'image6',
            'image7', 'image8', 'image9', 'image10', 'image11', 'image12',
        ];

        $urls = [];
        $seen = [];
        foreach ($imageColumns as $column) {
            if (! Schema::hasColumn('product_master', $column)) {
                continue;
            }
            $raw = trim((string) ($row->{$column} ?? ''));
            if ($raw === '') {
                continue;
            }
            $url = self::normalizeImageUrl($raw);
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $urls[] = $url;
            $seen[$url] = true;
            if (count($urls) >= $maxImages) {
                break;
            }
        }

        return $urls;
    }

    /**
     * @param  array<int, string>  $candidates
     * @return array<int, string>
     */
    private static function fromShopifyCatalog(array $candidates, int $maxImages): array
    {
        if (! Schema::hasTable('shopify_catalog_variants') || ! Schema::hasTable('shopify_catalog_products')) {
            return [];
        }
        if (! Schema::hasColumn('shopify_catalog_variants', 'sku')
            || ! Schema::hasColumn('shopify_catalog_variants', 'shopify_catalog_product_id')) {
            return [];
        }

        $variant = DB::table('shopify_catalog_variants')
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $q->orWhere('sku', $candidate);
                }
            })
            ->first();
        if (! $variant) {
            return [];
        }

        $product = DB::table('shopify_catalog_products')
            ->where('id', $variant->shopify_catalog_product_id)
            ->first();
        if (! $product) {
            return [];
        }

        $urls = [];
        $seen = [];
        $add = function (string $raw) use (&$urls, &$seen, $maxImages): void {
            $url = self::normalizeImageUrl($raw);
            if ($url === '' || isset($seen[$url])) {
                return;
            }
            $urls[] = $url;
            $seen[$url] = true;
        };

        if (Schema::hasColumn('shopify_catalog_products', 'images')) {
            $decoded = json_decode((string) ($product->images ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item)) {
                        $add($item);
                    }
                    if (count($urls) >= $maxImages) {
                        break;
                    }
                }
            }
        }

        if (count($urls) < $maxImages && Schema::hasColumn('shopify_catalog_products', 'image_urls')) {
            $decoded = json_decode((string) ($product->image_urls ?? ''), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item)) {
                        $add($item);
                    }
                    if (count($urls) >= $maxImages) {
                        break;
                    }
                }
            }
        }

        if (count($urls) < $maxImages && Schema::hasColumn('shopify_catalog_products', 'image_src')) {
            $add((string) ($product->image_src ?? ''));
        }

        return array_slice($urls, 0, $maxImages);
    }

    /**
     * @return array<int, string>
     */
    private static function candidateIdentifiers(string $identifier, ?string $skuHint = null): array
    {
        $items = array_values(array_filter([
            trim($identifier),
            trim((string) $skuHint),
        ], fn ($v) => $v !== ''));

        $candidates = [];
        foreach ($items as $item) {
            $candidates[] = $item;
            $candidates[] = strtoupper($item);
            $candidates[] = strtolower($item);
        }

        return array_values(array_unique($candidates));
    }

    private static function normalizeImageUrl(string $raw): string
    {
        $raw = trim(str_replace('\\', '/', $raw));
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, '//')) {
            return 'https:'.$raw;
        }
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        $base = rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            return '';
        }

        return $base.'/'.ltrim($raw, '/');
    }

    /**
     * @param  array<int, mixed>  $images
     * @return array<int, string>
     */
    private static function normalizeProvidedImages(array $images, int $maxImages): array
    {
        $urls = [];
        $seen = [];
        foreach ($images as $item) {
            if (! is_string($item)) {
                continue;
            }
            $url = self::normalizeImageUrl($item);
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $urls[] = $url;
            $seen[$url] = true;
            if (count($urls) >= $maxImages) {
                break;
            }
        }

        return $urls;
    }
}
