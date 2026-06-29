<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;

class BadgeCalculatorRegistry
{
    /**
     * Register every page badge calculator here. Add a new class when a page
     * needs its toolbar badges snapshotted into badges_data.
     *
     * @return list<class-string<PageBadgeCalculator>>
     */
    public static function all(): array
    {
        return [
            OnSeaTransitBadgeCalculator::class,
            ForecastAnalysisBadgeCalculator::class,
            AllMarketplaceMasterBadgeCalculator::class,
        ];
    }

    /**
     * @return class-string<PageBadgeCalculator>|null
     */
    public static function find(string $pageName): ?string
    {
        foreach (self::all() as $calculatorClass) {
            if ($calculatorClass::pageName() === $pageName) {
                return $calculatorClass;
            }
        }

        return null;
    }
}
