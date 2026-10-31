<?php

namespace App\Support\Ace;

use App\Http\Controllers\AmazonAds\AmazonFbmTargetingController;
use App\Http\Controllers\AmazonAdsMissingController;

/**
 * Pages whose badge numbers the SERVER can compute. Badge rules on these pages are checked by the daily
 * scheduler run (and right when a rule is created) - nobody has to have the page open. Badge rules on
 * every other page are still read from the browser while the page is open.
 */
class AceBadgeProviders
{
    /** @return array<string, callable(): list<array{key: string, label: string, value: int|float}>> page key => provider */
    private static function providers(): array
    {
        return [
            AcePages::pageKey('amazon-ads/fbm-targeting') => fn () => app(AmazonFbmTargetingController::class)->aceBadgeValues(),
            AcePages::pageKey('amazon-ads/missing') => fn () => app(AmazonAdsMissingController::class)->aceBadgeValues(),
        ];
    }

    public static function has(string $page): bool
    {
        return isset(self::providers()[$page]);
    }

    /** @return list<array{key: string, label: string, value: int|float}>|null */
    public static function values(string $page): ?array
    {
        $fn = self::providers()[$page] ?? null;

        return $fn ? $fn() : null;
    }
}
