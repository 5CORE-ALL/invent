<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\CvrMasterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-side version of the "SPRICE $" box on /price-increase (SKU modal toolbar).
 *
 * One typed price X is turned into a SPRICE for every Channel Master channel by the same column rules the
 * Channels modal uses, then saved through /cvr-master-save-suggested-data (so /price-increase, the channel
 * pages, Temu<->Temu2 and eBay1/2/3 pairing all see exactly the same data):
 *
 *   B2C and the rest ............ X
 *   B2B, C2C .................... 0.75 x X
 *   B2B without ship (SB2B, TopDawg, Faire) ... 0.75 x X - Ship
 *   High Shipping ............... X + Shipping Master Diff (Ship BB - Ship)
 *   Doba ........................ always X x 0.75 (-25%)
 *
 * Nothing is pushed live here - same as typing in the SPRICE $ box (auto-saved, not pushed).
 */
class ChannelSpriceRuleService
{
    public const NOSHIP_GROUP = 'B2B without ship';

    /** Channel Master name -> key used by the SKU modal rows. Same map as CHPUSH_ALIASES on /price-increase. */
    private const ALIASES = [
        'bestbuyusa' => 'bestbuy',
        'macys' => 'macy',
        'purchasingpower' => 'ppower',
        'ebay' => 'ebay1',
        'ebaytwo' => 'ebay2',
        'ebaythree' => 'ebay3',
        'tiktokshop' => 'tiktok',
        'business5coreb2b' => 'sb2b',
    ];

    /** Channels /cvr-master-save-suggested-data can store (same as CHSAVE_SUPPORTED on /price-increase). */
    private const SAVE_SUPPORTED = [
        'amazon', 'fba', 'ebay', 'ebay1', 'ebay2', 'ebay3', 'ebaytwo', 'ebaythree', 'temu', 'temu2', 'doba', 'walmart',
        'tiktok', 'tiktok2', 'bestbuy', 'bestbuyusa', 'macy', 'macys', 'reverb', 'topdawg', 'shein', 'faire',
        'aliexpress', 'ppower', 'purchasingpower', 'purchase', 'shopify', 'shopifyb2c', 'sb2c', 'shopifyb2b', 'sb2b',
        'pls', 'newegg', 'wayfair', 'temu3', 'alibaba', 'cjdropshipping', 'b2b', 'depop', 'vinted', 'instagramshop',
        'mercariwship', 'mercariwoship', 'fbmarketplace',
    ];

    /** Shipping is not applied on these channels (own "B2B without ship" column). */
    private const NOSHIP_KEYS = ['sb2b', 'topdawg', 'faire'];

    /** Channels whose SPFT subtracts the channel Ads% (ovl30UsesChannelAdsForSpft on /price-increase). */
    private const CHANNEL_ADS_SPFT = [
        'amazon', 'fba', 'tiktok', 'tiktok2', 'tiktok 2', 'bestbuy', 'macy', 'aliexpress', 'reverb',
        'sb2c', 'shopify', 'shopifyb2c', 'ebay', 'ebay1', 'ebaytwo', 'ebay2', 'ebaythree', 'ebay3',
    ];

    private const TEMU_S_RECOVERY_RATE = 0.88;

    /**
     * Save X on every channel of the SKU by the channel rules.
     *
     * @return array{saved: int, failed: int, skipped: int, channels: list<array<string, mixed>>}
     */
    public function applyToSku(string $sku, float $x): array
    {
        $cvr = app(CvrMasterController::class);
        $x = round($x, 2);

        $rows = $this->breakdownRows($cvr, $sku);
        $byKey = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $this->key((string) ($row['marketplace'] ?? ''));
            if ($key !== '' && ! isset($byKey[$key])) {
                $byKey[$key] = $row;
            }
        }

        $shipAmount = $this->shipAmount($byKey);
        $shipDiff = $this->shipDiff($byKey);

        $saved = 0;
        $failed = 0;
        $skipped = 0;
        $report = [];
        $done = [];

        foreach ($this->channelsWithType($cvr) as $channel) {
            $name = $channel['name'];
            $key = $this->key($name);
            $price = $this->priceFor($key, $channel['type'], $x, $shipAmount, $shipDiff);
            if ($price === null) {
                $skipped++;
                continue;
            }

            $row = $byKey[$key] ?? null;
            $mp = $row ? (string) ($row['marketplace'] ?? $key) : $key;
            $mpCompact = preg_replace('/\s+/', '', strtolower($mp)) ?? '';
            if (! in_array(strtolower($mp), self::SAVE_SUPPORTED, true) && ! in_array($mpCompact, self::SAVE_SUPPORTED, true)) {
                $skipped++;
                continue;
            }
            if (isset($done[$mpCompact])) {
                continue;
            }
            $done[$mpCompact] = true;

            $current = $row ? (float) ($row['sprice'] ?? 0) : 0.0;
            if ($current > 0 && abs($current - $price) < 0.005) {
                $skipped++;
                $report[] = ['channel' => $name, 'price' => $price, 'status' => 'unchanged'];
                continue;
            }

            $payload = ['sku' => $sku, 'marketplace' => $mp, 'sprice' => $price, 'apply_siblings' => 0];
            if ($row) {
                $payload = array_merge($payload, $this->metricsFor(strtolower($mp), $price, $row));
            }

            if ($this->save($cvr, $payload)) {
                $saved++;
                $report[] = ['channel' => $name, 'price' => $price, 'status' => 'saved'];
            } else {
                $failed++;
                $report[] = ['channel' => $name, 'price' => $price, 'status' => 'failed'];
            }
        }

        return ['saved' => $saved, 'failed' => $failed, 'skipped' => $skipped, 'channels' => $report];
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    private function channelsWithType(CvrMasterController $cvr): array
    {
        $groups = $cvr->pricingChannelsByType()['byType'] ?? [];
        // Same moves as the Channels modal: Alibaba -> B2B, Dropship -> B2B, Sheets -> C2C
        $override = ['alibaba' => 'B2B'];
        $moves = ['dropship' => 'B2B', 'ds' => 'B2B', 'sheets' => 'C2C'];

        $out = [];
        foreach ($groups as $type => $names) {
            foreach ((array) $names as $name) {
                $name = (string) $name;
                $nameKey = preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';
                $target = $override[$nameKey] ?? $moves[strtolower(trim((string) $type))] ?? (string) $type;
                if (in_array($this->key($name), self::NOSHIP_KEYS, true)) {
                    $target = self::NOSHIP_GROUP;
                }
                $out[] = ['name' => $name, 'type' => $target];
            }
        }

        return $out;
    }

    private function priceFor(string $key, string $type, float $x, float $shipAmount, ?float $shipDiff): ?float
    {
        // Doba always follows its own rule: 25% less than the typed price
        if ($key === 'doba') {
            return max(0.01, round($x * 0.75, 2));
        }

        $t = strtolower(trim($type));
        if ($t === 'b2b' || $t === 'c2c') {
            return round($x * 0.75, 2);
        }
        if ($t === strtolower(self::NOSHIP_GROUP)) {
            return max(0.01, round(($x * 0.75) - $shipAmount, 2));
        }
        if ($t === 'high shipping') {
            // No Diff (Ship BB / Ship missing): X itself, so High Shipping channels are never left unchanged
            return max(0.01, round($x + ($shipDiff ?? 0.0), 2));
        }

        return $x;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function breakdownRows(CvrMasterController $cvr, string $sku): array
    {
        @ini_set('memory_limit', '512M'); // same as /price-increase: the breakdown does not fit the 128M default
        @set_time_limit(180);
        $response = $cvr->getBreakdownData(Request::create('/cvr-master-breakdown', 'GET', ['sku' => $sku]));
        $data = $response->getData(true);
        if (! is_array($data) || isset($data['error'])) {
            throw new \RuntimeException('Could not load channel data for '.$sku);
        }

        return array_values($data);
    }

    private function key(string $name): string
    {
        $k = preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';

        return self::ALIASES[$k] ?? $k;
    }

    /** Shipping $ of the SKU: AliExpress row, else Amazon row; 0 when unknown (same as chpushShipAmount). */
    private function shipAmount(array $byKey): float
    {
        foreach (['aliexpress', 'amazon'] as $want) {
            $ship = isset($byKey[$want]['ship']) ? (float) $byKey[$want]['ship'] : 0.0;
            if ($ship > 0) {
                return $ship;
            }
        }

        return 0.0;
    }

    /** Shipping Master Diff (Ship BB - Ship) from the AliExpress row; null when unavailable. */
    private function shipDiff(array $byKey): ?float
    {
        $diff = $byKey['aliexpress']['ship_diff'] ?? null;

        return is_numeric($diff) ? (float) $diff : null;
    }

    /**
     * SGPFT / SPFT / SROI saved next to the price - same formulas as saveModalSpriceForRow() on /price-increase.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, float>
     */
    private function metricsFor(string $mpLower, float $sprice, array $row): array
    {
        $isPp = in_array($mpLower, ['ppower', 'purchasingpower', 'purchase'], true);
        $isTd = $mpLower === 'topdawg';
        $isShein = $mpLower === 'shein';
        $isFaire = $mpLower === 'faire';
        $isSb2b = in_array($mpLower, ['sb2b', 'shopifyb2b', 'shopify_b2b'], true);
        $isNoAds = $mpLower === 'doba' || $isPp || $isTd || $isShein || $isFaire;
        $isTemu = $mpLower === 'temu' || $mpLower === 'temu2';

        $lp = (float) ($row['lp'] ?? 0);
        $ad = (float) ($row['ad'] ?? 0);
        $l30 = (float) ($row['l30'] ?? 0);
        $ship = ($isPp || $isTd || $isFaire || $isSb2b) ? 0.0 : (float) ($row['ship'] ?? 0);
        $tacos = ($isNoAds || $mpLower === 'temu2') ? 0.0 : (float) ($row['tacos_ch'] ?? 0);

        if (isset($row['margin']) && $row['margin'] !== '' && is_numeric($row['margin'])) {
            $margin = (float) $row['margin'];
        } else {
            $margin = match (true) {
                $mpLower === 'doba' => 0.95,
                $mpLower === 'reverb' => 0.85,
                $isPp => 0.65,
                $isTd => 0.0,
                default => 0.80,
            };
        }

        $calcSp = ($isTemu && $sprice <= 26.99) ? $sprice + 2.99 : $sprice;

        if ($isTemu) {
            $temu = $this->temuPercents($sprice, $margin, $lp, $ship, $tacos, $mpLower === 'temu2');
            $sgpft = $temu['sgpft'];
            $sroi = $temu['sroi'];
            $spft = $temu['spft'];
        } else {
            $sgpft = $calcSp > 0 ? (($calcSp * $margin - $ship - $lp) / $calcSp) * 100 : 0.0;
            $sroi = $lp > 0 ? (($calcSp * $margin - $lp - $ship) / $lp) * 100 : 0.0;
            if ($isNoAds) {
                $spft = $sgpft;
            } elseif (in_array($mpLower, self::CHANNEL_ADS_SPFT, true)) {
                $spft = $sgpft - $tacos;
            } else {
                $spft = $l30 == 0.0 ? $sgpft : ($sgpft - $ad);
            }
        }

        return [
            'sgpft' => round($sgpft, 4),
            'spft' => round($spft, 4),
            'sroi' => round($sroi, 4),
            'amazon_margin' => $margin,
        ];
    }

    /** Temu / Temu2 percentages - same as temuSuggestedPercents() on /price-increase and /temu-decrease. */
    private function temuPercents(float $sprice, float $margin, float $lp, float $ship, float $adsPct, bool $isTemu2): array
    {
        if (! ($sprice > 0) || ! ($margin > 0)) {
            return ['sgpft' => 0.0, 'sroi' => 0.0, 'spft' => 0.0];
        }
        $ads = $isTemu2 ? 0.0 : $adsPct;
        $sgpft = (($sprice * $margin - $lp - $ship) / $sprice) * 100;
        $sProfit = ($sprice * self::TEMU_S_RECOVERY_RATE * $margin) - $lp - $ship;
        $sroi = $lp > 0 ? ($sProfit / $lp) * 100 : 0.0;
        $spft = $ads == 100.0 ? $sgpft : ($sgpft - $ads);

        return ['sgpft' => $sgpft, 'sroi' => $sroi, 'spft' => $spft];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function save(CvrMasterController $cvr, array $payload): bool
    {
        try {
            $response = $cvr->saveSuggestedData(Request::create('/cvr-master-save-suggested-data', 'POST', $payload));
            if ($response->getStatusCode() >= 400) {
                return false;
            }
            $body = $response->getData(true);

            return ! (is_array($body) && array_key_exists('success', $body) && $body['success'] === false);
        } catch (\Throwable $e) {
            Log::warning('LMP Overall SPRICE: channel save failed', [
                'sku' => $payload['sku'] ?? null,
                'marketplace' => $payload['marketplace'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
