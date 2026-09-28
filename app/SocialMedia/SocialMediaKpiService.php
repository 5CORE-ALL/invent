<?php

namespace App\SocialMedia;

/**
 * Flow metrics (impressions, reach, engagement, views) are summed.
 * Stock metrics (followers, following) use the first and last available day.
 * Rates are recomputed from summed numerators and denominators.
 * A missing API value stays unavailable. It is never treated as zero.
 */
class SocialMediaKpiService
{
    public const FLOW = [
        'impressions',
        'reach',
        'profile_views',
        'website_clicks',
        'likes',
        'comments',
        'shares',
        'saves',
        'clicks',
        'video_views',
        'watch_time',
        'engagement',
    ];

    public const STOCK = ['followers', 'following'];

    public function engagement(?float $likes, ?float $comments, ?float $shares, ?float $saves): ?float
    {
        $sum = 0.0;
        $any = false;
        foreach ([$likes, $comments, $shares, $saves] as $part) {
            if ($part === null) {
                continue;
            }
            $any = true;
            $sum += $part;
        }

        return $any ? $sum : null;
    }

    /**
     * engagement / impressions when impressions is available and positive,
     * otherwise engagement / reach, otherwise not available.
     *
     * @return array{value: float|null, availability: string, basis: string|null}
     */
    public function engagementRate(?float $engagement, ?float $impressions, ?float $reach): array
    {
        if ($engagement === null) {
            return $this->rateMissing();
        }
        if ($impressions !== null && $impressions > 0) {
            return $this->rate($engagement / $impressions, 'impressions');
        }
        if ($reach !== null && $reach > 0) {
            return $this->rate($engagement / $reach, 'reach');
        }

        return $this->rateMissing();
    }

    /**
     * @param  list<array{value: float|null, availability: string}>  $points
     */
    public function sumAvailable(array $points): ?float
    {
        $sum = 0.0;
        $any = false;
        foreach ($points as $point) {
            if (($point['availability'] ?? '') !== SocialMetric::AVAILABLE || $point['value'] === null) {
                continue;
            }
            $any = true;
            $sum += (float) $point['value'];
        }

        return $any ? $sum : null;
    }

    /**
     * @param  list<array{date: string, value: float|null, availability: string}>  $points
     * @return array{first: float|null, last: float|null, change: float|null, percent: float|null, availability: string}
     */
    public function followerGrowth(array $points): array
    {
        $available = array_values(array_filter($points, function (array $point): bool {
            return ($point['availability'] ?? '') === SocialMetric::AVAILABLE && $point['value'] !== null;
        }));
        usort($available, fn (array $a, array $b) => strcmp($a['date'], $b['date']));
        if (count($available) < 2) {
            return [
                'first' => $available[0]['value'] ?? null,
                'last' => $available[0]['value'] ?? null,
                'change' => null,
                'percent' => null,
                'availability' => SocialMetric::NOT_AVAILABLE,
            ];
        }
        $first = (float) $available[0]['value'];
        $last = (float) $available[array_key_last($available)]['value'];
        $change = $last - $first;

        return [
            'first' => $first,
            'last' => $last,
            'change' => $change,
            'percent' => $first > 0 ? ($change / $first) * 100 : null,
            'availability' => SocialMetric::AVAILABLE,
        ];
    }

    /**
     * @return array{target: float|null, actual: float|null, remaining: float|null, percent: float|null, status: string}
     */
    public function achievement(?float $actual, ?float $target, bool $actualAvailable): array
    {
        if (! $actualAvailable || $actual === null || $target === null) {
            return [
                'target' => $target,
                'actual' => $actualAvailable ? $actual : null,
                'remaining' => null,
                'percent' => null,
                'status' => 'Not Available',
            ];
        }
        $percent = $target > 0 ? ($actual / $target) * 100 : null;

        return [
            'target' => $target,
            'actual' => $actual,
            'remaining' => $target - $actual,
            'percent' => $percent,
            'status' => $this->status($percent),
        ];
    }

    public function status(?float $percent): string
    {
        if ($percent === null) {
            return 'Not Available';
        }
        if ($percent >= 90) {
            return 'On Track';
        }
        if ($percent >= 50) {
            return 'In Progress';
        }

        return 'Needs Attention';
    }

    /**
     * @return array{value: float|null, availability: string}
     */
    public function changePercent(?float $current, ?float $previous, bool $currentAvailable, bool $previousAvailable): array
    {
        if (! $currentAvailable || ! $previousAvailable || $current === null || $previous === null || (float) $previous === 0.0) {
            return ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];
        }

        return [
            'value' => (($current - $previous) / abs((float) $previous)) * 100,
            'availability' => SocialMetric::AVAILABLE,
        ];
    }

    /**
     * @param  list<array{platform?: string, account_id?: int, date: string, metric: string, value: float|null, availability: string, content_type?: string|null}>  $rows
     * @param  array{platform?: string|null, account_id?: int|null, content_type?: string|null, start?: string|null, end?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function filterRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters): bool {
            if (! empty($filters['platform']) && ($row['platform'] ?? null) !== $filters['platform']) {
                return false;
            }
            if (! empty($filters['account_id']) && (int) ($row['account_id'] ?? 0) !== (int) $filters['account_id']) {
                return false;
            }
            if (! empty($filters['content_type']) && ($row['content_type'] ?? null) !== $filters['content_type']) {
                return false;
            }
            if (! empty($filters['start']) && ($row['date'] ?? '') < $filters['start']) {
                return false;
            }
            if (! empty($filters['end']) && ($row['date'] ?? '') > $filters['end']) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array{value: float|null, availability: string, basis: string|null}
     */
    private function rate(float $value, string $basis): array
    {
        return [
            'value' => $value,
            'availability' => SocialMetric::AVAILABLE,
            'basis' => $basis,
        ];
    }

    /**
     * @return array{value: float|null, availability: string, basis: string|null}
     */
    private function rateMissing(): array
    {
        return [
            'value' => null,
            'availability' => SocialMetric::NOT_AVAILABLE,
            'basis' => null,
        ];
    }
}
