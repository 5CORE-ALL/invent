<?php

namespace App\SocialMedia\Platforms;

use App\Models\SocialMediaAccount;
use Carbon\CarbonInterface;

class YouTubeService extends AbstractPlatform
{
    public function platform(): string
    {
        return 'youtube';
    }

    public function fetch(SocialMediaAccount $account, CarbonInterface $since): array
    {
        $token = $this->token($account);
        $channel = $this->officialGet('https://www.googleapis.com/youtube/v3/channels', $token, [
            'part' => 'snippet,statistics',
            'id' => $account->external_account_id,
        ]);
        $item = $channel['items'][0] ?? [];
        $stats = $item['statistics'] ?? [];
        $analytics = $this->officialGet('https://youtubeanalytics.googleapis.com/v2/reports', $token, [
            'ids' => 'channel=='.$account->external_account_id,
            'startDate' => $this->since($since),
            'endDate' => now()->toDateString(),
            'metrics' => 'views,likes,comments,shares,estimatedMinutesWatched,averageViewPercentage',
            'dimensions' => 'day',
        ]);

        $headers = array_column($analytics['columnHeaders'] ?? [], 'name');
        $byDate = [];
        foreach ($analytics['rows'] ?? [] as $row) {
            $mapped = array_combine($headers, $row) ?: [];
            $date = (string) ($mapped['day'] ?? '');
            if ($date === '') {
                continue;
            }
            $byDate[$date] = [
                'video_views' => $mapped['views'] ?? null,
                'likes' => $mapped['likes'] ?? null,
                'comments' => $mapped['comments'] ?? null,
                'shares' => $mapped['shares'] ?? null,
                'watch_time' => $mapped['estimatedMinutesWatched'] ?? null,
                'completion' => $mapped['averageViewPercentage'] ?? null,
                'raw' => $mapped,
            ];
        }
        if (array_key_exists('subscriberCount', $stats)) {
            $today = now()->toDateString();
            $byDate[$today]['followers'] = $stats['subscriberCount'];
        }

        $days = [];
        foreach ($byDate as $date => $values) {
            $raw = $values['raw'] ?? [];
            unset($values['raw']);
            $days[] = ['date' => $date, 'metrics' => $this->mapMetrics($values), 'raw' => $raw];
        }

        $videos = $this->officialGet('https://www.googleapis.com/youtube/v3/search', $token, [
            'part' => 'snippet',
            'channelId' => $account->external_account_id,
            'type' => 'video',
            'order' => 'date',
            'publishedAfter' => $since->toIso8601String(),
            'maxResults' => 50,
        ]);
        $posts = [];
        foreach ($videos['items'] ?? [] as $video) {
            $videoId = $video['id']['videoId'] ?? null;
            if (! $videoId) {
                continue;
            }
            $posts[] = [
                'external_post_id' => (string) $videoId,
                'content_type' => 'video',
                'title' => $video['snippet']['title'] ?? null,
                'published_at' => $video['snippet']['publishedAt'] ?? null,
                'permalink' => 'https://www.youtube.com/watch?v='.$videoId,
                'media_url' => $video['snippet']['thumbnails']['medium']['url'] ?? null,
                'metrics' => $this->mapMetrics([]),
                'raw' => [],
            ];
        }

        return [
            'profile' => [
                'name' => $item['snippet']['title'] ?? $account->account_name,
                'followers_count' => $stats['subscriberCount'] ?? null,
            ],
            'days' => $days,
            'posts' => $posts,
        ];
    }
}
