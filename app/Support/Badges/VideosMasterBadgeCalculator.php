<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VideosMasterBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'videos-master';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * Mirrors updateCounts() in videos-master.blade.php (non-PARENT SKUs only).
     *
     * @return array{
     *     sku_count: int,
     *     missing_po: int,
     *     missing_shop: int,
     *     missing_howto: int,
     *     missing_setup: int,
     *     missing_ts: int,
     *     missing_bs: int,
     *     missing_pb: int
     * }
     */
    public static function calculate(): array
    {
        $defaults = [
            'sku_count' => 0,
            'missing_po' => 0,
            'missing_shop' => 0,
            'missing_howto' => 0,
            'missing_setup' => 0,
            'missing_ts' => 0,
            'missing_bs' => 0,
            'missing_pb' => 0,
        ];

        if (! Schema::hasTable('product_master')) {
            return $defaults;
        }

        $base = DB::table('product_master')
            ->whereRaw("UPPER(sku) NOT LIKE '%PARENT%'");

        $defaults['sku_count'] = (int) (clone $base)->count();

        $columns = [
            'missing_po' => 'video_product_overview',
            'missing_shop' => 'video_unboxing',
            'missing_howto' => 'video_how_to',
            'missing_setup' => 'video_setup',
            'missing_ts' => 'video_troubleshooting',
            'missing_bs' => 'video_brand_story',
            'missing_pb' => 'video_product_benefits',
        ];

        foreach ($columns as $key => $column) {
            if (! Schema::hasColumn('product_master', $column)) {
                continue;
            }
            $defaults[$key] = (int) (clone $base)
                ->where(function ($q) use ($column) {
                    $q->whereNull($column)->orWhere($column, '');
                })
                ->count();
        }

        return $defaults;
    }
}
