<?php

namespace App\Support\Marketplace;

/**
 * Last-two values for /all-marketplace-master table dots.
 * v2 is the latest (table) value; v1 is the last different snapshot day.
 */
class ChannelMetricDotPair
{
    /**
     * @param  list<float|null>  $valuesNewestFirst
     * @return array{0: float|null, 1: float|null}  [previous day, latest day]
     */
    public static function lastTwoAdjacent(array $valuesNewestFirst): array
    {
        $found = [];
        foreach ($valuesNewestFirst as $v) {
            if ($v === null) {
                continue;
            }
            $found[] = (float) $v;
            if (count($found) === 2) {
                break;
            }
        }
        if ($found === []) {
            return [null, null];
        }
        if (count($found) === 1) {
            return [$found[0], $found[0]];
        }

        return [$found[1], $found[0]];
    }

    /**
     * Walk back past duplicate / seeded days so a flat last calendar day
     * does not paint every marketplace gray while the chart still shows a move.
     *
     * @param  list<float|null>  $valuesNewestFirst
     * @return array{0: float|null, 1: float|null}  [last different, latest]
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
        if ($v2 === null) {
            return [null, null];
        }
        if ($v1 === null) {
            return [$v2, $v2];
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
