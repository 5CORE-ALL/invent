<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;

/**
 * Dynamic pricing rule (Amazon): extra S PRC discount by CVR trend (Down / Up / Equal).
 *
 *   Diff = CVR L30 − CVR L45 (in CVR points).
 *   Down  : diff < −0.1   → `down` %
 *   Up    : diff >  0.1   → `up` %
 *   Equal : otherwise     → `equal` %
 *
 * With `need_lmp` on (default) a SKU only gets the discount when an LMP is available. The discount is added to
 * the Sum Disc. The same logic runs in the page (amazon-lmp-cvr-disc-rule partial) and in
 * AmazonSprcDilAutoPushService.
 */
final class AmazonLmpCvrDiscRule
{
    public const CHANNEL_KEY = 'amazon_lmp_cvr_disc_rule';

    /** CVR must move by more than this many points to count as Up / Down (same tolerance as the CVR arrow). */
    public const TOLERANCE = 0.1;

    public const DEFAULTS = [
        'enabled' => false,
        'need_lmp' => true,
        'down' => 4.0,
        'up' => 0.0,
        'equal' => 0.0,
    ];

    /** @return array{enabled:bool,need_lmp:bool,down:float,up:float,equal:float} */
    public static function normalize(mixed $raw): array
    {
        $in = is_array($raw) ? $raw : [];
        $num = static function (string $key) use ($in): float {
            $v = $in[$key] ?? null;
            $n = is_numeric($v) ? (float) $v : (float) self::DEFAULTS[$key];

            return round(min(80.0, max(-50.0, $n)), 2);
        };

        return [
            'enabled' => filter_var($in['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'need_lmp' => filter_var($in['need_lmp'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'down' => $num('down'),
            'up' => $num('up'),
            'equal' => $num('equal'),
        ];
    }

    /** @return array{enabled:bool,need_lmp:bool,down:float,up:float,equal:float} */
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

    /** 'down' | 'up' | 'equal' */
    public static function trend(float $cvr, float $cvr45): string
    {
        $diff = $cvr - $cvr45;
        if ($diff < -self::TOLERANCE) {
            return 'down';
        }
        if ($diff > self::TOLERANCE) {
            return 'up';
        }

        return 'equal';
    }

    /**
     * Extra discount % for one SKU (0 when the rule is off, or LMP is required and missing).
     *
     * @param  float  $lmp  Lowest marketplace price (> 0 means "LMP available")
     * @param  float  $cvr  CVR L30 %
     * @param  float  $cvr45  CVR L45 % (baseline)
     */
    public static function discount(float $lmp, float $cvr, float $cvr45, ?array $cfg = null): float
    {
        $cfg = $cfg ?? self::config();
        if (empty($cfg['enabled']) || ! is_finite($cvr) || ! is_finite($cvr45)) {
            return 0.0;
        }
        if (! empty($cfg['need_lmp']) && ! ($lmp > 0)) {
            return 0.0;
        }

        return round((float) ($cfg[self::trend($cvr, $cvr45)] ?? 0), 2);
    }
}
