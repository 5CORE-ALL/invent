<?php

namespace App\Http\Controllers\ProductMaster\Concerns;

use App\Models\ProductMaster;
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
