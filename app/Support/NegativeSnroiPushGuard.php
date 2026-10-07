<?php

namespace App\Support;

use App\Models\ChannelMasterCalculatedData;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;

/**
 * Per-page switch: do not push a price whose SNROI is below 0.
 * SNROI% = ((price × margin − ship − LP − price × Ads%) / LP) × 100.
 */
class NegativeSnroiPushGuard
{
    /** @var array<string, bool> */
    private static array $enabled = [];

    /** @var array<string, float> */
    private static array $margin = [];

    /** @var array<string, float> */
    private static array $ads = [];

    /** @var array<string, array{lp: float, ship: float}> */
    private static array $cost = [];

    public static function enabled(string $channel): bool
    {
        $channel = strtolower(trim($channel));
        if ($channel === '') {
            return false;
        }
        if (array_key_exists($channel, self::$enabled)) {
            return self::$enabled[$channel];
        }
        try {
            $row = ChannelTabulatorColumnSetting::query()
                ->where('channel_name', $channel.'_ignore_neg_snroi')
                ->first();
        } catch (\Throwable) {
            return self::$enabled[$channel] = false;
        }
        $vis = is_array($row?->visibility) ? $row->visibility : null;
        $on = is_array($vis) && filter_var($vis['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return self::$enabled[$channel] = $on;
    }

    public static function remember(string $channel, bool $on): void
    {
        self::$enabled[strtolower(trim($channel))] = $on;
    }

    public static function percent(float $price, float $margin, float $lp, float $ship, float $adsPct): ?float
    {
        if (! ($price > 0) || ! ($lp > 0)) {
            return null;
        }
        if (! ($margin > 0)) {
            $margin = 0.80;
        }
        $net = ($price * $margin) - $ship - $lp - ($price * ($adsPct / 100));

        return ($net / $lp) * 100;
    }

    public static function shouldSkip(string $channel, string $sku, float $price): bool
    {
        if (! self::enabled($channel) || ! ($price > 0)) {
            return false;
        }
        $cost = self::costForSku($sku);
        if ($cost === null) {
            return false;
        }
        $ship = self::excludeShip($channel) ? 0.0 : $cost['ship'];
        $snroi = self::percent($price, self::marginFor($channel), $cost['lp'], $ship, self::adsFor($channel));

        return $snroi !== null && $snroi < 0;
    }

    private static function excludeShip(string $channel): bool
    {
        return in_array($channel, [
            'wayfair', 'doba_withoutship', 'faire', 'topdawg', 'fb_marketplace',
            'shopify_b2b', 'mercari_woship', 'depop', 'instagram', 'alibaba',
        ], true);
    }

    /**
     * @return array{lp: float, ship: float}|null
     */
    private static function costForSku(string $sku): ?array
    {
        $key = strtoupper(trim($sku));
        if ($key === '') {
            return null;
        }
        if (array_key_exists($key, self::$cost)) {
            return self::$cost[$key]['lp'] > 0 ? self::$cost[$key] : null;
        }
        try {
            $master = ProductMaster::query()
                ->whereIn('sku', array_values(array_unique([$sku, $key])))
                ->first(['sku', 'Values']);
        } catch (\Throwable) {
            return null;
        }
        $values = $master ? $master->Values : null;
        if (is_string($values)) {
            $values = json_decode($values, true);
        }
        $lp = 0.0;
        $ship = 0.0;
        foreach ((array) $values as $name => $value) {
            $n = strtolower((string) $name);
            if ($n === 'lp') {
                $lp = (float) $value;
            } elseif ($n === 'ship') {
                $ship = (float) $value;
            }
        }
        self::$cost[$key] = ['lp' => $lp, 'ship' => $ship];

        return $lp > 0 ? self::$cost[$key] : null;
    }

    private static function marginFor(string $channel): float
    {
        if (isset(self::$margin[$channel])) {
            return self::$margin[$channel];
        }
        try {
            $margin = MarketplacePercentage::takeHomeForPromoChannel($channel === 'amazon' ? 'amazon' : $channel);
        } catch (\Throwable) {
            $margin = 0.0;
        }
        if (! ($margin > 0) || $margin > 1) {
            $margin = $channel === 'amazon' ? 0.80 : ($margin > 1 ? $margin / 100 : 0.80);
        }

        return self::$margin[$channel] = $margin;
    }

    private static function adsFor(string $channel): float
    {
        if (isset(self::$ads[$channel])) {
            return self::$ads[$channel];
        }
        $needles = match ($channel) {
            'amazon' => ['Amazon'],
            'ebay1' => ['Ebay', 'eBay'],
            'ebay2' => ['EbayTwo', 'Ebay 2'],
            'ebay3' => ['EbayThree', 'Ebay 3'],
            'shopify_b2c' => ['Shopify B2C', 'ShopifyB2C'],
            'shopify_b2b' => ['Shopify B2B', 'ShopifyB2B'],
            default => [],
        };
        $ads = 0.0;
        try {
            if ($needles !== []) {
                $ads = (float) (ChannelMasterCalculatedData::query()->whereIn('channel', $needles)->value('ads_percentage') ?? 0);
            }
            if (! ($ads > 0)) {
                $ads = (float) (ChannelMasterCalculatedData::query()->where('channel', 'like', $channel.'%')->value('ads_percentage') ?? 0);
            }
        } catch (\Throwable) {
            $ads = 0.0;
        }

        return self::$ads[$channel] = max(0, $ads);
    }
}
