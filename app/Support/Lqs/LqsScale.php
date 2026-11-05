<?php

namespace App\Support\Lqs;

use App\Services\Lqs\BestBuyLqsApiSyncService;
use App\Services\Lqs\EbayLqsApiSyncService;
use App\Services\Lqs\TemuLqsApiSyncService;

/**
 * Which LQS pages store the score as a 0-100 percentage (API-scored) instead of the 0-10 sheet scale.
 */
class LqsScale
{
    /** Marketplaces scored 0-100 from the live listing API. */
    public static function isPercent(string $slug): bool
    {
        return EbayLqsApiSyncService::supports($slug) || TemuLqsApiSyncService::supports($slug)
            || $slug === BestBuyLqsApiSyncService::MARKETPLACE;
    }

    /** Marketplaces with a bulk "Sync LQS from API" button. */
    public static function hasApiSync(string $slug): bool
    {
        return self::isPercent($slug);
    }

    /** Bring any stored/average LQS onto the 0-10 scale used by LQS Master (percent values are > 10). */
    public static function toTenScale(float $value): float
    {
        return $value > 10 ? round($value / 10, 1) : $value;
    }
}
