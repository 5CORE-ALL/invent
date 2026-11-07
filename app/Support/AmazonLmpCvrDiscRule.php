<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;

/**
 * Dynamic pricing rule (Amazon): extra S PRC discount on the CVR arrow (Down / Up) bands.
 *
 * Same bands as the "CVR up / down" panel, but only for SKUs whose CVR L30 arrow is Down or Up
 * (a flat arrow gets nothing from this rule):
 *
 *   Arrow Down : CVR L30 < down2_lt → down2_disc %,  else CVR L30 < down_lt → down_disc %
 *   Arrow Up   : CVR L30 > up2_gt   → up2_disc %,    else CVR L30 > up_gt   → up_disc %
 *
 * With `need_lmp` on (default) a SKU only gets the discount when an LMP is available. The discount is added to
 * the Sum Disc. The same logic runs in the page (amazon-lmp-cvr-disc-rule partial) and in
 * AmazonSprcDilAutoPushService.
 */
final class AmazonLmpCvrDiscRule
{
    public const CHANNEL_KEY = 'amazon_lmp_cvr_disc_rule';

    public const DEFAULTS = [
        'enabled' => false,
        'need_lmp' => true,
        'down2_lt' => 4.0,
        'down2_disc' => 6.0,
        'down_lt' => 7.0,
        'down_disc' => 4.0,
        'up_gt' => 13.0,
        'up_disc' => 0.0,
        'up2_gt' => 15.0,
        'up2_disc' => 0.0,
    ];

    /** @return array<string,mixed> */
    public static function normalize(mixed $raw): array
    {
        $in = is_array($raw) ? $raw : [];
        $num = static function (string $key, float $lo, float $hi) use ($in): float {
            $v = $in[$key] ?? null;
            $n = is_numeric($v) ? (float) $v : (float) self::DEFAULTS[$key];

            return round(min($hi, max($lo, $n)), 2);
        };

        return [
            'enabled' => filter_var($in['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'need_lmp' => filter_var($in['need_lmp'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'down2_lt' => $num('down2_lt', 0.0, 100.0),
            'down2_disc' => $num('down2_disc', -50.0, 80.0),
            'down_lt' => $num('down_lt', 0.0, 100.0),
            'down_disc' => $num('down_disc', -50.0, 80.0),
            'up_gt' => $num('up_gt', 0.0, 100.0),
            'up_disc' => $num('up_disc', -50.0, 80.0),
            'up2_gt' => $num('up2_gt', 0.0, 100.0),
            'up2_disc' => $num('up2_disc', -50.0, 80.0),
        ];
    }

    /** eBay pages that carry the same rule (each keeps its own saved copy). */
    public const EBAY_CHANNELS = ['ebay1', 'ebay2', 'ebay3'];

    /** Storage key of a page's rule row. Amazon keeps its original key. */
    public static function keyFor(?string $channel = null): string
    {
        $channel = strtolower(trim((string) $channel));

        return ($channel === '' || $channel === 'amazon') ? self::CHANNEL_KEY : $channel.'_lmp_cvr_disc_rule';
    }

    /** @return array<string,mixed> */
    public static function config(?string $channel = null): array
    {
        try {
            $row = ChannelTabulatorColumnSetting::query()->where('channel_name', self::keyFor($channel))->first();
        } catch (\Throwable $e) {
            $row = null;
        }

        return self::normalize($row?->visibility);
    }

    public static function save(array $cfg, ?string $channel = null): array
    {
        $payload = self::normalize($cfg);
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => self::keyFor($channel)],
            ['visibility' => $payload, 'column_order' => ['rule']]
        );

        return $payload;
    }

    /**
     * Band a SKU falls in: 'down2' | 'down' | 'up2' | 'up', or null (flat arrow / outside every band).
     * Uses the same arrow as the CVR L30 column.
     */
    public static function band(float $cvr, float $cvr45, array $cfg, ?string $trend = null): ?string
    {
        $trend = $trend ?? AmazonDilGroiRule::cvrTrend($cvr, $cvr45);
        if ($trend === 'down') {
            $slabs = [];
            if ($cfg['down2_lt'] > 0) {
                $slabs[] = ['down2', $cfg['down2_lt']];
            }
            if ($cfg['down_lt'] > 0) {
                $slabs[] = ['down', $cfg['down_lt']];
            }
            usort($slabs, fn ($a, $b) => $a[1] <=> $b[1]);
            foreach ($slabs as [$key, $lt]) {
                if ($cvr < $lt) {
                    return $key;
                }
            }
        } elseif ($trend === 'up') {
            $slabs = [['up', $cfg['up_gt']], ['up2', $cfg['up2_gt']]];
            usort($slabs, fn ($a, $b) => $b[1] <=> $a[1]);
            foreach ($slabs as [$key, $gt]) {
                if ($cvr > $gt) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Extra discount % for one SKU (0 when the rule is off, LMP is required and missing, or no band matches).
     *
     * @param  float  $lmp  Lowest marketplace price (> 0 means "LMP available")
     * @param  float  $cvr  CVR L30 %
     * @param  float  $cvr45  CVR L45 % (baseline)
     * @param  string|null  $trend  'down' | 'up' | 'flat' when the page's arrow is not the Amazon one (eBay: CVR 30 vs CVR 60, 0 is not Down)
     */
    public static function discount(float $lmp, float $cvr, float $cvr45, ?array $cfg = null, ?string $trend = null): float
    {
        $cfg = $cfg ?? self::config();
        if (empty($cfg['enabled']) || ! is_finite($cvr) || ! is_finite($cvr45)) {
            return 0.0;
        }
        if (! empty($cfg['need_lmp']) && ! ($lmp > 0)) {
            return 0.0;
        }
        $key = self::band($cvr, $cvr45, $cfg, $trend);

        return $key === null ? 0.0 : round((float) ($cfg[$key . '_disc'] ?? 0), 2);
    }
}
