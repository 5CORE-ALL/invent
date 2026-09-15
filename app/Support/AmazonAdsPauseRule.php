<?php

namespace App\Support;

use App\Models\AmazonAdsPauseRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Amazon Ads All pause/activate rule: Dil% only.
 *
 * Price and Reviews rules are retired. Dil% ≥ threshold pauses PARENT and child
 * SKU campaigns. Only PARENT campaigns are turned back on after a recent Pause
 * Rule pause when Dil% no longer matches. Child SKU campaigns stay paused.
 */
final class AmazonAdsPauseRule
{
    public const CACHE_KEY = 'amazon_ads_pause_rule_resolved_v10';

    public const ACTION_PAUSED = 'PAUSED';

    public const ACTION_ENABLED = 'ENABLED';

    /** Only turn back on Pause Rule pauses from this window. */
    public const REACTIVATE_WITHIN_DAYS = 31;

    /** pink_dil_paused_at before this is the old pink-DIL cron, not Pause Rule. */
    public const PAUSE_RULE_STARTED_AT = '2026-08-26 00:00:00';

    /**
     * @return array{
     *     pricing: list<array{from: float, to: float, action: string, label: string}>,
     *     dil: list<array{from: float, to: float, action: string, label: string}>,
     *     acos: list<array{from: float, to: float, action: string, label: string}>,
     *     pr: array{enabled: bool, dil_above: float, dil_enabled: bool, price_below: float, price_enabled: bool, reviews_enabled: bool, reviews_below: float},
     *     reviews: array{enabled: bool, below: float}
     * }
     */
    public static function defaults(): array
    {
        return [
            'pricing' => [],
            'dil' => [],
            'acos' => [],
            'pr' => self::defaultPr(),
            'reviews' => self::defaultReviews(),
        ];
    }

    /**
     * Reviews product-ad pause is retired and always stored off.
     *
     * @return array{enabled: bool, below: float}
     */
    public static function defaultReviews(): array
    {
        return [
            'enabled' => false,
            'below' => 2.99,
        ];
    }

    /**
     * @return array{enabled: bool, dil_above: float, dil_enabled: bool, price_below: float, price_enabled: bool, reviews_enabled: bool, reviews_below: float}
     */
    public static function defaultPr(): array
    {
        return [
            'enabled' => false,
            'dil_above' => 100.0,
            'dil_enabled' => true,
            'price_below' => 20.0,
            'price_enabled' => false,
            'reviews_enabled' => false,
            'reviews_below' => 2.99,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $rule
     */
    public static function hasBands(?array $rule): bool
    {
        $r = $rule ?? [];

        return self::hasCampaignBands($r);
    }

    /**
     * Pricing / Dil% / ACOS% / PR — these pause the whole campaign.
     *
     * @param  array<string, mixed>|null  $rule
     */
    public static function hasCampaignBands(?array $rule): bool
    {
        $r = $rule ?? [];
        $pr = is_array($r['pr'] ?? null) ? $r['pr'] : [];

        return ! empty($pr['enabled']);
    }

    /**
     * @param  array<string, mixed>|null  $rule
     */
    public static function reviewsEnabled(?array $rule): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>|null  $rule
     */
    public static function reviewsBelow(?array $rule): float
    {
        $reviews = is_array($rule['reviews'] ?? null) ? $rule['reviews'] : [];
        $below = (float) ($reviews['below'] ?? 2.99);

        return is_finite($below) ? $below : 2.99;
    }

    /**
     * True when this SKU's rating should pause its product ad (campaign stays running).
     *
     * @param  array<string, mixed>|null  $rule
     */
    public static function ratingBelowReviewsThreshold(?array $rule, mixed $rating): bool
    {
        return false;
    }

    /**
     * PARENT … KW/PT campaigns. Child SKU campaigns can be paused but never auto-enabled.
     */
    public static function isParentCampaign(?string $campaignName): bool
    {
        $key = AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName($campaignName);

        return str_starts_with($key, 'PARENT ');
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
            'pricing' => [],
            'dil' => [],
            'acos' => [],
            'pr' => self::normalizePr($input['pr'] ?? self::defaultPr()),
            'reviews' => self::normalizeReviews($input['reviews'] ?? self::defaultReviews()),
        ];
    }

    /**
     * @param  array{pricing?: mixed, dil?: mixed, acos?: mixed}  $rule
     */
    public static function persistRule(array $rule): void
    {
        self::ensureSettingsTable();
        $normalized = self::normalizeRule($rule);
        $existing = self::loadResolvedRule();
        if (! array_key_exists('pr', $rule)) {
            $normalized['pr'] = $existing['pr'] ?? self::defaultPr();
        }
        if (! array_key_exists('reviews', $rule)) {
            $normalized['reviews'] = $existing['reviews'] ?? self::defaultReviews();
        }
        $row = AmazonAdsPauseRuleSetting::query()->orderBy('id')->first();
        if ($row === null) {
            AmazonAdsPauseRuleSetting::query()->create(['rule' => $normalized]);
        } else {
            $row->update(['rule' => $normalized]);
        }
        self::forgetResolvedCache();
    }

    /**
     * Create amazon_ads_pause_rule_settings if production skipped the migration.
     */
    private static function ensureSettingsTable(): void
    {
        if (Schema::hasTable('amazon_ads_pause_rule_settings')) {
            return;
        }
        try {
            Schema::create('amazon_ads_pause_rule_settings', function (Blueprint $table) {
                $table->id();
                $table->longText('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            Log::error('amazon_ads_pause_rule_settings create failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Could not create amazon_ads_pause_rule_settings: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array{enabled?: mixed, dil_above?: mixed, dil_enabled?: mixed, price_below?: mixed, price_enabled?: mixed, reviews_enabled?: mixed, reviews_below?: mixed}  $pr
     */
    public static function persistPr(array $pr): void
    {
        $current = self::loadResolvedRule();
        $normalizedPr = self::normalizePr($pr);
        $current['pr'] = $normalizedPr;
        $current['reviews'] = self::defaultReviews();
        self::persistRule($current);
    }

    /**
     * @param  array{enabled?: mixed, below?: mixed}  $reviews
     */
    public static function persistReviews(array $reviews): void
    {
        unset($reviews);
        $current = self::loadResolvedRule();
        $current['reviews'] = self::defaultReviews();
        $pr = is_array($current['pr'] ?? null) ? $current['pr'] : self::defaultPr();
        $pr['reviews_enabled'] = false;
        $current['pr'] = $pr;
        self::persistRule($current);
    }

    /**
     * @param  array{
     *     pricing?: list<array{from?: float, to?: float, action?: string, label?: string}>,
     *     dil?: list<array{from?: float, to?: float, action?: string, label?: string}>,
     *     acos?: list<array{from?: float, to?: float, action?: string, label?: string}>
     * }  $rule
     * @param  array{price?: float|null, dil?: float|null, acos?: float|null, rating?: float|null}  $metrics
     * @return array{status: string, reason: string, hits: list<string>}
     */
    public static function decide(?array $rule, array $metrics, ?string $campaignName = null): array
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

        $pr = is_array($r['pr'] ?? null) ? $r['pr'] : self::defaultPr();
        if (! empty($pr['enabled']) && ! empty($pr['dil_enabled'])) {
            $ownDil = self::finiteDil($metrics['dil'] ?? null);
            $parentDil = self::finiteDil($metrics['parent_dil'] ?? null);
            $dilVal = $ownDil;
            $fromParent = false;
            if ($parentDil !== null && ($dilVal === null || $parentDil > $dilVal)) {
                $dilVal = $parentDil;
                $fromParent = true;
            }
            $threshold = (float) ($pr['dil_above'] ?? 100);
            if ($dilVal !== null && is_finite($threshold) && $dilVal >= $threshold) {
                $shown = rtrim(rtrim(number_format($dilVal, 2, '.', ''), '0'), '.');
                $th = rtrim(rtrim(number_format($threshold, 2, '.', ''), '0'), '.');
                $hits[] = [
                    'action' => self::ACTION_PAUSED,
                    'reason' => 'PR Dil% '.$shown.'% ≥ '.$th.'%'
                        .($fromParent ? ' (PARENT family)' : ''),
                ];
            }
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
     * Re-enable only a PARENT campaign this Dil Pause Rule paused recently.
     * Manual / ACOS / pink-DIL / Price leftovers and child SKUs stay off.
     */
    public static function shouldAutoEnable(
        array $decision,
        string $status,
        mixed $pausedAt,
        ?\DateTimeImmutable $now = null,
        ?string $campaignName = null,
        ?string $pausedReason = null
    ): bool {
        if (! self::isParentCampaign($campaignName ?? '')) {
            return false;
        }
        if (! self::isRecentPauseRuleStamp($pausedAt, $now)) {
            return false;
        }
        if ($pausedReason !== null && ! self::isDilPauseReason($pausedReason)) {
            return false;
        }
        $st = strtoupper(trim($status));
        if ($st === 'ARCHIVED' || $st === self::ACTION_ENABLED) {
            return false;
        }

        return ($decision['status'] ?? '') !== self::ACTION_PAUSED;
    }

    public static function isDilPauseReason(mixed $reason): bool
    {
        return stripos(trim((string) $reason), 'dil') !== false;
    }

    private static function finiteDil(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value) || ! is_finite((float) $value)) {
            return null;
        }

        return (float) $value;
    }

    public static function isRecentPauseRuleStamp(mixed $pausedAt, ?\DateTimeImmutable $now = null): bool
    {
        $at = self::parseStamp($pausedAt);
        if ($at === null) {
            return false;
        }
        $now = $now ?? new \DateTimeImmutable('now');
        $monthAgo = $now->modify('-'.self::REACTIVATE_WITHIN_DAYS.' days');
        $ruleStart = new \DateTimeImmutable(self::PAUSE_RULE_STARTED_AT);
        $cutoff = $monthAgo > $ruleStart ? $monthAgo : $ruleStart;

        return $at >= $cutoff;
    }

    public static function parseStamp(mixed $pausedAt): ?\DateTimeImmutable
    {
        if ($pausedAt instanceof \DateTimeImmutable) {
            return $pausedAt;
        }
        if ($pausedAt instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($pausedAt);
        }
        $raw = trim((string) $pausedAt);
        if ($raw === '' || $raw === '0') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return (new \DateTimeImmutable('@'.$ts))->setTimezone(new \DateTimeZone(date_default_timezone_get() ?: 'UTC'));
    }

    public static function fallbackPauseReason(): string
    {
        return 'Pause Rule (Dil% ≥ 100%)';
    }

    public static function normalizeCampaignName(string $name): string
    {
        $n = preg_replace('/\s+/u', ' ', strtoupper(trim(str_replace("\xC2\xA0", ' ', $name)))) ?? '';

        return rtrim($n, ". \t");
    }

    /**
     * @param  array{paused_reason?: mixed, reactivated_at?: mixed}|null  $state
     * @return array{label: string, reason: string, tip: string}
     */
    public static function activeAgainDisplay(?array $state): array
    {
        if (! is_array($state) || trim((string) ($state['reactivated_at'] ?? '')) === '') {
            return ['label' => '', 'reason' => '', 'tip' => ''];
        }
        $reason = trim((string) ($state['paused_reason'] ?? ''));
        if ($reason === '') {
            $reason = self::fallbackPauseReason();
        }
        $when = trim((string) ($state['reactivated_at'] ?? ''));
        $tip = $reason;
        if ($when !== '') {
            $tip .= ' — turned back on '.$when;
        }

        return ['label' => 'Active Again', 'reason' => $reason, 'tip' => $tip];
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

    /**
     * @param  mixed  $pr
     * @return array{enabled: bool, dil_above: float, dil_enabled: bool, price_below: float, price_enabled: bool, reviews_enabled: bool, reviews_below: float}
     */
    private static function normalizePr(mixed $pr): array
    {
        $base = self::defaultPr();
        if (! is_array($pr)) {
            return $base;
        }
        $dil = self::normalizePrNumber($pr['dil_above'] ?? $pr['dilAbove'] ?? $base['dil_above'], 'PR Dil%', 0, 100000);
        $price = self::normalizePrNumber($pr['price_below'] ?? $pr['priceBelow'] ?? $base['price_below'], 'PR Price', 0, 1000000);
        $reviewsBelow = self::normalizePrNumber($pr['reviews_below'] ?? $pr['reviewsBelow'] ?? $base['reviews_below'], 'PR Reviews', 1, 5);

        return [
            'enabled' => self::normalizePrBool($pr['enabled'] ?? false),
            'dil_above' => $dil,
            'dil_enabled' => self::normalizePrBool($pr['dil_enabled'] ?? $pr['dilEnabled'] ?? true),
            'price_below' => $price,
            'price_enabled' => false,
            'reviews_enabled' => false,
            'reviews_below' => $reviewsBelow,
        ];
    }

    private static function normalizePrBool(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    private static function normalizePrNumber(mixed $value, string $label, float $min, float $max): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new \InvalidArgumentException($label.' must be a number.');
        }
        $n = (float) $value;
        if ($n < $min || $n > $max) {
            throw new \InvalidArgumentException($label.' must be between '.$min.' and '.$max.'.');
        }

        return $n;
    }

    /**
     * Single "below ★" threshold. Also migrates leftover From/To bands (first Pause band's To).
     *
     * @param  mixed  $reviews
     * @return array{enabled: bool, below: float}
     */
    private static function normalizeReviews(mixed $reviews): array
    {
        $base = self::defaultReviews();
        if (! is_array($reviews)) {
            return $base;
        }
        if (array_key_exists('enabled', $reviews) || array_key_exists('below', $reviews)) {
            return [
                'enabled' => false,
                'below' => self::normalizePrNumber($reviews['below'] ?? $base['below'], 'Reviews below', 1, 5),
            ];
        }
        if ($reviews === [] || ! array_is_list($reviews)) {
            return $base;
        }
        $below = $base['below'];
        foreach ($reviews as $band) {
            if (! is_array($band)) {
                continue;
            }
            $action = strtoupper((string) ($band['action'] ?? self::ACTION_PAUSED));
            if ($action !== self::ACTION_PAUSED) {
                continue;
            }
            $to = (float) ($band['to'] ?? $below);
            if (is_finite($to) && $to >= 1 && $to <= 5) {
                $below = $to;
            }
            break;
        }

        return [
            'enabled' => false,
            'below' => $below,
        ];
    }
}
