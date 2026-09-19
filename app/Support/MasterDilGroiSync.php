<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Central Dil slab writes against the existing {channel}_dil_vs_groi rows
 * on channel_tabulator_column_settings. No extra table. Each Sprc Dil page
 * still reads its own store.
 */
class MasterDilGroiSync
{
    public const STORE_SUFFIX = '_dil_vs_groi';

    /**
     * Channels that have a Sprc Dil page or cron reading {channel}_dil_vs_groi.
     *
     * @var list<string>
     */
    public const CHANNELS = [
        'amazon',
        'ebay1',
        'ebay2',
        'ebay2op',
        'ebay3',
        'aliexpress',
        'faire',
        'tiktok',
        'tiktok2',
        'mercari_wship',
        'mercari_woship',
        'pls',
        'shein',
        'bestbuy',
        'newegg',
        'reverb',
        'wayfair',
        'depop',
        'vinted',
        'instagram',
        'macys',
        'macy',
        'shopify_b2c',
        'shopify_b2b',
        'purchasing_power',
        'topdawg',
        'temu',
        'temu2',
        'temu3',
        'doba',
        'doba_withoutship',
        'walmart',
        'fb_marketplace',
    ];

    /**
     * @var array<string, string>
     */
    public const CHANNEL_LABELS = [
        'amazon' => 'Amazon',
        'ebay1' => 'eBay',
        'ebay2' => 'eBay 2',
        'ebay2op' => 'eBay 2 OP',
        'ebay3' => 'eBay 3',
        'aliexpress' => 'AliExpress',
        'faire' => 'Faire',
        'tiktok' => 'TikTok',
        'tiktok2' => 'TikTok 2',
        'mercari_wship' => 'Mercari w Ship',
        'mercari_woship' => 'Mercari Pickup',
        'pls' => 'PLS',
        'shein' => 'Shein',
        'bestbuy' => 'Best Buy',
        'newegg' => 'Newegg',
        'reverb' => 'Reverb',
        'wayfair' => 'Wayfair',
        'depop' => 'Depop',
        'vinted' => 'Vinted',
        'instagram' => 'Instagram Shop',
        'macys' => 'Macys',
        'macy' => 'Macys',
        'shopify_b2c' => 'Shopify B2C',
        'shopify_b2b' => 'Shopify B2B',
        'purchasing_power' => 'Purchasing Power',
        'topdawg' => 'TopDawg',
        'temu' => 'Temu / New Temu One / New Temu Two',
        'temu2' => 'Temu 2',
        'temu3' => 'Temu 3',
        'doba' => 'Doba',
        'doba_withoutship' => 'Doba Pickup',
        'walmart' => 'Walmart',
        'fb_marketplace' => 'FB Marketplace',
    ];

    public static function storeKey(string $channel): string
    {
        return $channel.self::STORE_SUFFIX;
    }

    public static function labelFor(string $channel): string
    {
        return self::CHANNEL_LABELS[$channel] ?? $channel;
    }

    /**
     * Known Sprc Dil channels plus any extra *_dil_vs_groi rows already stored.
     *
     * @return list<string>
     */
    public static function persistTargets(): array
    {
        $channels = self::CHANNELS;
        if (self::settingsTableReady()) {
            $names = ChannelTabulatorColumnSetting::query()
                ->where('channel_name', 'like', '%'.self::STORE_SUFFIX)
                ->pluck('channel_name')
                ->all();
            foreach ($names as $name) {
                $name = (string) $name;
                if (! str_ends_with($name, self::STORE_SUFFIX)) {
                    continue;
                }
                $channel = substr($name, 0, -strlen(self::STORE_SUFFIX));
                if ($channel !== '' && preg_match('/^[a-z0-9_]{2,40}$/', $channel) === 1) {
                    $channels[] = $channel;
                }
            }
        }

        return array_values(array_unique($channels));
    }

    /**
     * Master table = union of saved stores (or first-time defaults).
     *
     * @return array{
     *     rules: list<array{key:string,label:string,min:float,max:float,groi:float,used_on:list<string>}>,
     *     channels: list<array{channel:string,label:string,is_default:bool,slab_count:int}>,
     *     is_default: bool
     * }
     */
    public static function snapshot(): array
    {
        $savedLists = [];
        $channels = [];
        foreach (self::persistTargets() as $channel) {
            $loaded = self::loadChannel($channel);
            $isDefault = $loaded['is_default'];
            if (! $isDefault) {
                $savedLists[] = $loaded['rules'];
            }
            if ($channel === 'macy') {
                continue;
            }
            $channels[] = [
                'channel' => $channel,
                'label' => self::labelFor($channel),
                'is_default' => $isDefault,
                'slab_count' => count($loaded['rules']),
            ];
        }

        $isDefault = $savedLists === [];
        $union = $isDefault
            ? AmazonDilGroiRule::amazonDefaults()
            : AmazonDilGroiRule::unionByKey($savedLists);

        $usedBy = [];
        foreach (self::persistTargets() as $channel) {
            $loaded = self::loadChannel($channel);
            foreach (AmazonDilGroiRule::rulesByKey($loaded['rules']) as $key => $rule) {
                $usedBy[$key][] = $channel;
            }
        }

        $rules = [];
        foreach ($union as $rule) {
            $key = (string) $rule['key'];
            $used = array_values(array_unique($usedBy[$key] ?? []));
            $rules[] = array_merge($rule, ['used_on' => $used]);
        }

        return [
            'rules' => $rules,
            'channels' => $channels,
            'is_default' => $isDefault,
        ];
    }

    /**
     * Apply the master table to every existing {channel}_dil_vs_groi store.
     * Add / remove go to every site. Target % changes only where that From–To exists.
     * CVR overlay is left as-is.
     *
     * @param  list<array<string, mixed>>  $incoming
     * @return array{rules:list<array{key:string,label:string,min:float,max:float,groi:float}>,updated:list<string>}
     */
    public static function writeFull(array $incoming): array
    {
        $rules = AmazonDilGroiRule::normalizeList($incoming);
        if ($rules === []) {
            throw new \InvalidArgumentException('At least one Dil slab is required');
        }

        $previous = [];
        foreach (self::snapshot()['rules'] as $rule) {
            $previous[] = $rule;
        }
        $diff = AmazonDilGroiRule::diff($previous, $rules);
        $updated = [];
        foreach (self::persistTargets() as $channel) {
            $loaded = self::loadChannel($channel);
            $next = AmazonDilGroiRule::applyPatch($loaded['rules'], $diff);
            if (AmazonDilGroiRule::usesZeroToZero($channel)) {
                $next = AmazonDilGroiRule::ensureZeroToZero($next);
            }
            if (AmazonDilGroiRule::sameRules($loaded['rules'], $next)) {
                continue;
            }
            self::persistChannel($channel, $next);
            $updated[] = $channel;
        }

        return [
            'rules' => $rules,
            'updated' => $updated,
        ];
    }

    /**
     * After one site saves: add/remove slabs on every store; Target % only where that slab exists
     * (including first-time defaults, so an unsaved page still picks up the new value).
     *
     * @param  list<array<string, mixed>>  $oldRules
     * @param  list<array<string, mixed>>  $newRules
     * @return list<string>
     */
    public static function propagate(string $source, array $oldRules, array $newRules): array
    {
        $source = strtolower(trim($source));
        $oldForDiff = AmazonDilGroiRule::normalizeList($oldRules);
        if ($oldForDiff === []) {
            $oldForDiff = AmazonDilGroiRule::defaultsForChannel($source);
        }
        $diff = AmazonDilGroiRule::diff($oldForDiff, $newRules);
        if (AmazonDilGroiRule::isEmptyDiff($diff)) {
            return [];
        }

        $updated = [];
        foreach (self::persistTargets() as $channel) {
            if ($channel === $source) {
                continue;
            }
            $loaded = self::loadChannel($channel);
            $next = AmazonDilGroiRule::applyPatch($loaded['rules'], $diff);
            if (AmazonDilGroiRule::usesZeroToZero($channel)) {
                $next = AmazonDilGroiRule::ensureZeroToZero($next);
            }
            if (AmazonDilGroiRule::sameRules($loaded['rules'], $next) && ! $loaded['is_default']) {
                continue;
            }
            if (AmazonDilGroiRule::sameRules($loaded['rules'], $next) && $loaded['is_default']) {
                continue;
            }
            self::persistChannel($channel, $next);
            $updated[] = $channel;
        }

        return $updated;
    }

    /**
     * @return array{rules:list<array{key:string,label:string,min:float,max:float,groi:float}>,cvr_adj:array{down_lt:float,down_adj:float,up_gt:float,up_adj:float},is_default:bool}
     */
    public static function loadChannel(string $channel): array
    {
        $row = self::settingsTableReady()
            ? ChannelTabulatorColumnSetting::query()->where('channel_name', self::storeKey($channel))->first()
            : null;
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        if ($unpacked['rules'] === []) {
            $rules = AmazonDilGroiRule::defaultsForChannel($channel);

            return [
                'rules' => $rules,
                'cvr_adj' => $unpacked['cvr_adj'],
                'is_default' => true,
            ];
        }
        $rules = AmazonDilGroiRule::usesZeroToZero($channel)
            ? AmazonDilGroiRule::ensureZeroToZero($unpacked['rules'])
            : $unpacked['rules'];

        return [
            'rules' => $rules,
            'cvr_adj' => $unpacked['cvr_adj'],
            'is_default' => false,
        ];
    }

    /**
     * @param  list<array{key:string,label:string,min:float,max:float,groi:float}>  $rules
     */
    public static function persistChannel(string $channel, array $rules): void
    {
        if (! self::settingsTableReady()) {
            return;
        }
        $rules = AmazonDilGroiRule::normalizeList($rules);
        if ($rules === []) {
            return;
        }
        $existing = self::loadChannel($channel);
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => self::storeKey($channel)],
            [
                'visibility' => ['rules' => $rules, 'cvr_adj' => $existing['cvr_adj']],
                'column_order' => array_column($rules, 'key'),
            ]
        );
    }

    private static function settingsTableReady(): bool
    {
        try {
            return Schema::hasTable((new ChannelTabulatorColumnSetting)->getTable());
        } catch (\Throwable) {
            return false;
        }
    }
}
