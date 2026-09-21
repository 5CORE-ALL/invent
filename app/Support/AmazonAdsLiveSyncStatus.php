<?php

namespace App\Support;

use App\Models\AmazonAdsLiveSyncState;

/**
 * Column-wise BGT/BID sync status from verified Amazon live state (not push-API success).
 */
final class AmazonAdsLiveSyncStatus
{
    public const GREEN = 'green';

    public const YELLOW = 'yellow';

    public const RED = 'red';

    /**
     * @param  array<string, mixed>|AmazonAdsLiveSyncState|null  $state
     * @return array{color: string, tip: string, status: string, reason: string}
     */
    public static function present(string $field, mixed $state, mixed $fallbackDesired = null): array
    {
        $field = $field === 'bid' ? 'bid' : 'bgt';
        $label = $field === 'bid' ? 'BID' : 'BGT';
        $suggested = $field === 'bid' ? 'SBID' : 'SBGT';
        $row = self::normalizeState($state);

        if ($row === null) {
            return [
                'color' => self::YELLOW,
                'status' => 'pending',
                'reason' => 'not_verified',
                'tip' => "Pending — {$label} has not been pulled from Amazon and verified yet",
            ];
        }

        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $reason = trim((string) ($row['reason'] ?? ''));
        $desired = self::money($row['desired_value'] ?? $fallbackDesired);
        $live = self::money($row['live_value'] ?? null);
        $detail = is_array($row['detail'] ?? null) ? $row['detail'] : [];
        $pullAttempts = self::intish($detail['pull_attempts'] ?? $row['attempts'] ?? 0);
        $pushAttempts = self::intish($detail['push_attempts'] ?? 0);
        $verifyAttempts = self::intish($detail['verify_attempts'] ?? 0);
        $attempts = self::intish($row['attempts'] ?? ($pullAttempts + $pushAttempts + $verifyAttempts));
        $oldLive = self::money($detail['old_live'] ?? null);
        $verifiedLive = self::money($detail['verified_live'] ?? $row['live_value'] ?? null);

        if ($status === 'synced') {
            return [
                'color' => self::GREEN,
                'status' => 'synced',
                'reason' => $reason !== '' ? $reason : 'verified',
                'tip' => self::greenTip($label, $suggested, $reason, $verifiedLive ?? $live, $desired),
            ];
        }

        if ($status === 'failed') {
            return [
                'color' => self::RED,
                'status' => 'failed',
                'reason' => $reason !== '' ? $reason : 'failed',
                'tip' => self::redTip(
                    $label,
                    $suggested,
                    $reason,
                    $live ?? $oldLive,
                    $desired,
                    $pullAttempts,
                    $pushAttempts,
                    $verifyAttempts,
                    $attempts
                ),
            ];
        }

        return [
            'color' => self::YELLOW,
            'status' => $status !== '' ? $status : 'pending',
            'reason' => $reason !== '' ? $reason : 'pending',
            'tip' => self::yellowTip($label, $suggested, $reason, $status, $live, $desired, $pushAttempts, $verifyAttempts),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{bgt?: array<string, mixed>, bid?: array<string, mixed>}  $statesByFieldAndCid
     * @return list<array<string, mixed>>
     */
    public static function attachToRows(array $rows, array $statesByFieldAndCid): array
    {
        foreach ($rows as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $cid = trim((string) ($row['campaign_id'] ?? ''));
            $digits = preg_replace('/\D+/', '', $cid) ?: '';
            foreach (['bgt', 'bid'] as $field) {
                $map = is_array($statesByFieldAndCid[$field] ?? null) ? $statesByFieldAndCid[$field] : [];
                $state = $map[$cid] ?? ($digits !== '' ? ($map[$digits] ?? null) : null);
                $desired = $field === 'bid'
                    ? ($row['sbid'] ?? $row['last_sbid'] ?? null)
                    : ($row['sbgt'] ?? null);
                $presented = self::present($field, $state, $desired);
                if ($field === 'bid' && $presented['color'] !== self::RED && self::displayedBidsMatch($row['last_sbid'] ?? null, $row['sbid'] ?? $desired)) {
                    $shown = self::money($row['last_sbid'] ?? null);
                    $want = self::money($row['sbid'] ?? $desired);
                    $presented = [
                        'color' => self::GREEN,
                        'status' => 'synced',
                        'reason' => 'already_matched',
                        'tip' => self::greenTip('BID', 'SBID', 'already_matched', $shown, $want),
                    ];
                }
                $row[$field.'_sync_color'] = $presented['color'];
                $row[$field.'_sync_tip'] = $presented['tip'];
                $row[$field.'_sync_status'] = $presented['status'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{bgt: array{green: int, yellow: int, red: int}, bid: array{green: int, yellow: int, red: int}}
     */
    public static function countColors(array $rows): array
    {
        $out = [
            'bgt' => ['green' => 0, 'yellow' => 0, 'red' => 0],
            'bid' => ['green' => 0, 'yellow' => 0, 'red' => 0],
        ];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['bgt', 'bid'] as $field) {
                $color = self::normalizeColor($row[$field.'_sync_color'] ?? null) ?? self::YELLOW;
                $out[$field][$color]++;
            }
        }

        return $out;
    }

    public static function displayedBidsMatch(mixed $shown, mixed $desired): bool
    {
        $live = self::numeric($shown);
        $want = self::numeric($desired);
        if ($live === null || $want === null || $live <= 0 || $want <= 0) {
            return false;
        }

        return AmazonAdsApiRetry::valuesMatch($live, $want, 0.015);
    }

    public static function colorFromStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'synced' => self::GREEN,
            'failed' => self::RED,
            default => self::YELLOW,
        };
    }

    public static function normalizeColor(?string $raw): ?string
    {
        $v = strtolower(trim((string) $raw));

        return in_array($v, [self::GREEN, self::YELLOW, self::RED], true) ? $v : null;
    }

    /**
     * @return list<string>
     */
    public static function statusesForColor(string $color): array
    {
        return match (self::normalizeColor($color)) {
            self::GREEN => ['synced'],
            self::RED => ['failed'],
            self::YELLOW => ['pending', 'in_progress'],
            default => [],
        };
    }

    public static function yellowIncludesMissingState(): bool
    {
        return true;
    }

    public static function statusMatchesColor(?string $status, string $color): bool
    {
        $want = self::normalizeColor($color);
        if ($want === null) {
            return false;
        }
        $have = strtolower(trim((string) $status));
        if ($have === '') {
            return $want === self::YELLOW;
        }

        return self::colorFromStatus($have) === $want;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeState(mixed $state): ?array
    {
        if ($state instanceof AmazonAdsLiveSyncState) {
            return $state->toArray();
        }
        if (! is_array($state) || $state === []) {
            return null;
        }

        return $state;
    }

    private static function greenTip(string $label, string $suggested, string $reason, ?string $live, ?string $desired): string
    {
        if ($reason === 'paused_zero_sbgt') {
            $want = $desired ?? '$0.00';

            return "Updated — {$suggested} {$want}; Amazon campaign verified PAUSED";
        }
        $liveTxt = $live ?? 'n/a';
        $wantTxt = $desired ?? 'n/a';
        if ($reason === 'verified_after_push') {
            return "Updated — Amazon {$label} {$liveTxt} matches {$suggested} {$wantTxt}";
        }

        return "Updated — Amazon {$label} {$liveTxt} matches {$suggested} {$wantTxt}";
    }

    private static function redTip(
        string $label,
        string $suggested,
        string $reason,
        ?string $live,
        ?string $desired,
        int $pullAttempts,
        int $pushAttempts,
        int $verifyAttempts,
        int $attempts
    ): string {
        if (self::isRateLimited($reason)) {
            $extra = self::reasonDetail($reason, ['pull_failed:', 'push_failed:', 'verify_failed:', 'pause_failed:']);

            return 'Rate Limited — Amazon API rate limit; automatic retry scheduled'
                .($extra !== '' ? ' ('.$extra.')' : '');
        }

        if (str_starts_with($reason, 'pull_failed')) {
            $n = $pullAttempts > 0 ? $pullAttempts : max(1, $attempts);
            $err = self::reasonDetail($reason, ['pull_failed:']);

            return "Pull Failed — Live Amazon {$label} could not be retrieved after {$n} attempts"
                .($err !== '' ? '. '.$err : '');
        }

        if (str_starts_with($reason, 'verify_failed')) {
            $n = $pushAttempts > 0 ? $pushAttempts : max(1, $verifyAttempts, $attempts);
            $remain = $live ?? 'n/a';
            $want = $desired ?? 'n/a';

            return "Failed — Amazon {$label} remains {$remain}; expected {$want} after {$n} push attempts";
        }

        if (str_starts_with($reason, 'push_failed')) {
            $n = $pushAttempts > 0 ? $pushAttempts : max(1, $attempts);
            $err = self::reasonDetail($reason, ['push_failed:']);

            return "Failed — Amazon {$label} push did not succeed after {$n} attempts"
                .($err !== '' ? '. '.$err : '')
                .($desired !== null ? "; expected {$desired}" : '');
        }

        if (str_starts_with($reason, 'pause_failed')) {
            $n = $pushAttempts > 0 ? $pushAttempts : max(1, $attempts);
            $err = self::reasonDetail($reason, ['pause_failed:']);

            return "Failed — Amazon campaign pause did not succeed after {$n} attempts"
                .($err !== '' ? '. '.$err : '');
        }

        $remain = $live ?? 'n/a';
        $want = $desired ?? 'n/a';
        $why = $reason !== '' ? $reason : 'Amazon live value does not match '.$suggested;

        return "Failed — Amazon {$label} remains {$remain}; expected {$want}. {$why}";
    }

    private static function yellowTip(
        string $label,
        string $suggested,
        string $reason,
        string $status,
        ?string $live,
        ?string $desired,
        int $pushAttempts,
        int $verifyAttempts
    ): string {
        if ($reason === 'concurrent_sync' || $status === 'in_progress') {
            return "Pending — another sync is already running for this {$label}";
        }
        if ($reason === 'push_succeeded_waiting_verify' || ($pushAttempts > 0 && $verifyAttempts === 0)) {
            $want = $desired ?? 'n/a';

            return "Pending — Push succeeded; waiting for Amazon {$label} verification (expected {$want})";
        }
        if ($reason === 'pulling_live') {
            return "Pending — Pulling live Amazon {$label} for verification against {$suggested}"
                .($desired !== null ? " {$desired}" : '');
        }
        if ($reason === 'pushing') {
            return "Pending — Pushing {$label} ".($desired ?? '').' to Amazon';
        }
        if ($reason === 'retrying') {
            return "Pending — Retrying Amazon {$label} sync"
                .($desired !== null ? "; expected {$desired}" : '');
        }
        if (str_contains(strtolower($reason), 'partial') || $reason === 'partial') {
            $want = $desired ?? 'n/a';
            $have = $live ?? 'n/a';

            return "Partial — Amazon {$label} {$have} is not fully verified against {$suggested} {$want}";
        }
        if ($reason !== '' && $reason !== 'pending' && $reason !== 'not_verified') {
            return 'Pending — '.$reason
                .($desired !== null ? "; expected {$desired}" : '')
                .($live !== null ? "; live {$live}" : '');
        }

        return "Pending — Live Amazon {$label} not verified yet"
            .($desired !== null ? "; expected {$suggested} {$desired}" : '');
    }

    /**
     * @param  list<string>  $prefixes
     */
    private static function reasonDetail(string $reason, array $prefixes): string
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($reason, $prefix)) {
                return trim(substr($reason, strlen($prefix)));
            }
        }

        return trim($reason);
    }

    private static function isRateLimited(string $reason): bool
    {
        $r = strtolower($reason);

        return str_contains($r, '429')
            || str_contains($r, 'rate limit')
            || str_contains($r, 'too many')
            || str_contains($r, 'throttl');
    }

    private static function numeric(mixed $v): ?float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }
        $n = (float) $v;

        return is_finite($n) ? $n : null;
    }

    private static function money(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return '$'.number_format((float) $v, 2);
    }

    private static function intish(mixed $v): int
    {
        return is_numeric($v) ? (int) $v : 0;
    }
}
