<?php

namespace App\Support\Marketplace;

/**
 * Last-two values for /all-marketplace-master table dots.
 * v2 is the saved table cell; v1 is the previous snapshot day.
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
     * @deprecated use lastTwoAdjacent — kept so older callers keep compiling
     *
     * @param  list<float|null>  $valuesNewestFirst
     * @return array{0: float|null, 1: float|null}
     */
    public static function lastTwoDistinct(array $valuesNewestFirst, float $epsilon = 0.01): array
    {
        return self::lastTwoAdjacent($valuesNewestFirst);
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
