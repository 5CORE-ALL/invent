<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Models\VideoAdsMaster;
use Illuminate\Support\Facades\Schema;

class VideoAdsMasterBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'video-ads-master';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * Mirrors updateCount() in video-ads-master.blade.php.
     *
     * @return array{
     *     required: int,
     *     sku: int,
     *     parent: int,
     *     group: int,
     *     available: int,
     *     missing: int
     * }
     */
    public static function calculate(): array
    {
        $defaults = [
            'required' => 0,
            'sku' => 0,
            'parent' => 0,
            'group' => 0,
            'available' => 0,
            'missing' => 0,
        ];

        if (! Schema::hasTable('video_ads_master')) {
            return $defaults;
        }

        $rows = VideoAdsMaster::query()->get(['target_type', 'link']);
        $required = $rows->count();
        $sku = 0;
        $parent = 0;
        $group = 0;
        $available = 0;

        foreach ($rows as $row) {
            $t = strtolower((string) ($row->target_type ?? ''));
            if ($t === 'sku') {
                $sku++;
            } elseif ($t === 'parent') {
                $parent++;
            } elseif ($t === 'group') {
                $group++;
            }

            if (self::isLikelyUrl(self::normalizeUrl($row->link))) {
                $available++;
            }
        }

        return [
            'required' => $required,
            'sku' => $sku,
            'parent' => $parent,
            'group' => $group,
            'available' => $available,
            'missing' => max(0, $required - $available),
        ];
    }

    private static function normalizeUrl(?string $value): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $v)) {
            return $v;
        }
        if (str_starts_with($v, '//')) {
            return 'https:'.$v;
        }
        if (preg_match('#^www\.#i', $v)) {
            return 'https://'.$v;
        }

        return $v;
    }

    private static function isLikelyUrl(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return (bool) preg_match('#^(https?:)?//#i', $value) || (bool) preg_match('#^www\.#i', $value);
    }
}
