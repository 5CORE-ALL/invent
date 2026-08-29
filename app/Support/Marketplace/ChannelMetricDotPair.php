<?php

namespace App\Support\Marketplace;

/**
 * Last-two values for /all-marketplace-master table dots.
 * Walks back past duplicate/seeded days so a gray table dot cannot sit
 * next to a red/green last step on the chart.
 */
class ChannelMetricDotPair
{
    /**
     * @param  list<float|null>  $valuesNewestFirst
     * @return array{0: float|null, 1: float|null}
     */
    public static function lastTwoDistinct(array $valuesNewestFirst, float $epsilon = 0.01): array
    {
        $v2 = null;
        $v1 = null;
        foreach ($valuesNewestFirst as $v) {
            if ($v === null) {
                continue;
            }
            $v = (float) $v;
            if ($v2 === null) {
                $v2 = $v;
                continue;
            }
            if (abs($v - $v2) > $epsilon) {
                $v1 = $v;
                break;
            }
        }
        if ($v2 !== null && $v1 === null) {
            $v1 = $v2;
        }

        return [$v1, $v2];
    }

    public static function pinLatest(?float $v1, ?float $v2, ?float $live, float $epsilon = 0.01): array
    {
        if ($live === null) {
            return [$v1, $v2];
        }
        if ($v1 === null) {
            $v1 = $v2;
        }

        return [$v1, $live];
    }
}
