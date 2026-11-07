<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;

/**
 * Dynamic pricing rule (Amazon): LMP available AND CVR decreasing → add extra discount.
 *
 *   CVR decreasing = CVR L30 is below CVR L45 by more than `min_drop` points (and CVR L45 > 0, so a SKU that
 *                    never converted is not treated as "decreasing").
 *   Extra discount = base + step × (drop − min_drop), capped at `max`   (all in % off Std Prc)
 *
 * The bigger the CVR drop, the bigger the extra discount, so it follows the data on its own. Same formula
 * runs in the page (amazon-lmp-cvr-disc-rule partial) and in AmazonSprcDilAutoPushService.
 */
final class AmazonLmpCvrDiscRule
{
    public const CHANNEL_KEY = 'amazon_lmp_cvr_disc_rule';

    public const DEFAULTS = [
        'enabled' => false,
        'base' => 2.0,
        'step' => 1.0,
        'max' => 10.0,
        'min_drop' => 0.1,
    ];

    /** @return array{enabled:bool,base:float,step:float,max:float,min_drop:float} */
    public static function normalize(mixed $raw): array
    {
        $in = is_array($raw) ? $raw : [];
        $num = static function (string $key, float $lo, float $hi) use ($in): float {
            $v = $in[$key] ?? null;
            $d = self::DEFAULTS[$key];
            $n = is_numeric($v) ? (float) $v : (float) $d;

            return round(min($hi, max($lo, $n)), 2);
        };

        return [
            'enabled' => filter_var($in['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'base' => $num('base', 0.0, 50.0),
            'step' => $num('step', 0.0, 50.0),
            'max' => $num('max', 0.0, 80.0),
            'min_drop' => $num('min_drop', 0.0, 50.0),
        ];
    }

    /** @return array{enabled:bool,base:float,step:float,max:float,min_drop:float} */
    public static function config(): array
    {
        try {
            $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::CHANNEL_KEY)->first();
        } catch (\Throwable $e) {
            $row = null;
        }

        return self::normalize($row?->visibility);
    }

    /** @param array{enabled:bool,base:float,step:float,max:float,min_drop:float} $cfg */
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
     * Extra discount % for one SKU.
     *
     * @param  float  $lmp  Lowest marketplace price (> 0 means "LMP available")
     * @param  float  $cvr  CVR L30 %
     * @param  float  $cvr45  CVR L45 % (baseline)
     * @param  array{enabled:bool,base:float,step:float,max:float,min_drop:float}|null  $cfg
     */
    public static function discount(float $lmp, float $cvr, float $cvr45, ?array $cfg = null): float
    {
        $cfg = $cfg ?? self::config();
        if (empty($cfg['enabled']) || ! ($lmp > 0) || ! ($cvr45 > 0) || ! is_finite($cvr) || ! is_finite($cvr45)) {
            return 0.0;
        }
        $drop = $cvr45 - max(0.0, $cvr);
        if (! ($drop > $cfg['min_drop'])) {
            return 0.0;
        }
        $extra = $cfg['base'] + $cfg['step'] * ($drop - $cfg['min_drop']);

        return round(min($cfg['max'], max(0.0, $extra)), 2);
    }
}
