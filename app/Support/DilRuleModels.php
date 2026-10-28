<?php

namespace App\Support;

/**
 * Which Sprc Dil pages share one rule model.
 *
 * A "rule model" is the set of pages whose Sprc Dil modal carries the SAME blocks:
 * Dil slabs + (CVR overlay) + (Age points) + (Diff points) + (LMP cap). The blocks come from the flags in
 * resources/views/partials/ebay-sprc-dil.blade.php; tests/Unit/DilRuleModelsTest.php fails when the partial and
 * this list disagree, so a page can not silently drift into the wrong model.
 *
 * Storage is unchanged: every page keeps its own row `{channel}_dil_vs_groi` in channel_tabulator_column_settings,
 * because every pricing service reads that row. The two save actions are:
 *   - Save Only: ChannelPromoPricingController::saveDilGroiRules   → this page's row only.
 *   - Save All:  ChannelPromoPricingController::saveDilGroiRulesAll → this page's row AND every other row of the model,
 *                written from the one configuration the user just saved (see applyTo()).
 */
final class DilRuleModels
{
    /**
     * @var array<string, array{label:string, blocks:list<string>, channels:list<string>}>
     */
    public const MODELS = [
        'cvr_age_diff_lmp' => [
            'label' => 'Dil + CVR + Age + Diff + LMP cap',
            'blocks' => ['cvr_adj', 'age_adj', 'diff_adj', 'lmp_cap'],
            'channels' => ['ebay1', 'ebay2', 'ebay3', 'temu', 'newtemutwo', 'temu3', 'shopify_b2c', 'shopify_b2b', 'reverb', 'shein', 'tiktok', 'tiktok2'],
        ],
        'cvr_age_diff' => [
            'label' => 'Dil + CVR + Age + Diff',
            'blocks' => ['cvr_adj', 'age_adj', 'diff_adj'],
            'channels' => ['ebay2op', 'faire'],
        ],
        'age_diff_lmp' => [
            'label' => 'Dil + Age + Diff + LMP cap',
            'blocks' => ['age_adj', 'diff_adj', 'lmp_cap'],
            'channels' => ['macys', 'aliexpress', 'bestbuy', 'mercari_wship'],
        ],
        'age_diff' => [
            'label' => 'Dil + Age + Diff',
            'blocks' => ['age_adj', 'diff_adj'],
            'channels' => ['purchasing_power', 'wayfair', 'doba', 'doba_withoutship', 'topdawg', 'newegg', 'walmart', 'pls', 'depop', 'vinted', 'instagram', 'alibaba', 'mercari_woship', 'fb_marketplace'],
        ],
    ];

    /** Rule blocks a Save All may copy. Clearance NROI% is page-specific and is never copied. */
    public const ALL_BLOCKS = ['cvr_adj', 'age_adj', 'diff_adj', 'lmp_cap'];

    /**
     * @return array{id:string,label:string,blocks:list<string>,channels:list<string>}|null
     */
    public static function modelFor(string $channel): ?array
    {
        $channel = self::canonical($channel);
        foreach (self::MODELS as $id => $model) {
            if (in_array($channel, $model['channels'], true)) {
                return ['id' => $id] + $model;
            }
        }

        return null;
    }

    /** @return list<string> every page of the model that contains $channel (including $channel); [] when it has none */
    public static function channelsFor(string $channel): array
    {
        return self::modelFor($channel)['channels'] ?? [];
    }

    public static function canonical(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return $channel === 'macy' ? 'macys' : $channel;
    }

    /**
     * The row a Save All writes to $target, built from the saved $source configuration.
     * Slabs always follow $source (0–0 slab kept where the page needs it). Only the blocks the model owns are copied.
     * The target's own Clearance NROI% (page-specific, tied to that channel's margin) and any key this model does not
     * own are kept as they are.
     *
     * @param  array{rules:list<array<string,mixed>>,cvr_adj?:mixed,lmp_cap?:mixed,diff_adj?:mixed,age_adj?:mixed}  $source
     * @param  array<string,mixed>|null  $targetStored  the target's current `visibility` JSON
     * @return array<string,mixed> the new `visibility` JSON
     */
    public static function applyTo(string $target, array $source, ?array $targetStored): array
    {
        $model = self::modelFor($target);
        $owned = $model['blocks'] ?? [];
        $existing = AmazonDilGroiRule::unpackStored($targetStored);

        $rules = AmazonDilGroiRule::normalizeList($source['rules']);
        if (AmazonDilGroiRule::usesZeroToZero($target)) {
            $rules = AmazonDilGroiRule::ensureZeroToZero($rules);
        }

        $out = [
            'rules' => $rules,
            'cvr_adj' => in_array('cvr_adj', $owned, true) && array_key_exists('cvr_adj', $source) ? $source['cvr_adj'] : $existing['cvr_adj'],
            'lmp_cap' => in_array('lmp_cap', $owned, true) && array_key_exists('lmp_cap', $source) ? $source['lmp_cap'] : $existing['lmp_cap'],
            'diff_adj' => in_array('diff_adj', $owned, true) && array_key_exists('diff_adj', $source) ? $source['diff_adj'] : $existing['diff_adj'],
            'age_adj' => in_array('age_adj', $owned, true) && array_key_exists('age_adj', $source) ? $source['age_adj'] : $existing['age_adj'],
        ];
        if (array_key_exists('clearance_nroi', $existing) && $existing['clearance_nroi'] !== null) {
            $out['clearance_nroi'] = $existing['clearance_nroi'];
        }

        return $out;
    }
}
