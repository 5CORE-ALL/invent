<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DescriptionWithImagesFormatter
{
    /**
     * @param  array<int, array{title?: string, body?: string}>|null  $featureBoxes  Up to four title/body pairs for the 2×2 Features grid (Shopify rich layout).
     * @return array{html: string, text_html: string, images: array<int, string>}
     */
    public static function buildHtmlWithImages(
        string $descriptionPlain,
        string $identifier,
        ?string $skuHint = null,
        string $altText = 'Product Image',
        int $maxImages = 12,
        array $preferredImages = [],
        ?string $aboutItemBulletsPlain = null,
        ?array $featureBoxes = null,
        bool $shopifyRichLayout = false
    ): array {
        $descriptionPlain = trim($descriptionPlain);
        $images = self::normalizeProvidedImages($preferredImages, $maxImages);
        if ($images === []) {
            $images = self::resolveImageUrls($identifier, $skuHint, $maxImages);
        }

        $textInner = '<p>'.nl2br(htmlspecialchars($descriptionPlain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</p>';

        if ($shopifyRichLayout) {
            $aboutHtml = self::formatAboutItemHtml((string) ($aboutItemBulletsPlain ?? ''));
            $featuresHtml = self::buildFeaturesGridHtml(is_array($featureBoxes) ? $featureBoxes : []);

            $imageParts = [];
            foreach ($images as $idx => $url) {
                $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $n = $idx + 1;
                $safeAlt = htmlspecialchars(trim($altText) !== '' ? "{$altText} {$n}" : "Product Image {$n}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $imageParts[] = '<img src="'.$safeUrl.'" alt="'.$safeAlt.'" style="max-width:40%; height:auto; display:inline-block; margin:5px;">';
            }
            $imageSection = '';
            if ($imageParts !== []) {
                $imageSection = '<h3 style="margin-top:1.25em;">Images</h3>'
                    .'<div class="product-images" style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;">'
                    .implode("\n", $imageParts)
                    .'</div>';
            }

            $blocks = [];
            $blocks[] = '<div class="product-description" style="max-width:100%;">';
            if ($aboutHtml !== '') {
                $blocks[] = '<h3 style="margin-top:0;">About Item</h3>';
                $blocks[] = '<div class="about-item" style="margin:15px 0; line-height:1.6;">'.$aboutHtml.'</div>';
            }
            $blocks[] = '<h3 style="margin-top:1.25em;">Product Description</h3>';
            $blocks[] = '<div class="product-text" style="margin-bottom:1em;">'.$textInner.'</div>';
            if ($featuresHtml !== '') {
                $blocks[] = $featuresHtml;
            }
            if ($imageSection !== '') {
                $blocks[] = $imageSection;
            }
            $blocks[] = '</div>';
            $html = implode("\n", $blocks);

            Log::info('DescriptionWithImagesFormatter: Shopify rich layout built', [
                'identifier' => $identifier,
                'has_about_item' => $aboutHtml !== '',
                'has_features_grid' => $featuresHtml !== '',
                'image_count' => count($images),
                'html_length' => strlen($html),
            ]);

            return ['html' => $html, 'text_html' => $textInner, 'images' => $images];
        }

        if ($images === []) {
            $html = '<div class="product-description"><div class="product-text">'.$textInner.'</div></div>';

            return ['html' => $html, 'text_html' => $textInner, 'images' => []];
        }

        $imageParts = [];
        foreach ($images as $idx => $url) {
            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $n = $idx + 1;
            $safeAlt = htmlspecialchars(trim($altText) !== '' ? "{$altText} {$n}" : "Product Image {$n}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $imageParts[] = '<img src="'.$safeUrl.'" alt="'.$safeAlt.'" style="max-width:40%; height:auto; display:inline-block; margin:5px;">';
        }
        $imageBlock = '<div class="product-images" style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;">'.implode("\n", $imageParts).'</div>';
        $html = '<div class="product-description"><div class="product-text">'.$textInner.'</div>'.$imageBlock.'</div>';

        Log::debug('DescriptionWithImagesFormatter: standard layout built', [
            'identifier' => $identifier,
            'image_count' => count($images),
        ]);

        return ['html' => $html, 'text_html' => $textInner, 'images' => $images];
    }

    /**
     * Bullet / about lines: supports lines like **LABEL** - body text (markdown-style bold lead-in).
     */
    public static function formatAboutItemHtml(string $bulletPlain): string
    {
        $bulletPlain = trim($bulletPlain);
        if ($bulletPlain === '') {
            return '';
        }
        $lines = preg_split('/\r\n|\r|\n/', $bulletPlain);
        $parts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\*\*(.+?)\*\*\s*-\s*(.+)$/s', $line, $m)) {
                $label = htmlspecialchars(trim($m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $rest = htmlspecialchars(trim($m[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $parts[] = '<p style="margin:0 0 10px 0;"><strong>'.$label.'</strong> - '.$rest.'</p>';
            } else {
                $parts[] = '<p style="margin:0 0 10px 0;">'.nl2br(htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</p>';
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param  array<int, array{title?: string, body?: string}>  $boxes
     */
    public static function buildFeaturesGridHtml(array $boxes): string
    {
        $normalized = [];
        foreach (array_slice($boxes, 0, 4) as $b) {
            if (! is_array($b)) {
                continue;
            }
            $t = trim((string) ($b['title'] ?? ''));
            $body = trim((string) ($b['body'] ?? ''));
            if ($t === '' && $body === '') {
                continue;
            }
            $normalized[] = ['title' => $t, 'body' => $body];
        }
        if ($normalized === []) {
            return '';
        }

        $out = '<h3 style="margin-top:1.25em;">Features</h3>';
        $out .= '<div class="features-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin:20px 0;">';
        foreach ($normalized as $box) {
            $t = htmlspecialchars($box['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $body = htmlspecialchars($box['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $out .= '<div class="feature-box" style="border:1px solid #ddd; border-radius:8px; padding:15px; background:#f9f9f9;">';
            $out .= '<h4 style="margin:0 0 10px 0; color:#333; font-weight:bold;">'.$t.'</h4>';
            $out .= '<p style="margin:0; line-height:1.5;">'.$body.'</p>';
            $out .= '</div>';
        }
        $out .= '</div>';

        return $out;
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
