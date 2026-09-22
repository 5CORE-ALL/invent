<?php

namespace App\Support;

/**
 * Stored LBid / LBgt for /google/shopping/google-shopping.
 * The page only reads values already verified by the background queue.
 * A failed Google Ads pull does not change the last stored amount or dot.
 */
final class GoogleShoppingLiveSyncStatus
{
    public const BID_TOLERANCE = 0.015;

    public const BGT_TOLERANCE = 0.01;

    /**
     * Decide whether a pulled live amount may replace the stored value.
     * Null means the pull failed or returned nothing, so the last verified row stays.
     *
     * @return array{value: float, green: bool}|null
     */
    public static function verifiedStore(string $field, ?float $live, ?string $error, mixed $suggested): ?array
    {
        $isBid = $field === 'bid';
        $error = trim((string) ($error ?? ''));
        if ($error !== '' || $live === null || ! is_finite($live) || $live <= 0) {
            return null;
        }

        $rounded = round($live, $isBid ? 4 : 2);
        $want = self::positiveNumber($suggested);
        $tolerance = $isBid ? self::BID_TOLERANCE : self::BGT_TOLERANCE;

        return [
            'value' => $rounded,
            'green' => self::amountsMatch($rounded, $want, $tolerance),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $state
     * @return array{lbid: float|null, lbid_green: bool, lbid_tip: string, lbgt: float|null, lbgt_green: bool, lbgt_tip: string}
     */
    public static function columns(?array $state, mixed $sbid = null, mixed $sbgt = null): array
    {
        $state = $state ?? [];
        $bid = self::evaluate('bid', $state, $sbid);
        $bgt = self::evaluate('bgt', $state, $sbgt);

        return [
            'lbid' => $bid['value'],
            'lbid_green' => $bid['green'],
            'lbid_tip' => $bid['tip'],
            'lbgt' => $bgt['value'],
            'lbgt_green' => $bgt['green'],
            'lbgt_tip' => $bgt['tip'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $state
     * @return array{value: float|null, fetch_ok: bool, push_ok: bool, green: bool, tip: string}
     */
    public static function fieldRow(string $field, ?array $state, mixed $suggested): array
    {
        $eval = self::evaluate($field === 'bid' ? 'bid' : 'bgt', $state ?? [], $suggested);

        return [
            'value' => $eval['value'],
            'fetch_ok' => $eval['fetch_ok'],
            'push_ok' => $eval['push_ok'],
            'green' => $eval['green'],
            'tip' => $eval['tip'],
        ];
    }

    public static function dollarsFromMicros(mixed $micros): ?float
    {
        if ($micros === null || $micros === '' || ! is_numeric($micros)) {
            return null;
        }
        $n = ((float) $micros) / 1000000;
        if (! is_finite($n) || $n <= 0) {
            return null;
        }

        return round($n, 2);
    }

    /**
     * One live amount when every positive sample is within tolerance.
     * Null when there is no positive sample or the samples disagree.
     *
     * @param  list<float|int|string>  $amounts
     */
    public static function uniformPositive(array $amounts, float $tolerance): ?float
    {
        $vals = [];
        foreach ($amounts as $amount) {
            if (! is_numeric($amount)) {
                continue;
            }
            $n = (float) $amount;
            if (! is_finite($n) || $n <= 0) {
                continue;
            }
            $vals[] = $n;
        }
        if ($vals === []) {
            return null;
        }
        if ((max($vals) - min($vals)) > $tolerance) {
            return null;
        }

        return round($vals[0], 2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<float>>
     */
    public static function positiveDollarsByCampaign(array $rows, string $microsKey): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = preg_replace('/\D+/', '', (string) ($row['campaign']['id'] ?? '')) ?? '';
            if ($cid === '') {
                continue;
            }
            $micros = self::microsFromRow($row, $microsKey);
            $dollars = self::dollarsFromMicros($micros);
            if ($dollars === null) {
                continue;
            }
            $out[$cid][] = $dollars;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{value: float|null, fetch_ok: bool, push_ok: bool, green: bool, tip: string}
     */
    private static function evaluate(string $field, array $state, mixed $suggested): array
    {
        $isBid = $field === 'bid';
        $liveKey = $isBid ? 'live_bid' : 'live_bgt';
        $fetchKey = $isBid ? 'bid_fetch_ok' : 'bgt_fetch_ok';
        $pushKey = $isBid ? 'bid_push_ok' : 'bgt_push_ok';
        $pushedKey = $isBid ? 'bid_pushed_value' : 'bgt_pushed_value';
        $liveLabel = $isBid ? 'Live Bid' : 'Live Budget';
        $suggestedLabel = $isBid ? 'SBID' : 'SBGT';

        $greenKey = $isBid ? 'bid_green' : 'bgt_green';
        $fetchOk = self::boolish($state[$fetchKey] ?? false);
        $pushOk = self::boolish($state[$pushKey] ?? false);
        $live = self::positiveNumber($state[$liveKey] ?? null);
        $pushed = self::positiveNumber($state[$pushedKey] ?? null);
        $green = self::boolish($state[$greenKey] ?? false) && $live !== null;

        return [
            'value' => $live,
            'fetch_ok' => $fetchOk && $live !== null,
            'push_ok' => $pushOk && $pushed !== null,
            'green' => $green,
            'tip' => self::storedTip($liveLabel, $suggestedLabel, $green, $live),
        ];
    }

    private static function storedTip(string $liveLabel, string $suggestedLabel, bool $green, ?float $live): string
    {
        if ($live === null) {
            return $liveLabel.' has not been verified yet';
        }
        if ($green) {
            return 'Verified — '.$liveLabel.' '.self::money($live).' matches '.$suggestedLabel;
        }

        return $liveLabel.' '.self::money($live).' does not match '.$suggestedLabel;
    }

    private static function amountsMatch(?float $a, ?float $b, float $tolerance): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        return abs($a - $b) <= $tolerance;
    }

    private static function positiveNumber(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;
        if (! is_finite($n) || $n <= 0) {
            return null;
        }

        return $n;
    }

    private static function boolish(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private static function money(?float $amount): string
    {
        if ($amount === null) {
            return 'n/a';
        }

        return '$'.number_format($amount, 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function microsFromRow(array $row, string $microsKey): mixed
    {
        if ($microsKey === 'cpc') {
            $criterion = $row['adGroupCriterion'] ?? $row['ad_group_criterion'] ?? null;
            if (is_array($criterion)) {
                $fromCriterion = $criterion['cpcBidMicros'] ?? $criterion['cpc_bid_micros'] ?? null;
                if ($fromCriterion !== null && $fromCriterion !== '') {
                    return $fromCriterion;
                }
            }
            $adGroup = $row['adGroup'] ?? $row['ad_group'] ?? [];

            return is_array($adGroup) ? ($adGroup['cpcBidMicros'] ?? $adGroup['cpc_bid_micros'] ?? null) : null;
        }

        $budget = $row['campaignBudget'] ?? $row['campaign_budget'] ?? [];

        return is_array($budget) ? ($budget['amountMicros'] ?? $budget['amount_micros'] ?? null) : null;
    }
};
