<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;
use App\Services\TemuShopifySalesService;
use App\Support\ProductMasterTemuShip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * NROI% of a SKU on Temu 2 at the base price it is listed / published with.
 *
 * Same formula as /new-temutwo (and /temu2-decrease):
 *   R Price = Base (+ $2.99 when Base <= $26.99), Full Price = Base x full-price multiplier
 *   NPFT $  = R Price x Temu 2 margin - LP - Temu ship - (Full Price x channel Ads% / 100)
 *   NROI %  = NPFT $ / LP x 100
 *
 * Rule: a SKU whose NROI% is below LOW_BELOW is published to Temu 2 with 0 inventory.
 */
class Temu2Nroi
{
    public const LOW_BELOW = 30.0;

    private static ?float $adsPercent = null;

    private static int $adsAt = 0;

    public static function isLow(?float $nroi): bool
    {
        return $nroi !== null && $nroi < self::LOW_BELOW;
    }

    /** Channel-level Temu 2 Ads% (same number /new-temutwo nets from every row; cached 10 min there). */
    public static function adsPercent(): float
    {
        if (self::$adsPercent !== null && (time() - self::$adsAt) < 60) {
            return self::$adsPercent;
        }
        self::$adsAt = time();
        $ads = 0.0;
        try {
            $cached = Cache::get('newtemutwo_channel_ads_summary_v3');
            if (is_array($cached)) {
                $ads = (float) ($cached['percent'] ?? 0);
            } else {
                $ads = (float) app(\App\Http\Controllers\MarketPlace\NewTemutwoController::class)->channelAdsPercent();
            }
        } catch (\Throwable $e) {
            Log::warning('Temu2Nroi ads percent failed: '.$e->getMessage());
        }

        return self::$adsPercent = max(0.0, $ads);
    }

    public static function lp(ProductMaster $pm): float
    {
        $values = self::values($pm);
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp' && is_numeric($v)) {
                return (float) $v;
            }
        }
        foreach (['lp', 'LP'] as $attr) {
            if (isset($pm->{$attr}) && is_numeric($pm->{$attr})) {
                return (float) $pm->{$attr};
            }
        }

        return 0.0;
    }

    /**
     * @return float|null null when there is no price or no LP to measure against
     */
    public static function forProduct(ProductMaster $pm, ?float $basePrice): ?float
    {
        if ($basePrice === null || ! ($basePrice > 0)) {
            return null;
        }
        $lp = self::lp($pm);
        if (! ($lp > 0)) {
            return null;
        }

        $ship = ProductMasterTemuShip::forPricing(self::values($pm), $pm);
        $margin = TemuShopifySalesService::temu2MarginDecimal();
        $rPrice = TemuShopifySalesService::computeRPrice($basePrice);
        $fullPrice = round(TemuShopifySalesService::computeFullTemuPrice($basePrice), 2);
        if (! ($rPrice > 0)) {
            return null;
        }

        $gpftDollars = TemuShopifySalesService::computeGroiProfit($rPrice, $margin, $lp, $ship);
        $npftDollars = $gpftDollars - ($fullPrice * (self::adsPercent() / 100));

        return round(($npftDollars / $lp) * 100, 2);
    }

    /** @return array<string, mixed> */
    private static function values(ProductMaster $pm): array
    {
        $raw = $pm->Values ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }
}
