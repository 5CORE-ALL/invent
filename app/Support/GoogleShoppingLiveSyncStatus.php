<?php

namespace App\Support;

/**
 * Green LBid / LBgt dots for /google/shopping/google-shopping.
 * Each column is independent: a dot shows only when that value was fetched,
 * the matching SBID or SBGT was pushed, and the live number still matches.
 */
final class GoogleShoppingLiveSyncStatus
{
    public const BID_TOLERANCE = 0.015;

    public const BGT_TOLERANCE = 0.01;

    /**
     * @param  array<string, mixed>|null  $state
     * @return array{lbid: float|null, lbid_green: bool, lbid_tip: string, lbgt: float|null, lbgt_green: bool, lbgt_tip: string}
     */
    public static function columns(?array $state, mixed $sbid, mixed $sbgt): array
    {
        $bid = self::evaluate('bid', $state ?? [], $sbid);
        $bgt = self::evaluate('bgt', $state ?? [], $sbgt);

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
        $errorKey = $isBid ? 'bid_fetch_error' : 'bgt_fetch_error';
        $tolerance = $isBid ? self::BID_TOLERANCE : self::BGT_TOLERANCE;
        $liveLabel = $isBid ? 'Live Bid' : 'Live Budget';
        $suggestedLabel = $isBid ? 'SBID' : 'SBGT';

        $fetchOk = self::boolish($state[$fetchKey] ?? false);
        $pushOk = self::boolish($state[$pushKey] ?? false);
        $live = $fetchOk ? self::positiveNumber($state[$liveKey] ?? null) : null;
        $pushed = $pushOk ? self::positiveNumber($state[$pushedKey] ?? null) : null;
        $want = self::positiveNumber($suggested);
        $error = trim((string) ($state[$errorKey] ?? ''));

        $value = $live;
        $matchesLive = self::amountsMatch($live, $want, $tolerance);
        $matchesPush = self::amountsMatch($pushed, $want, $tolerance);
        $green = $fetchOk && $pushOk && $value !== null && $matchesLive && $matchesPush;

        return [
            'value' => $value,
            'fetch_ok' => $fetchOk && $value !== null,
            'push_ok' => $pushOk && $pushed !== null,
            'green' => $green,
            'tip' => self::tip($liveLabel, $suggestedLabel, $green, $fetchOk, $pushOk, $value, $want, $pushed, $error, $matchesLive),
        ];
    }

    private static function tip(
        string $liveLabel,
        string $suggestedLabel,
        bool $green,
        bool $fetchOk,
        bool $pushOk,
        ?float $live,
        ?float $want,
        ?float $pushed,
        string $error,
        bool $matchesLive
    ): string {
        if ($green) {
            return 'Updated — '.$liveLabel.' '.self::money($live).' matches '.$suggestedLabel.' '.self::money($want).' (fetched and pushed)';
        }
        if (! $fetchOk || $live === null) {
            if ($error === 'mixed') {
                return $liveLabel.' values differ across Google Ads — no single amount to compare with '.$suggestedLabel;
            }
            if ($error === 'missing') {
                return $liveLabel.' was not returned by Google Ads for this campaign';
            }
            if ($error !== '') {
                return $liveLabel.' could not be fetched. '.$error;
            }

            return $liveLabel.' has not been fetched yet';
        }
        if ($want === null) {
            return $liveLabel.' '.self::money($live).' — no '.$suggestedLabel.' to compare';
        }
        if (! $matchesLive) {
            return $liveLabel.' '.self::money($live).' does not match '.$suggestedLabel.' '.self::money($want);
        }
        if (! $pushOk || $pushed === null) {
            return $liveLabel.' '.self::money($live).' matches '.$suggestedLabel.' '.self::money($want).' — not pushed yet';
        }
        if (! self::amountsMatch($pushed, $want, $liveLabel === 'Live Bid' ? self::BID_TOLERANCE : self::BGT_TOLERANCE)) {
            return $liveLabel.' '.self::money($live).' matches the last push '.self::money($pushed).', but '.$suggestedLabel.' is now '.self::money($want);
        }

        return $liveLabel.' '.self::money($live).' is not verified against '.$suggestedLabel.' '.self::money($want);
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
