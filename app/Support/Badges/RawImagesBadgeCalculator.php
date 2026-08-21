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
     *
     * @return array{sku_count: int, with_raw_image: int, missing: int}
     */
    public static function calculate(): array
    {
        $defaults = [
            'sku_count' => 0,
            'with_raw_image' => 0,
            'missing' => 0,
        ];

        if (! Schema::hasTable('product_master')) {
            return $defaults;
        }

        $skuCount = (int) DB::table('product_master')
            ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'")
            ->count();

        $withRaw = 0;
        if (Schema::hasTable('product_raw_images')) {
            $skusQuery = DB::table('product_raw_images')->distinct();
            if (Schema::hasColumn('product_raw_images', 'kind')) {
                $skusQuery->where('kind', static::kind());
            }
            $skusWithRaw = $skusQuery->pluck('sku');
            $withRaw = (int) DB::table('product_master')
                ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'")
                ->whereIn('sku', $skusWithRaw)
                ->count();
        }

        return [
            'sku_count' => $skuCount,
            'with_raw_image' => $withRaw,
            'missing' => max(0, $skuCount - $withRaw),
        ];
    }
}
