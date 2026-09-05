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
     *   parent_label: string,
     *   parent_sku: string|null,
     *   skus: list<string>,
     *   children: list<array{sku: string, parent: string, is_current: bool, variation_label: string}>
     * }
     */
    public static function forSku(string $sku): array
    {
        return self::forParent(self::parentKey($sku), $sku);
    }

    /**
     * Family for a chosen Product Master parent group.
     *
     * @return array{
     *   parent: string,
     *   parent_label: string,
     *   parent_sku: string|null,
     *   skus: list<string>,
     *   children: list<array{sku: string, parent: string, is_current: bool, variation_label: string}>
     * }
     */
    public static function forParent(string $parent, string $currentSku = ''): array
    {
        $parent = self::normalizeParentKey($parent);
        $currentSku = trim($currentSku);
        if ($parent === '' && $currentSku !== '') {
            $parent = self::parentKey($currentSku);
        }
        $skus = self::siblingSkus($parent, $currentSku);
        if ($currentSku !== '' && ! self::isParentSku($currentSku)) {
            $found = false;
            foreach ($skus as $child) {
                if (strcasecmp($child, $currentSku) === 0) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                array_unshift($skus, $currentSku);
            }
        }

        $children = [];
        foreach ($skus as $child) {
            $children[] = [
                'sku' => $child,
                'parent' => $parent,
                'is_current' => $currentSku !== '' && strcasecmp($child, $currentSku) === 0,
                'variation_label' => self::variationLabel($child, $parent),
            ];
        }

        return [
            'parent' => $parent,
            'parent_label' => $parent !== '' ? $parent : ($currentSku !== '' ? self::stripParentPrefix($currentSku) : ''),
            'parent_sku' => $parent !== '' ? self::parentRowSkuFromKey($parent) : self::parentRowSku($currentSku),
            'skus' => $skus,
            'children' => $children,
        ];
    }

    public static function normalizeParentKey(string $parent): string
    {
        return self::stripParentPrefix(trim($parent));
    }

    /**
     * Distinct Product Master parent groups for the variation picker.
     *
     * @return list<array{id: string, name: string, parent_sku: string, child_count: int}>
     */
    public static function parentGroups(string $q = '', int $limit = 400): array
    {
        if (! Schema::hasTable('product_master')) {
            return [];
        }

        $query = DB::table('product_master')
            ->whereNotNull('parent')
            ->whereRaw("TRIM(parent) != ''");
        if (Schema::hasColumn('product_master', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $q = trim($q);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('parent', 'like', $like)
                    ->orWhere('sku', 'like', $like);
            });
        }

        $rows = $query->select('parent', 'sku')->orderBy('parent')->limit(8000)->get();
        $groups = [];
        foreach ($rows as $row) {
            $key = self::normalizeParentKey((string) ($row->parent ?? ''));
            if ($key === '') {
                continue;
            }
            $sku = trim((string) ($row->sku ?? ''));
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'id' => $key,
                    'name' => $key,
                    'parent_sku' => 'PARENT '.$key,
                    'child_count' => 0,
                ];
            }
            if (self::isParentSku($sku)) {
                $groups[$key]['parent_sku'] = $sku;
            } elseif ($sku !== '') {
                $groups[$key]['child_count']++;
            }
        }

        $out = array_values($groups);
        usort($out, static fn ($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));

        return array_slice($out, 0, max(1, $limit));
    }

    public static function isParentSku(string $sku): bool
    {
        return stripos(trim($sku), 'PARENT') === 0;
    }

    public static function stripParentPrefix(string $sku): string
    {
        $sku = trim($sku);
        if (preg_match('/^PARENT(?:\s+PARENT)*\s+(.+)$/i', $sku, $m)) {
            $rest = trim($m[1]);
            if ($rest !== '') {
                return $rest;
            }
        }

        return $sku;
    }

    public static function parentKey(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('product_master')) {
            return self::stripParentPrefix($sku);
        }

        $row = DB::table('product_master')->where('sku', $sku)->first();
        $parent = trim((string) ($row->parent ?? ''));
        if ($parent !== '') {
            return self::isParentSku($parent) ? self::stripParentPrefix($parent) : $parent;
        }

        return self::stripParentPrefix($sku);
    }

    /**
     * PARENT summary row SKU for this family, if one exists in product_master.
     */
    public static function parentRowSku(string $sku): ?string
    {
        return self::parentRowSkuFromKey(self::parentKey($sku));
    }

    public static function parentRowSkuFromKey(string $parent): ?string
    {
        $parent = self::normalizeParentKey($parent);
        if ($parent === '' || ! Schema::hasTable('product_master')) {
            return null;
        }

        $upper = strtoupper($parent);
        $row = DB::table('product_master')
            ->where(function ($q) use ($parent, $upper) {
                $q->where('sku', 'PARENT '.$parent)
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', ['PARENT '.$upper])
                    ->orWhere(function ($q2) use ($parent) {
                        $q2->where('parent', $parent)
                            ->whereRaw("UPPER(TRIM(sku)) LIKE 'PARENT %'");
                    });
            })
            ->orderByRaw("CASE WHEN UPPER(TRIM(sku)) LIKE 'PARENT %' THEN 0 ELSE 1 END")
            ->first();

        $found = trim((string) ($row->sku ?? ''));

        return $found !== '' ? $found : null;
    }

    /**
     * Related SKUs to write when family sync checkboxes are on. Never includes $sku.
     *
     * @return list<string>
     */
    public static function syncTargetSkus(string $sku, bool $siblings, bool $parent): array
    {
        $sku = trim($sku);
        if ($sku === '' || (! $siblings && ! $parent)) {
            return [];
        }

        $targets = [];
        $isParentRow = self::isParentSku($sku);
        $family = self::forSku($sku);

        if ($siblings || ($parent && $isParentRow)) {
            foreach ($family['skus'] as $child) {
                if (strcasecmp($child, $sku) !== 0) {
                    $targets[] = $child;
                }
            }
        }

        if ($parent && ! $isParentRow) {
            $parentSku = self::parentRowSku($sku);
            if ($parentSku !== null && strcasecmp($parentSku, $sku) !== 0) {
                $targets[] = $parentSku;
            }
        }

        return array_values(array_unique($targets));
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
            $keys = array_values(array_unique(array_filter([
                $parent,
                self::stripParentPrefix($parent),
                'PARENT '.$parent,
                'PARENT '.self::stripParentPrefix($parent),
            ], static fn ($key) => trim((string) $key) !== '')));
            $query = DB::table('product_master')->whereIn('parent', $keys);
            if (Schema::hasColumn('product_master', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            $rows = $query->orderBy('sku')->pluck('sku');
            foreach ($rows as $child) {
                $child = trim((string) $child);
                if ($child === '' || self::isParentSku($child)) {
                    continue;
                }
                $skus[] = $child;
            }
        }

        if ($skus === [] && $fallbackSku !== '' && ! self::isParentSku($fallbackSku)) {
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
