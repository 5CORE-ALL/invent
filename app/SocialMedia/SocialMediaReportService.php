<?php

namespace App\SocialMedia;

use App\Models\SocialMediaAccount;
use App\Models\SocialMediaKpiTarget;
use App\Models\SocialMediaNormalizedMetric;
use App\Models\SocialMediaPost;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SocialMediaReportService
{
    public function __construct(private readonly SocialMediaKpiService $kpi)
    {
    }

    /**
     * @param  array{platform?: string|null, account_id?: int|null, user_id?: int|null, content_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function dashboard(Carbon $start, Carbon $end, array $filters): array
    {
        $key = 'social-media.dashboard.'.md5(json_encode([$start->toDateString(), $end->toDateString(), $filters]));

        return Cache::remember($key, 300, fn () => $this->build($start, $end, $filters));
    }

    /**
     * @param  array{platform?: string|null, account_id?: int|null, user_id?: int|null, content_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(Carbon $start, Carbon $end, array $filters): array
    {
        $accounts = $this->accounts($filters);
        $accountIds = $accounts->pluck('id');
        $contentType = $filters['content_type'] ?? null;
        $rows = $this->rows($accountIds, $start, $end, $contentType);
        $cards = $this->cards($accounts, $rows, $start, $end, $contentType);
        $targets = $this->targets($start, $end, $filters);
        $progress = [];
        foreach ($cards as $name => $card) {
            $target = $targets[$name] ?? null;
            $actualAvailable = ($card['availability'] ?? '') === SocialMetric::AVAILABLE;
            $progress[$name] = $this->kpi->achievement(
                $actualAvailable ? $card['value'] : null,
                $target,
                $actualAvailable
            );
            $progress[$name]['change'] = $card['previous_period'] ?? ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];
        }

        return [
            'accounts' => $accounts,
            'cards' => $cards,
            'progress' => $progress,
            'charts' => $this->charts($accounts, $rows, $start, $end, $contentType),
            'platforms' => $this->platformComparison($accounts, $rows, $start, $end, $contentType),
            'notes' => $this->notes($accounts),
        ];
    }

    /**
     * @param  array{platform?: string|null, account_id?: int|null, user_id?: int|null}  $filters
     */
    public function accounts(array $filters): Collection
    {
        return SocialMediaAccount::query()
            ->when(! empty($filters['platform']), fn ($q) => $q->where('platform', $filters['platform']))
            ->when(! empty($filters['account_id']), fn ($q) => $q->whereKey($filters['account_id']))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->orderBy('platform')
            ->orderBy('account_name')
            ->get();
    }

    private function rows(Collection $accountIds, Carbon $start, Carbon $end, ?string $contentType): Collection
    {
        if ($accountIds->isEmpty()) {
            return collect();
        }

        $query = SocialMediaNormalizedMetric::query()
            ->whereIn('social_media_account_id', $accountIds)
            ->whereDate('metric_date', '>=', $start->toDateString())
            ->whereDate('metric_date', '<=', $end->toDateString());

        if ($contentType) {
            $postIds = SocialMediaPost::query()
                ->whereIn('social_media_account_id', $accountIds)
                ->where('content_type', $contentType)
                ->pluck('id');
            $query->whereIn('social_media_post_id', $postIds);
        }

        return $query->get(['social_media_account_id', 'social_media_post_id', 'metric_date', 'metric_name', 'metric_value', 'availability']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cards(Collection $accounts, Collection $rows, Carbon $start, Carbon $end, ?string $contentType): array
    {
        $posts = $this->postCount($accounts->pluck('id'), $start, $end, $contentType);
        $flow = function (string $metric) use ($rows, $contentType): array {
            $scoped = $contentType
                ? $rows
                : $rows->where('social_media_post_id', 0);
            $points = $scoped->where('metric_name', $metric)->map(fn ($row) => [
                'value' => $row->metric_value,
                'availability' => $row->availability,
            ])->all();
            $sum = $this->kpi->sumAvailable($points);

            return $sum === null
                ? ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE]
                : ['value' => $sum, 'availability' => SocialMetric::AVAILABLE];
        };

        $engagement = $this->engagementCard($rows, $contentType);
        $impressions = $flow('impressions');
        $reach = $flow('reach');
        $rate = $this->kpi->engagementRate(
            $engagement['availability'] === SocialMetric::AVAILABLE ? $engagement['value'] : null,
            $impressions['availability'] === SocialMetric::AVAILABLE ? $impressions['value'] : null,
            $reach['availability'] === SocialMetric::AVAILABLE ? $reach['value'] : null,
        );

        $previousStart = $start->copy()->subDays($start->diffInDays($end) + 1);
        $previousEnd = $start->copy()->subDay();
        $previousRows = $this->rows($accounts->pluck('id'), $previousStart, $previousEnd, $contentType);
        $previousEngagement = $this->engagementCard($previousRows, $contentType);

        $cards = [
            'posts' => ['value' => (float) $posts, 'availability' => SocialMetric::AVAILABLE, 'label' => 'Posts Published'],
            'reach' => array_merge($reach, ['label' => 'Total Reach']),
            'impressions' => array_merge($impressions, ['label' => 'Impressions']),
            'engagement' => array_merge($engagement, ['label' => 'Engagement']),
            'engagement_rate' => ['value' => $rate['value'], 'availability' => $rate['availability'], 'label' => 'Engagement Rate', 'basis' => $rate['basis']],
            'followers' => array_merge($this->followers($accounts, $rows), ['label' => 'Followers']),
            'follower_growth' => array_merge($this->growth($accounts, $rows), ['label' => 'Follower Growth']),
            'video_views' => array_merge($this->videoViews($rows, $contentType, $flow('video_views')), ['label' => 'Video Views']),
            'website_clicks' => array_merge($flow('website_clicks'), ['label' => 'Website Clicks']),
            'leads' => ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE, 'label' => 'Leads'],
        ];

        $cards['engagement']['previous_period'] = $this->kpi->changePercent(
            $engagement['value'],
            $previousEngagement['value'],
            $engagement['availability'] === SocialMetric::AVAILABLE,
            $previousEngagement['availability'] === SocialMetric::AVAILABLE,
        );
        foreach (['reach', 'impressions', 'video_views', 'website_clicks'] as $metric) {
            $previous = $this->sumNamed($previousRows, $metric, $contentType);
            $cards[$metric]['previous_period'] = $this->kpi->changePercent(
                $cards[$metric]['value'],
                $previous,
                $cards[$metric]['availability'] === SocialMetric::AVAILABLE,
                $previous !== null,
            );
        }
        $cards['posts']['previous_period'] = $this->kpi->changePercent(
            (float) $posts,
            (float) $this->postCount($accounts->pluck('id'), $previousStart, $previousEnd, $contentType),
            true,
            true,
        );
        $cards['followers']['previous_period'] = ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];
        $cards['follower_growth']['previous_period'] = ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];
        $cards['engagement_rate']['previous_period'] = ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];
        $cards['leads']['previous_period'] = ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];

        $cards['previous_month'] = $this->windowAvailable($accounts, $start->copy()->subMonth(), $end->copy()->subMonth(), $contentType);
        $cards['previous_year'] = $this->windowAvailable($accounts, $start->copy()->subYear(), $end->copy()->subYear(), $contentType);

        return $cards;
    }

    /**
     * @param  array{value: float|null, availability: string}  $accountLevel
     * @return array{value: float|null, availability: string}
     */
    private function videoViews(Collection $rows, ?string $contentType, array $accountLevel): array
    {
        if (! $contentType && $accountLevel['availability'] === SocialMetric::AVAILABLE) {
            return $accountLevel;
        }
        $posts = $this->latestPostSum($rows, 'video_views');
        if ($posts === null) {
            return ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];
        }

        return ['value' => $posts, 'availability' => SocialMetric::AVAILABLE];
    }

    private function engagementCard(Collection $rows, ?string $contentType): array
    {
        $parts = [];
        foreach (['likes', 'comments', 'shares', 'saves'] as $name) {
            $parts[$name] = $this->latestPostSum($rows, $name);
            if ($parts[$name] === null && ! $contentType) {
                $parts[$name] = $this->sumNamed($rows, $name, null);
            }
        }
        if (! array_filter($parts, fn ($value) => $value !== null)) {
            return ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];
        }

        return [
            'value' => $this->kpi->engagement($parts['likes'], $parts['comments'], $parts['shares'], $parts['saves']),
            'availability' => SocialMetric::AVAILABLE,
        ];
    }

    private function latestPostSum(Collection $rows, string $metric): ?float
    {
        $latest = [];
        foreach ($rows as $row) {
            if ((int) $row->social_media_post_id === 0 || $row->metric_name !== $metric) {
                continue;
            }
            $date = $row->metric_date->toDateString();
            $id = (int) $row->social_media_post_id;
            if (! isset($latest[$id]) || $date > $latest[$id]['date']) {
                $latest[$id] = [
                    'date' => $date,
                    'value' => $row->metric_value,
                    'availability' => $row->availability,
                ];
            }
        }

        return $this->kpi->sumAvailable(array_values($latest));
    }

    private function sumNamed(Collection $rows, string $metric, ?string $contentType): ?float
    {
        $scoped = $contentType ? $rows : $rows->where('social_media_post_id', 0);

        return $this->kpi->sumAvailable($scoped->where('metric_name', $metric)->map(fn ($row) => [
            'value' => $row->metric_value,
            'availability' => $row->availability,
        ])->all());
    }

    /**
     * @return array{value: float|null, availability: string}
     */
    private function followers(Collection $accounts, Collection $rows): array
    {
        if ($accounts->isEmpty()) {
            return ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];
        }
        $total = 0.0;
        foreach ($accounts as $account) {
            $points = $rows->where('social_media_account_id', $account->id)
                ->where('social_media_post_id', 0)
                ->where('metric_name', 'followers')
                ->map(fn ($row) => [
                    'date' => $row->metric_date->toDateString(),
                    'value' => $row->metric_value,
                    'availability' => $row->availability,
                ])->all();
            $growth = $this->kpi->followerGrowth($points);
            $latest = $growth['last'];
            if ($latest === null) {
                return ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE];
            }
            $total += $latest;
        }

        return ['value' => $total, 'availability' => SocialMetric::AVAILABLE];
    }

    /**
     * @return array{value: float|null, availability: string, percent: float|null}
     */
    private function growth(Collection $accounts, Collection $rows): array
    {
        if ($accounts->isEmpty()) {
            return ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE, 'percent' => null];
        }
        $change = 0.0;
        foreach ($accounts as $account) {
            $points = $rows->where('social_media_account_id', $account->id)
                ->where('social_media_post_id', 0)
                ->where('metric_name', 'followers')
                ->map(fn ($row) => [
                    'date' => $row->metric_date->toDateString(),
                    'value' => $row->metric_value,
                    'availability' => $row->availability,
                ])->all();
            $growth = $this->kpi->followerGrowth($points);
            if ($growth['availability'] !== SocialMetric::AVAILABLE) {
                return ['value' => null, 'availability' => SocialMetric::NOT_AVAILABLE, 'percent' => null];
            }
            $change += (float) $growth['change'];
        }

        return ['value' => $change, 'availability' => SocialMetric::AVAILABLE, 'percent' => null];
    }

    private function postCount(Collection $accountIds, Carbon $start, Carbon $end, ?string $contentType): int
    {
        if ($accountIds->isEmpty()) {
            return 0;
        }

        return SocialMediaPost::query()
            ->whereIn('social_media_account_id', $accountIds)
            ->whereBetween('published_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($contentType, fn ($q) => $q->where('content_type', $contentType))
            ->count();
    }

    /**
     * @return array{available: bool}
     */
    private function windowAvailable(Collection $accounts, Carbon $start, Carbon $end, ?string $contentType): array
    {
        $rows = $this->rows($accounts->pluck('id'), $start, $end, $contentType);

        return [
            'available' => $rows->contains(fn ($row) => $row->availability === SocialMetric::AVAILABLE && $row->metric_value !== null),
        ];
    }

    /**
     * @return array<string, list<array{date: string, value: float|null}>>
     */
    private function charts(Collection $accounts, Collection $rows, Carbon $start, Carbon $end, ?string $contentType): array
    {
        $accountIds = $accounts->pluck('id');
        $reach = $this->series($rows, 'reach', $contentType);
        $engagementRows = $contentType ? $rows : $rows->where('social_media_post_id', 0);
        $engagement = [];
        foreach ($engagementRows->groupBy(fn ($row) => $row->metric_date->toDateString()) as $date => $dayRows) {
            $value = $this->kpi->engagement(
                $this->kpi->sumAvailable($this->points($dayRows, 'likes')),
                $this->kpi->sumAvailable($this->points($dayRows, 'comments')),
                $this->kpi->sumAvailable($this->points($dayRows, 'shares')),
                $this->kpi->sumAvailable($this->points($dayRows, 'saves')),
            );
            if ($value !== null) {
                $engagement[] = ['date' => $date, 'value' => $value];
            }
        }
        $followers = [];
        $dates = $rows->where('metric_name', 'followers')->where('social_media_post_id', 0)
            ->map(fn ($row) => $row->metric_date->toDateString())->unique()->sort()->values();
        foreach ($dates as $date) {
            $total = 0.0;
            $complete = $accounts->isNotEmpty();
            foreach ($accounts as $account) {
                $point = $rows->first(fn ($row) => $row->social_media_account_id === $account->id
                    && (int) $row->social_media_post_id === 0
                    && $row->metric_name === 'followers'
                    && $row->metric_date->toDateString() === $date
                    && $row->availability === SocialMetric::AVAILABLE);
                if (! $point) {
                    $complete = false;
                    break;
                }
                $total += (float) $point->metric_value;
            }
            if ($complete) {
                $followers[] = ['date' => $date, 'value' => $total];
            }
        }

        $published = SocialMediaPost::query()
            ->whereIn('social_media_account_id', $accountIds->isEmpty() ? [0] : $accountIds)
            ->whereBetween('published_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->when($contentType, fn ($q) => $q->where('content_type', $contentType))
            ->get(['published_at'])
            ->groupBy(fn ($post) => optional($post->published_at)->toDateString())
            ->map(fn ($group, $date) => ['date' => (string) $date, 'value' => $group->count()])
            ->filter(fn ($point) => $point['date'] !== '')
            ->values()
            ->all();

        return [
            'reach' => $reach,
            'engagement' => $engagement,
            'followers' => $followers,
            'posts' => array_values($published),
        ];
    }

    /**
     * @return list<array{date: string, value: float}>
     */
    private function series(Collection $rows, string $metric, ?string $contentType): array
    {
        $scoped = $contentType ? $rows : $rows->where('social_media_post_id', 0);

        return $scoped->where('metric_name', $metric)
            ->where('availability', SocialMetric::AVAILABLE)
            ->groupBy(fn ($row) => $row->metric_date->toDateString())
            ->map(function (Collection $day, string $date) {
                $sum = $day->sum(fn ($row) => (float) $row->metric_value);

                return ['date' => $date, 'value' => $sum];
            })
            ->sortBy('date')
            ->values()
            ->all();
    }

    private function points(Collection $rows, string $metric): array
    {
        return $rows->where('metric_name', $metric)->map(fn ($row) => [
            'value' => $row->metric_value,
            'availability' => $row->availability,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function platformComparison(Collection $accounts, Collection $rows, Carbon $start, Carbon $end, ?string $contentType): array
    {
        $out = [];
        foreach ($accounts->groupBy('platform') as $platform => $group) {
            $ids = $group->pluck('id');
            $scoped = $rows->whereIn('social_media_account_id', $ids);
            $out[] = [
                'platform' => $platform,
                'reach' => $this->sumNamed($scoped, 'reach', $contentType),
                'engagement' => $this->engagementCard($scoped, $contentType)['value'],
                'followers' => $this->followers($group, $scoped)['value'],
                'posts' => $this->postCount($ids, $start, $end, $contentType),
            ];
        }

        return $out;
    }

    /**
     * @param  array{platform?: string|null, account_id?: int|null, user_id?: int|null}  $filters
     * @return array<string, float>
     */
    private function targets(Carbon $start, Carbon $end, array $filters): array
    {
        $rows = SocialMediaKpiTarget::query()
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->when(! empty($filters['user_id']), fn ($q) => $q->where(function ($inner) use ($filters) {
                $inner->whereNull('user_id')->orWhere('user_id', $filters['user_id']);
            }))
            ->when(! empty($filters['platform']), fn ($q) => $q->where(function ($inner) use ($filters) {
                $inner->whereNull('platform')->orWhere('platform', $filters['platform']);
            }))
            ->orderByDesc('id')
            ->get();

        $picked = [];
        foreach ($rows as $row) {
            $score = ($row->platform ? 2 : 0) + ($row->user_id ? 1 : 0);
            if (! isset($picked[$row->metric_name]) || $score >= $picked[$row->metric_name]['score']) {
                $picked[$row->metric_name] = ['score' => $score, 'value' => (float) $row->target_value];
            }
        }

        return array_map(fn ($item) => $item['value'], $picked);
    }

    /**
     * @return list<string>
     */
    private function notes(Collection $accounts): array
    {
        $notes = [
            'Engagement is the sum of available likes, comments, shares, and saves. A metric the API does not return is left out of that sum.',
            'Engagement rate is engagement divided by impressions when impressions exist, otherwise by reach. Percentages are not averaged together.',
            'Follower growth needs a follower count on at least two dates. One snapshot is not shown as zero growth.',
            'Leads are not available because no social platform lead source is connected.',
        ];
        foreach ($accounts->pluck('platform')->unique() as $platform) {
            $missing = array_keys(array_filter(
                config('social_media.metrics.'.$platform, []),
                fn ($state) => $state !== SocialMetric::AVAILABLE
            ));
            if ($missing !== []) {
                $notes[] = ucfirst((string) $platform).' does not provide: '.implode(', ', $missing).'.';
            }
        }

        return $notes;
    }
}
