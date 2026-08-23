<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Models\ProductRawImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RawImagesBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'raw-images';

    public static function pageName(): string
    {
        return static::PAGE_NAME;
    }

    protected static function kind(): string
    {
        return ProductRawImage::KIND_RAW;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * Mirrors updateCounts() in raw-images.blade.php (non-PARENT SKUs only).
     * Missing excludes SKUs with 0 / empty inventory.
     *
     * @return array{sku_count: int, with_raw_image: int, image: int, missing: int}
     */
    public static function calculate(): array
    {
        $defaults = [
            'sku_count' => 0,
            'with_raw_image' => 0,
            'image' => 0,
            'missing' => 0,
        ];

        if (! Schema::hasTable('product_master')) {
            return $defaults;
        }

        $productSkus = DB::table('product_master')
            ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'")
            ->pluck('sku');

        $skuCount = $productSkus->count();
        if ($skuCount === 0) {
            return $defaults;
        }

        $rawSet = [];
        $availableSet = [];
        if (Schema::hasTable('product_raw_images')) {
            $kinds = [static::kind()];
            if (Schema::hasColumn('product_raw_images', 'kind')) {
                $kinds[] = ProductRawImage::aiKindFor(static::kind());
            }
            $rowsQuery = DB::table('product_raw_images')->select('sku');
            if (Schema::hasColumn('product_raw_images', 'kind')) {
                $rowsQuery->addSelect('kind')->whereIn('kind', $kinds);
            }
            foreach ($rowsQuery->get() as $row) {
                $norm = self::normalizeSku((string) $row->sku);
                if ($norm === '') {
                    continue;
                }
                $availableSet[$norm] = true;
                $rowKind = (string) ($row->kind ?? static::kind());
                if ($rowKind === static::kind()) {
                    $rawSet[$norm] = true;
                }
            }
        }

        $invByNorm = [];
        if (Schema::hasTable('shopify_skus')) {
            foreach (DB::table('shopify_skus')->select('sku', 'inv')->get() as $row) {
                $norm = self::normalizeSku((string) $row->sku);
                if ($norm === '' || isset($invByNorm[$norm])) {
                    continue;
                }
                $invByNorm[$norm] = (float) ($row->inv ?? 0);
            }
        }

        $withRaw = 0;
        $available = 0;
        $missing = 0;
        foreach ($productSkus as $sku) {
            $norm = self::normalizeSku((string) $sku);
            $hasRaw = $norm !== '' && isset($rawSet[$norm]);
            $hasImage = $norm !== '' && isset($availableSet[$norm]);
            if ($hasRaw) {
                $withRaw++;
            }
            if ($hasImage) {
                $available++;
            }
            $inv = $invByNorm[$norm] ?? 0;
            if ($inv > 0 && ! $hasRaw) {
                $missing++;
            }
        }

        return [
            'sku_count' => $skuCount,
            'with_raw_image' => $withRaw,
            'image' => $available,
            'missing' => $missing,
        ];
    }

    private static function normalizeSku(?string $sku): string
    {
        return trim(str_replace("\xc2\xa0", ' ', (string) $sku));
    }
}
