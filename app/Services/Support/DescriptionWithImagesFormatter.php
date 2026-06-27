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

        $textInner = self::formatDescriptionInnerHtml($descriptionPlain);

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
                $imageSection = '<div class="product-images" style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;">'
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
     * Plain-text length for char limits (strips editor / listing HTML).
     */
    public static function plainTextFromDescription(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/<[^>]+>/', $text)) {
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Editor or fetched listing body → inner HTML for the Product Description block (no double-escape).
     */
    public static function formatDescriptionInnerHtml(string $description): string
    {
        $description = trim($description);
        if ($description === '') {
            return '<p></p>';
        }

        $body = self::extractEditorDescriptionBody($description);

        if (! preg_match('/<[^>]+>/', $body)) {
            return '<p>'.nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</p>';
        }

        return self::sanitizeEditorHtml($body);
    }

    /**
     * Pull description copy out of a full listing body or unified Shopify layout.
     */
    public static function extractEditorDescriptionBody(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (preg_match('/<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bproduct-text\b[^"\']*["\'][^>]*>(.*)/is', $html, $m)) {
            $inner = trim($m[1]);
            if (preg_match('/\A(.*?)(?=<\/div>\s*(?:<h3\b|<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bfeatures-grid\b|$))/is', $inner, $section)) {
                return trim($section[1]);
            }

            return $inner;
        }

        if (preg_match('/<h[1-6]\b[^>]*>\s*Product\s+Description\s*<\/h[1-6]>\s*(.*)/is', $html, $m)) {
            $after = trim($m[1]);
            if (preg_match('/\A(.*?)(?=<h[1-6]\b|<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bfeatures-grid\b|<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bproduct-images\b|$)/is', $after, $section)) {
                return trim($section[1]);
            }

            return $after;
        }

        $stripped = ShopifyBulletPointsFormatter::removeAboutItemBlock($html);

        return trim($stripped);
    }

    private static function sanitizeEditorHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;

        return trim($html);
    }

    /**
     * Convert editor HTML to bullet/feature lines (Wayfair key features, plain newline bodies).
     * Preserves inline tags such as strong/em within each line.
     *
     * @return list<string>
     */
    public static function htmlToFeatureLines(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        if (! preg_match('/<[^>]+>/', $html)) {
            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $html))));
        }

        $body = self::extractEditorDescriptionBody($html);

        if (preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $body, $matches)) {
            $lines = [];
            foreach ($matches[1] as $item) {
                $line = trim(self::sanitizeEditorHtml($item));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
            if ($lines !== []) {
                return $lines;
            }
        }

        if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $body, $matches)) {
            $lines = [];
            foreach ($matches[1] as $item) {
                $line = trim(self::sanitizeEditorHtml($item));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
            if ($lines !== []) {
                return $lines;
            }
        }

        $withBreaks = preg_replace('/<br\s*\/?>/i', "\n", $body) ?? $body;
        $plain = self::plainTextFromDescription($withBreaks);
        if ($plain === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $plain))));
    }

    /**
     * Plain newline-separated lines → HTML list for the TinyMCE editor.
     */
    public static function linesToEditorHtml(string $text): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($text)))));
        if ($lines === []) {
            return '';
        }

        $items = array_map(
            static fn (string $line) => '<li>'.($line).'</li>',
            $lines
        );

        return '<ul>'.implode('', $items).'</ul>';
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
            $line = ShopifyBulletPointsFormatter::cleanBulletLine((string) $line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\*\*(.+?)\*\*\s*-\s*(.+)$/s', $line, $m)) {
                $label = htmlspecialchars(trim($m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $rest = htmlspecialchars(trim($m[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $parts[] = '<p style="margin:0 0 10px 0;"><strong>'.$label.'</strong> - '.$rest.'</p>';

                continue;
            }
            $dashPos = mb_strpos($line, ' - ');
            if ($dashPos !== false && $dashPos > 0 && $dashPos < mb_strlen($line) - 3) {
                $label = trim(mb_substr($line, 0, $dashPos));
                $rest = trim(mb_substr($line, $dashPos + 3));
                if ($label !== '' && $rest !== '' && mb_strlen($label) <= 120) {
                    $parts[] = '<p style="margin:0 0 10px 0;"><strong>'.htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</strong> - '.htmlspecialchars($rest, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';

                    continue;
                }
            }
            $parts[] = '<p style="margin:0 0 10px 0;">'.nl2br(htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false).'</p>';
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
