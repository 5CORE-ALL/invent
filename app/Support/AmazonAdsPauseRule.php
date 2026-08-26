<?php

namespace App\Support;

use App\Models\AmazonAdsPauseRuleSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Amazon Ads All pause/activate rule: dynamic Pricing / Dil% / ACOS% bands.
 *
 * Default desired state is ENABLED. A campaign is PAUSED only when it matches
 * at least one band whose action is PAUSED. First matching band per section wins.
 */
final class AmazonAdsPauseRule
{
    public const CACHE_KEY = 'amazon_ads_pause_rule_resolved_v1';

    public const ACTION_PAUSED = 'PAUSED';

    public const ACTION_ENABLED = 'ENABLED';

    /**
     * @return array{
     *     pricing: list<array{from: float, to: float, action: string, label: string}>,
     *     dil: list<array{from: float, to: float, action: string, label: string}>,
     *     acos: list<array{from: float, to: float, action: string, label: string}>
     * }
     */
    public static function defaults(): array
    {
        return [
            'pricing' => [],
            'dil' => [],
            'acos' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $rule
     */
    public static function hasBands(?array $rule): bool
    {
        $r = $rule ?? [];

        return ($r['pricing'] ?? []) !== []
            || ($r['dil'] ?? []) !== []
            || ($r['acos'] ?? []) !== [];
    }

    /**
     * @return array{
     *     pricing: list<array{from: float, to: float, action: string, label: string}>,
     *     dil: list<array{from: float, to: float, action: string, label: string}>,
     *     acos: list<array{from: float, to: float, action: string, label: string}>
     * }
     */
    public static function resolvedRule(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 86400, static fn (): array => self::loadResolvedRule());
        } catch (\Throwable) {
            return self::loadResolvedRule();
        }
    }

    /**
     * @return array{
     *     pricing: list<array{from: float, to: float, action: string, label: string}>,
     *     dil: list<array{from: float, to: float, action: string, label: string}>,
     *     acos: list<array{from: float, to: float, action: string, label: string}>
     * }
     */
    private static function loadResolvedRule(): array
    {
        if (! Schema::hasTable('amazon_ads_pause_rule_settings')) {
            return self::defaults();
        }
        $row = AmazonAdsPauseRuleSetting::query()->orderBy('id')->first();
        if ($row === null || ! is_array($row->rule) || $row->rule === []) {
            return self::defaults();
        }

        return self::normalizeRule($row->rule);
    }

    public static function forgetResolvedCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pricing: list<array{from: float, to: float, action: string, label: string}>,
     *     dil: list<array{from: float, to: float, action: string, label: string}>,
     *     acos: list<array{from: float, to: float, action: string, label: string}>
     * }
     */
    public static function normalizeRule(array $input): array
    {
        return [
            'pricing' => self::normalizeBands($input['pricing'] ?? []),
            'dil' => self::normalizeBands($input['dil'] ?? []),
            'acos' => self::normalizeBands($input['acos'] ?? []),
        ];
    }

    /**
     * @param  array{pricing?: mixed, dil?: mixed, acos?: mixed}  $rule
     */
    public static function persistRule(array $rule): void
    {
        if (! Schema::hasTable('amazon_ads_pause_rule_settings')) {
            throw new \RuntimeException('Table amazon_ads_pause_rule_settings does not exist. Run migrations.');
        }
        $normalized = self::normalizeRule($rule);
        $row = AmazonAdsPauseRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsPauseRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetResolvedCache();
    }

    /**
     * @param  array{
     *     pricing?: list<array{from?: float, to?: float, action?: string, label?: string}>,
     *     dil?: list<array{from?: float, to?: float, action?: string, label?: string}>,
     *     acos?: list<array{from?: float, to?: float, action?: string, label?: string}>
     * }  $rule
     * @param  array{price?: float|null, dil?: float|null, acos?: float|null}  $metrics
     * @return array{status: string, reason: string, hits: list<string>}
     */
    public static function decide(?array $rule, array $metrics): array
    {
        $r = $rule ?? self::defaults();
        if (! self::hasBands($r)) {
            return [
                'status' => '',
                'reason' => 'No pause bands configured',
                'hits' => [],
            ];
        }
        $hits = [];

        $priceHit = self::firstMatchingBand($r['pricing'] ?? [], $metrics['price'] ?? null, 'Price', '$');
        if ($priceHit !== null) {
            $hits[] = $priceHit;
        }
        $dilHit = self::firstMatchingBand($r['dil'] ?? [], $metrics['dil'] ?? null, 'Dil%', '%');
        if ($dilHit !== null) {
            $hits[] = $dilHit;
        }
        $acosHit = self::firstMatchingBand($r['acos'] ?? [], $metrics['acos'] ?? null, 'ACOS%', '%');
        if ($acosHit !== null) {
            $hits[] = $acosHit;
        }

        $pauseHits = array_values(array_filter($hits, static fn (array $h): bool => $h['action'] === self::ACTION_PAUSED));
        if ($pauseHits !== []) {
            $reasons = array_map(static fn (array $h): string => $h['reason'], $pauseHits);

            return [
                'status' => self::ACTION_PAUSED,
                'reason' => 'Pause — '.implode('; ', $reasons),
                'hits' => $reasons,
            ];
        }

        return [
            'status' => self::ACTION_ENABLED,
            'reason' => $hits === []
                ? 'Active — no pause rule matched'
                : 'Active — '.implode('; ', array_map(static fn (array $h): string => $h['reason'], $hits)),
            'hits' => array_map(static fn (array $h): string => $h['reason'], $hits),
        ];
    }

    /**
     * @param  list<array{from: float, to: float, action: string, label: string}>  $bands
     * @return array{action: string, reason: string}|null
     */
    private static function firstMatchingBand(array $bands, mixed $value, string $section, string $suffix): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (float) $value;
        if (! is_finite($n)) {
            return null;
        }
        foreach ($bands as $band) {
            $from = (float) ($band['from'] ?? 0);
            $to = (float) ($band['to'] ?? 9999);
            if ($n >= $from && $n <= $to) {
                $action = strtoupper((string) ($band['action'] ?? self::ACTION_PAUSED)) === self::ACTION_ENABLED
                    ? self::ACTION_ENABLED
                    : self::ACTION_PAUSED;
                $label = trim((string) ($band['label'] ?? ''));
                $shown = $suffix === '$'
                    ? ('$'.rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'))
                    : (rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.').$suffix);
                $range = $suffix === '$'
                    ? ('$'.$from.'–$'.$to)
                    : ($from.$suffix.'–'.$to.$suffix);
                $reason = $section.' '.$shown.' in '.$range;
                if ($label !== '') {
                    $reason .= ' ('.$label.')';
                }

                return ['action' => $action, 'reason' => $reason];
            }
        }

        return null;
    }

    /**
     * @param  mixed  $bands
     * @return list<array{from: float, to: float, action: string, label: string}>
     */
    private static function normalizeBands(mixed $bands): array
    {
        if (! is_array($bands)) {
            return [];
        }
        $out = [];
        foreach ($bands as $i => $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = (float) ($band['from'] ?? 0);
            $to = (float) ($band['to'] ?? 9999);
            if (! is_finite($from) || ! is_finite($to)) {
                throw new \InvalidArgumentException('Band '.($i + 1).': From and To must be finite numbers.');
            }
            if ($from > $to) {
                throw new \InvalidArgumentException('Band '.($i + 1).': From must be ≤ To.');
            }
            $action = strtoupper(trim((string) ($band['action'] ?? self::ACTION_PAUSED)));
            if ($action !== self::ACTION_ENABLED) {
                $action = self::ACTION_PAUSED;
            }
            $out[] = [
                'from' => $from,
                'to' => $to,
                'action' => $action,
                'label' => (string) ($band['label'] ?? ''),
            ];
        }

        return $out;
    }
}
