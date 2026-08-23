<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parent / sibling SKUs from product_master.parent.
 */
class ListingManagerFamily
{
    /**
     * @return array{
     *   parent: string,
     *   skus: list<string>,
     *   children: list<array{sku: string, parent: string, is_current: bool, variation_label: string}>
     * }
     */
    public static function forSku(string $sku): array
    {
        $sku = trim($sku);
        $parent = self::parentKey($sku);
        $skus = self::siblingSkus($parent, $sku);

        $children = [];
        foreach ($skus as $child) {
            $children[] = [
                'sku' => $child,
                'parent' => $parent,
                'is_current' => strcasecmp($child, $sku) === 0,
                'variation_label' => self::variationLabel($child, $parent),
            ];
        }

        return [
            'parent' => $parent,
            'skus' => $skus,
            'children' => $children,
        ];
    }

    public static function parentKey(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('product_master')) {
            return $sku;
        }

        $row = DB::table('product_master')->where('sku', $sku)->first();
        $parent = trim((string) ($row->parent ?? ''));
        if ($parent !== '') {
            return $parent;
        }

        return $sku;
    }

    /**
     * @return list<string>
     */
    public static function siblingSkus(string $parent, string $fallbackSku = ''): array
    {
        $parent = trim($parent);
        $fallbackSku = trim($fallbackSku);
        $skus = [];

        if ($parent !== '' && Schema::hasTable('product_master')) {
            $query = DB::table('product_master')->where('parent', $parent);
            if (Schema::hasColumn('product_master', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            $rows = $query->orderBy('sku')->pluck('sku');
            foreach ($rows as $child) {
                $child = trim((string) $child);
                if ($child === '' || stripos($child, 'PARENT') === 0) {
                    continue;
                }
                $skus[] = $child;
            }
        }

        if ($skus === [] && $fallbackSku !== '' && stripos($fallbackSku, 'PARENT') !== 0) {
            $skus[] = $fallbackSku;
        }

        return array_values(array_unique($skus));
    }

    public static function variationLabel(string $sku, string $parent = ''): string
    {
        $sku = trim($sku);
        $parent = trim($parent);
        if ($sku === '') {
            return 'Variation';
        }

        $rest = $parent !== '' && strncasecmp($sku, $parent, strlen($parent)) === 0
            ? trim(substr($sku, strlen($parent)))
            : '';
        if ($rest !== '' && $rest !== $sku) {
            return ltrim($rest, "-_ \t");
        }

        if (preg_match('/(\d+\s*(?:pcs?|pieces?|pack|pk))\s*$/i', $sku, $m)) {
            return trim($m[1]);
        }

        return $sku;
    }
}
