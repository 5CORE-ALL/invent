<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;

/**
 * Which S PRC formula a channel uses. std = Std prc vs dil. dil = Sprc Dil.
 * Stored once per channel so the page switch and cron stay on the same rule.
 */
class SpriceActiveRule
{
    public static function forChannel(string $channel): string
    {
        $channel = self::normalize($channel);
        if ($channel === '') {
            return 'std';
        }
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $channel.'_sprice_active_rule')
            ->first();
        $rule = is_array($row?->visibility) ? ($row->visibility['rule'] ?? null) : null;

        return $rule === 'dil' ? 'dil' : 'std';
    }

    public static function usesStdPrc(string $channel): bool
    {
        return self::forChannel($channel) !== 'dil';
    }

    public static function save(string $channel, string $rule): string
    {
        $channel = self::normalize($channel);
        $rule = $rule === 'dil' ? 'dil' : 'std';
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => $channel.'_sprice_active_rule'],
            ['visibility' => ['rule' => $rule], 'column_order' => []]
        );

        return $rule;
    }

    public static function normalize(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return preg_match('/^[a-z0-9_]{2,40}$/', $channel) === 1 ? $channel : '';
    }
}
