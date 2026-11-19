<?php

namespace App\Support;

/**
 * eBay 1 / 2 / 3 and Shopify B2C CVR Price Rule — server twin of ebayCvrRuleApply() in partials/ebay-cvr-price-rule.blade.php.
 *
 * The SKU's CVR 30 is compared with its LT O CVR: Down / within target / Up, each with a price change %. The change is
 * taken off the S PRC the other rules already produced (LMP cap and Std Prc maximum done), NOT off the live Price, so
 * the answer is the same before and after a push (a % taken off the live Price would compound on every run).
 * Lower cap: the new price never goes under the price where NPFT% or NROI% reaches its minimum.
 */
class EbayCvrPriceRule
{
    public const KEYS = ['enabled', 'down', 'same', 'up', 'lower_cap', 'min_npft', 'min_nroi', 'down_band', 'up_band'];

    /** Saved rule for a channel (ebay1 / ebay2 / ebay3 / shopify_b2c), normalized. Stored under `{channel}_cvr_price_rule`. */
    public static function config(string $channel): array
    {
        try {
            $row = \App\Models\ChannelTabulatorColumnSetting::query()->where('channel_name', $channel.'_cvr_price_rule')->first();
        } catch (\Throwable $e) {
            $row = null;
        }

        return \App\Http\Controllers\MarketPlace\OverallAmazonController::amazonNormalizeCvrPriceRule($row?->visibility);
    }

    public static function save(string $channel, array $input): array
    {
        $payload = \App\Http\Controllers\MarketPlace\OverallAmazonController::amazonNormalizeCvrPriceRule(array_intersect_key($input, array_flip(self::KEYS)));
        \App\Models\ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => $channel.'_cvr_price_rule'],
            ['visibility' => $payload, 'column_order' => self::KEYS]
        );

        return $payload;
    }

    /**
     * CVR Price Rule running on its own (both S PRC rules OFF) compares with the live Price: S PRC = live Price + the rule's %.
     * It acts once per price: when the saved S PRC already equals the live Price (the rule's price was pushed), the rule holds
     * until the price changes, so the % never compounds on every run.
     */
    public static function holdsLive(float $live, float $saved): bool
    {
        return $saved > 0 && abs($saved - $live) < 0.005;
    }

    /**
     * Up from the live Price never goes past the Std Prc (LMP-capped) price; Down never goes above live (see apply()).
     * Null = no change from the live Price.
     */
    public static function clampUp(float $price, float $live, float $stdCapped): ?float
    {
        if ($price > $live) {
            if (! ($stdCapped > 0)) {
                return null;
            }
            $price = max($live, min($price, $stdCapped));
        }
        $price = round($price, 2);

        return abs($price - $live) < 0.005 ? null : $price;
    }

    /**
     * CVR Price Rule running on its own (Sprc Dil and Std prc vs dil both OFF): the SKUs the last run priced. The automatic
     * blue push sends only these, never an old saved S PRC. Stored under `{channel}_cvr_only_skus`.
     *
     * @return list<string>
     */
    public static function onlySkus(string $channel): array
    {
        try {
            $row = \App\Models\ChannelTabulatorColumnSetting::query()->where('channel_name', $channel.'_cvr_only_skus')->first();
            $v = is_array($row?->visibility) ? $row->visibility : [];

            return array_values(array_unique(array_map(static fn ($s) => strtoupper(trim((string) $s)), (array) ($v['skus'] ?? []))));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param  list<string>  $skus */
    public static function saveOnlySkus(string $channel, array $skus): void
    {
        \App\Models\ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => $channel.'_cvr_only_skus'],
            ['visibility' => ['skus' => array_values(array_unique(array_map(static fn ($s) => strtoupper(trim((string) $s)), $skus)))], 'column_order' => []]
        );
    }

    /**
     * @param  array<string, mixed>  $input  EbayRuleSprice::inputFromTabulatorRow() (needs cvr, lto_cvr, lp, ship, percentage, ads)
     * @param  array<string, mixed>|null  $cfg  EbayController::ebayCvrPriceRuleConfig()
     * @return array{price: float, capped: bool}|null  null = the rule does not change this SKU
     */
    public static function apply(float $base, array $input, ?array $cfg): ?array
    {
        if ($cfg === null || empty($cfg['enabled']) || ! ($base > 0)) {
            return null;
        }
        $lto = $input['lto_cvr'] ?? null;
        if ($lto === null || $lto === '' || ! is_numeric($lto)) {
            return null;
        }
        $lto = (float) $lto;
        $cvr = (float) ($input['cvr'] ?? 0);
        $down = self::band($cfg['down_band'] ?? null, 20.0, 99.0) / 100;
        $up = self::band($cfg['up_band'] ?? null, 20.0, 1000.0) / 100;
        if ($cvr > $lto * (1 + $up)) {
            $case = 'up';
        } elseif ($cvr < $lto * (1 - $down)) {
            $case = 'down';
        } else {
            $case = 'same';
        }
        $raw = $cfg[$case] ?? null;
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }
        $pct = (float) $raw;
        $pct = $case === 'down' ? -abs($pct) : ($case === 'up' ? abs($pct) : $pct);
        if ($pct == 0.0) {
            return null;
        }
        $next = $base * (1 + $pct / 100);
        $capped = false;
        $floor = ! empty($cfg['lower_cap']) ? self::floorPrice($input, $cfg) : null;
        if ($floor !== null && $next < $floor) {
            $next = is_finite($floor) ? $floor : $base;
            $capped = true;
        }
        // CVR Down must never RAISE the price: not above the price it started from (the Lower cap can lift a low price
        // up to its floor, which made a Down SKU go up), and not above the live price either.
        if ($pct < 0) {
            $next = min($next, $base);
            $live = (float) ($input['price'] ?? 0);
            if ($live > 0) {
                $next = min($next, $live);
            }
        }
        $next = round($next, 2);
        if (! ($next >= 0.01) || abs($next - $base) < 0.005) {
            return null;
        }

        return ['price' => $next, 'capped' => $capped];
    }

    /** Lowest price the Lower cap allows (higher of the Min NPFT% price and the Min NROI% price). INF = unreachable, null = no limit. */
    public static function floorPrice(array $input, array $cfg): ?float
    {
        $lp = (float) ($input['lp'] ?? 0);
        $cost = $lp > 0 ? $lp : 0.0;
        $ship = (float) ($input['ship'] ?? 0);
        $margin = (float) ($input['percentage'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0)) {
            $margin = AmazonDilGroiRule::TAKE_HOME;
        }
        $ads = max(0.0, (float) ($input['ads'] ?? 0)) * 100;
        $floor = null;
        if (isset($cfg['min_npft']) && is_numeric($cfg['min_npft'])) {
            $k = $margin - ((float) $cfg['min_npft'] + $ads) / 100;
            $f = $k > 0 ? ($ship + $cost) / $k : INF;
            $floor = $floor === null ? $f : max($floor, $f);
        }
        if (isset($cfg['min_nroi']) && is_numeric($cfg['min_nroi']) && $cost > 0) {
            $k = $margin - $ads / 100;
            $f = $k > 0 ? ($cost * (1 + (float) $cfg['min_nroi'] / 100) + $ship) / $k : INF;
            $floor = $floor === null ? $f : max($floor, $f);
        }

        return $floor;
    }

    private static function band(mixed $v, float $default, float $max): float
    {
        return (is_numeric($v) && (float) $v >= 0 && (float) $v < $max + 1) ? (float) $v : $default;
    }
}
