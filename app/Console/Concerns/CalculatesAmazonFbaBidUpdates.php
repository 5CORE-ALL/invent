<?php

namespace App\Console\Concerns;

/**
 * Shared bid math for Amazon FBA auto bid update commands (KW + PT).
 */
trait CalculatesAmazonFbaBidUpdates
{
    public const MIN_BID = 0.10;

    public const MAX_BID = 5.00;

    public const UNDER_UTILIZED_THRESHOLD = 50;

    public const OVER_UTILIZED_THRESHOLD = 70;

    public const OVER_UTILIZED_AGGRESSIVE_UB1 = 85;

    public const AGGRESSIVE_DECREASE = 0.30;

    public const MODERATE_DECREASE = 0.20;

    public const LIGHT_DECREASE = 0.10;

    public const LIGHT_INCREASE = 0.20;

    public const AGGRESSIVE_INCREASE = 0.30;

    /**
     * Best-effort current default bid from synced report rows (not CPC).
     *
     * @param object|null $l7
     * @param object|null $l1
     */
    protected function resolveCurrentBidFromReport(?object $l7, ?object $l1, float $l7Cpc = 0.0, float $l1Cpc = 0.0): float
    {
        foreach (['sbid', 'last_sbid'] as $field) {
            foreach ([$l7, $l1] as $row) {
                if ($row === null) {
                    continue;
                }
                $v = (float) ($row->{$field} ?? 0);
                if ($v > 0) {
                    return $v;
                }
            }
        }

        // Fallback when report has not stored bids yet: use CPC as a proxy base, else floor.
        $proxy = max($l1Cpc, $l7Cpc);
        if ($proxy > 0) {
            return max(self::MIN_BID, round($proxy, 2));
        }

        return self::MIN_BID;
    }

    /**
     * Under-utilized: decrease bid by utilization tier (uses 1-day utilization ub1).
     */
    protected function calculateUnderUtilizedBid(float $currentBid, float $ub1, int $inv): float
    {
        if ($inv <= 0) {
            return round($currentBid, 2);
        }

        if ($ub1 >= self::UNDER_UTILIZED_THRESHOLD) {
            return round($currentBid, 2);
        }

        $decreasePercent = 0.0;
        if ($ub1 <= 0) {
            $decreasePercent = self::AGGRESSIVE_DECREASE;
        } elseif ($ub1 <= 30) {
            $decreasePercent = self::MODERATE_DECREASE;
        } elseif ($ub1 < self::UNDER_UTILIZED_THRESHOLD) {
            $decreasePercent = self::LIGHT_DECREASE;
        } else {
            return round($currentBid, 2);
        }

        $newBid = $currentBid * (1 - $decreasePercent);

        return round(max(self::MIN_BID, $newBid), 2);
    }

    /**
     * Over-utilized: increase bid (uses 1-day utilization ub1).
     */
    protected function calculateOverUtilizedBid(float $currentBid, float $ub1, int $inv): float
    {
        if ($inv <= 0) {
            return round($currentBid, 2);
        }

        if ($ub1 <= self::OVER_UTILIZED_THRESHOLD) {
            return round($currentBid, 2);
        }

        $increasePercent = $ub1 > self::OVER_UTILIZED_AGGRESSIVE_UB1
            ? self::AGGRESSIVE_INCREASE
            : self::LIGHT_INCREASE;

        $newBid = $currentBid * (1 + $increasePercent);

        return round(min(self::MAX_BID, max(self::MIN_BID, $newBid)), 2);
    }
}
