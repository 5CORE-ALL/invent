<?php

namespace App\Support;

/**
 * Temu ads badge snapshots used to store Y spend in cents.
 * After lastDaySpendFromResult returns dollars, convert old points once.
 */
class TemuAdsBadgeHistory
{
    public const Y_SPEND_DOLLARS_FLAG = 'y_spend_in_dollars';

    /**
     * @param  array<string, mixed>  $hist
     * @return array<string, mixed>
     */
    public static function ensureYSpendInDollars(array $hist): array
    {
        if (! empty($hist[self::Y_SPEND_DOLLARS_FLAG])) {
            return $hist;
        }

        foreach ($hist as $period => $days) {
            if ($period === self::Y_SPEND_DOLLARS_FLAG || ! is_array($days)) {
                continue;
            }
            foreach ($days as $date => $metrics) {
                if (! is_array($metrics) || ! array_key_exists('y_spend', $metrics)) {
                    continue;
                }
                $hist[$period][$date]['y_spend'] = round(((float) $metrics['y_spend']) / 100, 2);
            }
        }

        $hist[self::Y_SPEND_DOLLARS_FLAG] = true;

        return $hist;
    }
}
