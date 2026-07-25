<?php

namespace App\Http\Controllers\ProductMaster\Concerns;

use App\Models\ProductMaster;
use App\Services\Support\ProductDescriptionV2HtmlBuilder;
use Illuminate\Support\Facades\Schema;

/**
 * Shared Product Master fields used by:
 * - Description For HTML  (description_html / description_1500 / product_description)
 * - Images A+ Content     (description_v2_images)
 * - A+ Content            (both)
 */
trait SyncsAPlusContentFields
{
    protected const APLUS_MAX_IMAGES = 12;

    protected function resolveDescriptionForHtml(ProductMaster $product): string
    {
        $htmlCol = (string) ($product->description_html ?? '');
        if (trim($htmlCol) !== '') {
            return $htmlCol;
        }

        $d1500 = (string) ($product->description_1500 ?? '');
        if (trim($d1500) !== '') {
            return $d1500;
        }

        return (string) ($product->product_description ?? '');
    }

    /**
     * Build combined HTML preview from Bullet Points, Description, Features,
     * Specifications, Package Includes, About Us (+ A+ images when present).
     *
     * @return array{html: string, sections: array<string, bool>}
     */
    protected function buildCompositeHtmlPreview(ProductMaster $product): array
    {
        $bullets = $this->resolvePreviewBullets($product);
        $images = $this->normalizeAPlusImages($product->description_v2_images ?? null);
        $description = $this->resolvePreviewDescription($product);
        $features = $this->resolvePreviewFeatures($product);
        $specs = $this->normalizePreviewSpecs($product->description_v2_specifications ?? null);
        $package = $this->resolvePreviewPackage($product);
        $about = (string) ($product->description_v2_brand ?? '');

        $built = ProductDescriptionV2HtmlBuilder::build(
            $bullets,
            $images,
            $description,
            $features,
            $specs,
            $package,
            $about,
            'Specifications',
            'About Us',
            true, // images first for A+ Content preview
            'Bullet Points',
        );

        return [
            'html' => $built['html'],
            'sections' => [
                'bullet_points' => $bullets !== [],
                'description' => trim($description) !== '',
                'features' => $this->featuresHaveContent($features),
                'specifications' => $specs !== [],
                'package_includes' => trim($package) !== '',
                'about_us' => trim($about) !== '',
                'images' => $images !== [],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    protected function resolvePreviewBullets(ProductMaster $product): array
    {
        $v2 = trim((string) ($product->description_v2_bullets ?? ''));
        if ($v2 !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $v2) ?: [])));
        }

        $out = [];
        foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
            $line = trim((string) ($product->{$col} ?? ''));
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    protected function resolvePreviewDescription(ProductMaster $product): string
    {
        // Prefer plain/structured description text for the composite HTML body.
        $v2 = trim((string) ($product->description_v2_description ?? ''));
        if ($v2 !== '') {
            return $v2;
        }

        $html = $this->resolveDescriptionForHtml($product);
        if ($html === '') {
            return '';
        }

        // If stored as HTML, strip tags for the structured builder body.
        if (str_contains($html, '<')) {
            return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $html;
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    protected function resolvePreviewFeatures(ProductMaster $product): array
    {
        $raw = $product->description_v2_features ?? null;
        if (is_array($raw) && $raw !== []) {
            $out = [];
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                $body = trim((string) ($row['body'] ?? ''));
                if ($title === '' && $body === '') {
                    continue;
                }
                $out[] = ['title' => $title, 'body' => $body];
            }
            if ($out !== []) {
                return $out;
            }
        }

        $out = [];
        foreach (['feature1', 'feature2', 'feature3', 'feature4'] as $col) {
            $body = trim((string) ($product->{$col} ?? ''));
            if ($body !== '') {
                $out[] = ['title' => '', 'body' => $body];
            }
        }

        return $out;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{key: string, value: string}>
     */
    protected function normalizePreviewSpecs($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? $row['Key'] ?? ''));
            $value = trim((string) ($row['value'] ?? $row['Value'] ?? ''));
            if ($key === '' && $value === '') {
                continue;
            }
            $out[] = ['key' => $key, 'value' => $value];
        }

        return $out;
    }

    protected function resolvePreviewPackage(ProductMaster $product): string
    {
        $v2 = trim((string) ($product->description_v2_package ?? ''));
        if ($v2 !== '') {
            return $v2;
        }

        $values = is_array($product->Values) ? $product->Values : [];
        if (! is_array($values) && is_string($product->Values)) {
            $decoded = json_decode($product->Values, true);
            $values = is_array($decoded) ? $decoded : [];
        }

        $lines = [];
        for ($i = 1; $i <= 10; $i++) {
            $item = trim((string) ($values['item'.$i] ?? ''));
            if ($item !== '') {
                $lines[] = $item;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{title?: string, body?: string}>  $features
     */
    protected function featuresHaveContent(array $features): bool
    {
        foreach ($features as $f) {
            if (trim((string) ($f['title'] ?? '')) !== '' || trim((string) ($f['body'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    protected function normalizeAPlusImages($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            $out[] = $url;
            if (count($out) >= self::APLUS_MAX_IMAGES) {
                break;
            }
        }

        return array_values($out);
    }

    protected function syncDescriptionHtmlFields(ProductMaster $product, string $html): void
    {
        if (Schema::hasColumn('product_master', 'description_html')) {
            $product->description_html = $html;
        }
        $product->description_1500 = $html;
        $product->product_description = $html;
    }

    /**
     * @param  list<string>  $images
     */
    protected function syncAPlusImageFields(ProductMaster $product, array $images): void
    {
        if (! Schema::hasColumn('product_master', 'description_v2_images')) {
            return;
        }

        $images = $this->normalizeAPlusImages($images);
        $product->description_v2_images = $images === [] ? null : $images;
    }

    /**
     * @param  list<string>|null  $images
     * @return array{
     *   description_html: string,
     *   description_for_html: string,
     *   description_1500: string|null,
     *   product_description: string|null,
     *   description_v2_images: list<string>,
     *   aplus_images: list<string>,
     *   aplus_image_count: int
     * }
     */
    protected function aPlusSyncedPayload(ProductMaster $product, ?array $images = null): array
    {
        $html = $this->resolveDescriptionForHtml($product);
        $imgs = $images ?? $this->normalizeAPlusImages($product->description_v2_images ?? null);

        return [
            'description_html' => (string) ($product->description_html ?? ''),
            'description_for_html' => $html,
            'description_1500' => $product->description_1500,
            'product_description' => $product->product_description,
            'description_v2_images' => $imgs,
            'aplus_images' => $imgs,
            'aplus_image_count' => count($imgs),
        ];
    }
}
