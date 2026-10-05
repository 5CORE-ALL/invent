<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Support\Marketplace\LmpMissingChannelCounts;

class LmpMissingBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'lmp-missing';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * LMP M. total shown on the home dashboard. NR channels are left out.
     *
     * @return array{lmp_missing: int}
     */
    public static function calculate(): array
    {
        return [
            'lmp_missing' => LmpMissingChannelCounts::totalMissing(true),
        ];
    }
}
