<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Http\Controllers\ProductMaster\ForecastAnalysisController;

class ForecastAnalysisBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'forecast-analysis';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        // No pre-sync needed — buildForecastAnalysisData loads everything.
    }

    /**
     * @return array<string, int|float>
     */
    public static function calculate(): array
    {
        return app(ForecastAnalysisController::class)->getForecastAnalysisBadgeTotals();
    }
}
