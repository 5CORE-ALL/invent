<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;

/**
 * Dynamic pricing rule (Amazon): LMP available AND CVR going down → extra discount, by how far CVR fell.
 *
 *   Drop = CVR L45 − CVR L30 (in CVR points). CVR is "down" when the drop is more than 0.1 point
 *   (same tolerance as the CVR arrow) and CVR L45 > 0.
 *
 *   Small  : drop < t1          → d1 %
 *   Medium : t1 ≤ drop < t2     → d2 %
 *   Large  : drop ≥ t2          → d3 %
 *
 * SKUs whose CVR is flat/up, or that have no LMP, get nothing from this rule. The same bands run in the page
 * (amazon-lmp-cvr-disc-rule partial) and in AmazonSprcDilAutoPushService.
 */
final class AmazonLmpCvrDiscRule
{
    public const CHANNEL_KEY = 'amazon_lmp_cvr_disc_rule';

    /** CVR must fall by more than this many points to count as "down". */
    public const DOWN_TOLERANCE = 0.1;

    public const DEFAULTS = [
        'enabled' => false,
        't1' => 0.5,
        't2' => 1.5,
        'd1' => 2.0,
        'd2' => 4.0,
        'd3' => 6.0,
    ];

    /** @return array{enabled:bool,t1:float,t2:float,d1:float,d2:float,d3:float} */
    public static function normalize(mixed $raw): array
    {
        $in = is_array($raw) ? $raw : [];
        $num = static function (string $key, float $lo, float $hi) use ($in): float {
            $v = $in[$key] ?? null;
            $n = is_numeric($v) ? (float) $v : (float) self::DEFAULTS[$key];

            return round(min($hi, max($lo, $n)), 2);
        };

        $t1 = $num('t1', 0.0, 100.0);
        $t2 = $num('t2', 0.0, 100.0);
        if ($t2 < $t1) {
            [$t1, $t2] = [$t2, $t1];
        }

        return [
            'enabled' => filter_var($in['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            't1' => $t1,
            't2' => $t2,
            'd1' => $num('d1', 0.0, 80.0),
            'd2' => $num('d2', 0.0, 80.0),
            'd3' => $num('d3', 0.0, 80.0),
        ];
    }

    /** @return array{enabled:bool,t1:float,t2:float,d1:float,d2:float,d3:float} */
    public static function config(): array
    {
        try {
            $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::CHANNEL_KEY)->first();
        } catch (\Throwable $e) {
            $row = null;
        }

        return self::normalize($row?->visibility);
    }

    public static function save(array $cfg): array
    {
        $payload = self::normalize($cfg);
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => self::CHANNEL_KEY],
            ['visibility' => $payload, 'column_order' => ['rule']]
        );

        return $payload;
    }

    /**
     * Extra discount % for one SKU (0 when the rule is off, no LMP, or CVR is not down).
     *
     * @param  float  $lmp  Lowest marketplace price (> 0 means "LMP available")
     * @param  float  $cvr  CVR L30 %
     * @param  float  $cvr45  CVR L45 % (baseline)
     */
    public static function discount(float $lmp, float $cvr, float $cvr45, ?array $cfg = null): float
    {
        $cfg = $cfg ?? self::config();
        if (empty($cfg['enabled']) || ! ($lmp > 0) || ! ($cvr45 > 0) || ! is_finite($cvr) || ! is_finite($cvr45)) {
            return 0.0;
        }
        $drop = $cvr45 - max(0.0, $cvr);
        if (! ($drop > self::DOWN_TOLERANCE)) {
            return 0.0;
        }
        $band = $drop < $cfg['t1'] ? 'd1' : ($drop < $cfg['t2'] ? 'd2' : 'd3');

        return round(max(0.0, (float) $cfg[$band]), 2);
    }
}
