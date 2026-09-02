<?php

namespace App\Support;

use App\Models\AmazonAdsPauseRuleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Amazon Ads All pause/activate rule: dynamic Pricing / Dil% / ACOS% bands.
 *
 * Default desired state is ENABLED. A campaign is PAUSED only when it matches
 * at least one band whose action is PAUSED. First matching band per section wins.
 */
final class AmazonAdsPauseRule
{
    public const CACHE_KEY = 'amazon_ads_pause_rule_resolved_v9';

    public const ACTION_PAUSED = 'PAUSED';

    public const ACTION_ENABLED = 'ENABLED';

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
     * Pause product ads (not the campaign) when that ad's SKU rating is below this star value.
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
            'price_enabled' => true,
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

        return self::hasCampaignBands($r) || self::reviewsEnabled($r);
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
        $reviews = is_array($rule['reviews'] ?? null) ? $rule['reviews'] : [];

        return ! empty($reviews['enabled']);
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
        if (! self::reviewsEnabled($rule)) {
            return false;
        }
        if ($rating === null || $rating === '' || ! is_numeric($rating) || ! is_finite((float) $rating)) {
            return false;
        }

        return (float) $rating < self::reviewsBelow($rule);
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
        if (array_key_exists('reviews_enabled', $pr) || array_key_exists('reviews_below', $pr)) {
            $current['reviews'] = self::normalizeReviews([
                'enabled' => ! empty($normalizedPr['reviews_enabled']),
                'below' => $normalizedPr['reviews_below'],
            ]);
        }
        self::persistRule($current);
    }

    /**
     * @param  array{enabled?: mixed, below?: mixed}  $reviews
     */
    public static function persistReviews(array $reviews): void
    {
        $current = self::loadResolvedRule();
        $normalized = self::normalizeReviews($reviews);
        $current['reviews'] = $normalized;
        $pr = is_array($current['pr'] ?? null) ? $current['pr'] : self::defaultPr();
        $pr['reviews_enabled'] = ! empty($normalized['enabled']);
        $pr['reviews_below'] = $normalized['below'];
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

        $pr = is_array($r['pr'] ?? null) ? $r['pr'] : self::defaultPr();
        if (! empty($pr['enabled'])) {
            // Dil% and Price are independent: either matching condition pauses the campaign (OR).
            if (! empty($pr['dil_enabled'])) {
                $dilVal = $metrics['dil'] ?? null;
                $threshold = (float) ($pr['dil_above'] ?? 100);
                if ($dilVal !== null && $dilVal !== '' && is_finite((float) $dilVal) && is_finite($threshold)
                    && (float) $dilVal >= $threshold) {
                    $shown = rtrim(rtrim(number_format((float) $dilVal, 2, '.', ''), '0'), '.');
                    $th = rtrim(rtrim(number_format($threshold, 2, '.', ''), '0'), '.');
                    $hits[] = [
                        'action' => self::ACTION_PAUSED,
                        'reason' => 'PR Dil% '.$shown.'% ≥ '.$th.'%',
                    ];
                }
            }
            if (! empty($pr['price_enabled'])) {
                $priceVal = $metrics['price'] ?? null;
                $priceMax = (float) ($pr['price_below'] ?? 20);
                if ($priceVal !== null && $priceVal !== '' && is_finite((float) $priceVal) && is_finite($priceMax)
                    && (float) $priceVal < $priceMax) {
                    $shown = rtrim(rtrim(number_format((float) $priceVal, 2, '.', ''), '0'), '.');
                    $th = rtrim(rtrim(number_format($priceMax, 2, '.', ''), '0'), '.');
                    $hits[] = [
                        'action' => self::ACTION_PAUSED,
                        'reason' => 'PR Price $'.$shown.' < $'.$th,
                    ];
                }
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
            'price_enabled' => self::normalizePrBool($pr['price_enabled'] ?? $pr['priceEnabled'] ?? true),
            'reviews_enabled' => self::normalizePrBool($pr['reviews_enabled'] ?? $pr['reviewsEnabled'] ?? false),
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
                'enabled' => self::normalizePrBool($reviews['enabled'] ?? false),
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
            'enabled' => true,
            'below' => $below,
        ];
    }
}
