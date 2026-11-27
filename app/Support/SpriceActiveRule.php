<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;

/**
 * Which S PRC formula a channel uses. std = Std prc vs dil. dil = Sprc Dil.
 * Stored once per channel so the page switch and cron stay on the same rule.
 */
class SpriceActiveRule
{
    /**
     * The ON rule. dil / std, or none when both switches are OFF.
     * none only exists for channels that save the two switches separately (Amazon). Other channels stay dil or std.
     */
    public static function forChannel(string $channel): string
    {
        $state = self::state($channel);
        if ($state['dil']) {
            return 'dil';
        }

        return $state['std'] ? 'std' : 'none';
    }

    /**
     * ON / OFF of each rule on its own. Old rows hold one rule only: that rule is ON, the other OFF.
     *
     * @return array{dil: bool, std: bool}
     */
    public static function state(string $channel): array
    {
        $channel = self::normalize($channel);
        if ($channel === '') {
            return ['dil' => false, 'std' => true];
        }
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $channel.'_sprice_active_rule')
            ->first();
        $v = is_array($row?->visibility) ? $row->visibility : [];
        if (array_key_exists('dil', $v) && array_key_exists('std', $v)) {
            return ['dil' => (bool) $v['dil'], 'std' => (bool) $v['std']];
        }
        $dil = ($v['rule'] ?? null) === 'dil';

        return ['dil' => $dil, 'std' => ! $dil];
    }

    public static function dilOn(string $channel): bool
    {
        return self::state($channel)['dil'];
    }

    public static function usesStdPrc(string $channel): bool
    {
        return self::forChannel($channel) === 'std';
    }

    public static function save(string $channel, string $rule): string
    {
        $rule = $rule === 'dil' ? 'dil' : 'std';
        self::saveState($channel, $rule === 'dil', $rule === 'std');

        return $rule;
    }

    /** Each rule ON / OFF on its own (both may be OFF). */
    public static function saveState(string $channel, bool $dil, bool $std): string
    {
        $channel = self::normalize($channel);
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => $channel.'_sprice_active_rule'],
            ['visibility' => ['rule' => $dil ? 'dil' : 'std', 'dil' => $dil, 'std' => $std], 'column_order' => []]
        );

        return $dil ? 'dil' : ($std ? 'std' : 'none');
    }

    /**
     * Run summary of a push / re-save job that did nothing because both rule switches are OFF.
     * Every stat the jobs report is 0, so callers read it like any other (empty) run.
     *
     * @return array{dry_run: bool, skip_push: bool, stats: array<string, mixed>}
     */
    public static function noneSummary(bool $dryRun = false, bool $skipPush = false): array
    {
        return [
            'dry_run' => $dryRun,
            'skip_push' => $skipPush,
            'stats' => [
                'candidates' => 0,
                'applied' => 0,
                'pushed' => 0,
                'blue' => 0,
                'failed' => 0,
                'cooldown' => 0,
                'left_for_next_run' => 0,
                'skipped_unchanged' => 0,
                'price_repaired' => 0,
                'skipped' => 0,
                'push_failed' => 0,
                'errors' => [],
            ],
        ];
    }

    public static function normalize(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return preg_match('/^[a-z0-9_]{2,40}$/', $channel) === 1 ? $channel : '';
    }
}
