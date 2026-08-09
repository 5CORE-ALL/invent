<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VideoMasterBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'video-master';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * Mirrors Video Master rowCountBadge (+ how many SKUs have at least one video slot).
     *
     * @return array{products: int, with_video: int, missing_video: int}
     */
    public static function calculate(): array
    {
        if (! Schema::hasTable('product_master')) {
            return [
                'products' => 0,
                'with_video' => 0,
                'missing_video' => 0,
            ];
        }

        $products = (int) DB::table('product_master')->count();

        $videoCols = [];
        for ($i = 1; $i <= 10; $i++) {
            $col = 'video'.$i;
            if (Schema::hasColumn('product_master', $col)) {
                $videoCols[] = $col;
            }
        }
        if (Schema::hasColumn('product_master', 'main_video')) {
            $videoCols[] = 'main_video';
        }

        $withVideo = 0;
        if ($videoCols !== []) {
            $withVideo = (int) DB::table('product_master')
                ->where(function ($q) use ($videoCols) {
                    foreach ($videoCols as $col) {
                        $q->orWhere(function ($inner) use ($col) {
                            $inner->whereNotNull($col)->where($col, '!=', '');
                        });
                    }
                })
                ->count();
        }

        return [
            'products' => $products,
            'with_video' => $withVideo,
            'missing_video' => max(0, $products - $withVideo),
        ];
    }
}
