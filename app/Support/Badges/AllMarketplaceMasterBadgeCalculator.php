<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Http\Controllers\Channels\ChannelMasterController;

class AllMarketplaceMasterBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'all-marketplace-master';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        // Uses pre-calculated channel_master_calculated_data when available.
    }

    /**
     * @return array<string, int|float|string|null>
     */
    public static function calculate(): array
    {
        return app(ChannelMasterController::class)->getAllMarketplaceMasterBadgeTotals();
    }
}
