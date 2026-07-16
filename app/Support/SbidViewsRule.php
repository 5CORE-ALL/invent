<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Sbid (Views): a daily one-step adjustment applied on top of the row's current
 * C Bid (the live eBay bid_percentage) based on its L7 View colour band, then
 * clamped to a manual Min/Max cap. Green = no change (keep current C Bid).
 *
 *   band = red   when l7_views < avg
 *          green when avg <= l7_views < 2*avg
 *          pink  when l7_views >= 2*avg
 *
 * Each band has a direction (inc | dec | none) and a step (%/day). The settings
 * are stored (shared across users) in ebay_sbid_rules under key ebay1_sbid_views
 * (eBay 1), ebay2_sbid_views (eBay 2), or ebay3_sbid_views (eBay 3) so both the
 * UI and the ebay*:update-suggestedbid cron use the same rule.
 *
 * Extra guard: when E L30 sold ≤ no_dec_max_el30 (default 0), never decrease
 * the bid — even if the L7 colour band says "dec".
 */
final class SbidViewsRule
{
    public const KEY = 'ebay1_sbid_views';

    public const KEY_EBAY2 = 'ebay2_sbid_views';

    public const KEY_EBAY3 = 'ebay3_sbid_views';

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'min_cap'    => 1.0,
            'max_cap'    => 20.0,
            'pink_dir'   => 'dec',
            'pink_step'  => 1.0,
            'green_dir'  => 'none',
            'green_step' => 0.0,
            'red_dir'    => 'inc',
            'red_step'   => 1.0,
            // If E L30 sold ≤ this qty, skip any Decrease step (keep C Bid).
            'no_dec_max_el30' => 0.0,
        ];
    }

    /**
     * Load the settings from ebay_sbid_rules, merged over the defaults so missing
     * keys always resolve.
     *
     * @return array<string,mixed>
     */
    public static function settings(string $key = self::KEY): array
    {
        $row = DB::table('ebay_sbid_rules')->where('key', $key)->first();
        $stored = $row ? json_decode($row->rule, true) : [];
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_merge(self::defaults(), $stored);
    }

    /** Normalise a submitted settings payload to the stored shape. */
    public static function sanitize(array $in): array
    {
        $dir = function ($v, $fallback) {
            $v = is_string($v) ? strtolower($v) : '';
            return in_array($v, ['inc', 'dec', 'none'], true) ? $v : $fallback;
        };
        $num = function ($v, $fallback) {
            return is_numeric($v) ? (float) $v : $fallback;
        };
        $d = self::defaults();

        return [
            'min_cap'    => $num($in['min_cap']    ?? null, $d['min_cap']),
            'max_cap'    => $num($in['max_cap']    ?? null, $d['max_cap']),
            'pink_dir'   => $dir($in['pink_dir']   ?? null, $d['pink_dir']),
            'pink_step'  => $num($in['pink_step']  ?? null, $d['pink_step']),
            'green_dir'  => $dir($in['green_dir']  ?? null, $d['green_dir']),
            'green_step' => $num($in['green_step'] ?? null, $d['green_step']),
            'red_dir'    => $dir($in['red_dir']    ?? null, $d['red_dir']),
            'red_step'   => $num($in['red_step']   ?? null, $d['red_step']),
            'no_dec_max_el30' => $num($in['no_dec_max_el30'] ?? null, $d['no_dec_max_el30']),
        ];
    }

    /** Colour band for an l7_views value relative to the average. '' when no avg. */
    public static function bandKey(float $l7, float $avg): string
    {
        if ($avg <= 0) {
            return '';
        }
        if ($l7 < $avg) {
            return 'red';
        }
        if ($l7 < $avg * 2) {
            return 'green';
        }
        return 'pink';
    }

    /**
     * Apply the Sbid (Views) adjustment to a base bid (the current C Bid).
     *
     * @param float                $baseBid  current C Bid (0 / missing = skip)
     * @param float                $l7       row l7_views
     * @param float                $avg      average l7_views across the processed set
     * @param array<string,mixed>  $settings resolved settings (see settings())
     * @param float|null           $el30Sold E L30 units sold (ebay_l30); when ≤ no_dec_max_el30, Decrease is skipped
     */
    public static function apply(float $baseBid, float $l7, float $avg, array $settings, ?float $el30Sold = null): float
    {
        // No current C Bid → nothing to adjust (stays skipped/0).
        if ($baseBid <= 0) {
            return $baseBid;
        }

        $band = self::bandKey($l7, $avg);
        $bid  = $baseBid;

        if ($band !== '') {
            $dir  = $settings[$band . '_dir'] ?? 'none';
            $step = (float) ($settings[$band . '_step'] ?? 0);

            // If E L30 sold ≤ threshold, never decrease the bid.
            $noDecMax = (float) ($settings['no_dec_max_el30'] ?? 0);
            if ($dir === 'dec' && $el30Sold !== null && $el30Sold <= $noDecMax) {
                $dir = 'none';
            }

            if ($dir === 'inc') {
                $bid = $baseBid + $step;
            } elseif ($dir === 'dec') {
                $bid = $baseBid - $step;
            }
        }

        $min = $settings['min_cap'] ?? null;
        $max = $settings['max_cap'] ?? null;
        if (is_numeric($min) && $bid < (float) $min) {
            $bid = (float) $min;
        }
        if (is_numeric($max) && $bid > (float) $max) {
            $bid = (float) $max;
        }
        if ($bid < 0) {
            $bid = 0.0;
        }

        return $bid;
    }
}
