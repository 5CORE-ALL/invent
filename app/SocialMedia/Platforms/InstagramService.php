<?php

namespace App\SocialMedia\Platforms;

use App\Models\SocialMediaAccount;
use App\SocialMedia\Exceptions\SocialMediaApiException;
use App\SocialMedia\Exceptions\SocialMediaAuthException;
use App\SocialMedia\Exceptions\SocialMediaRateLimitException;
use Carbon\CarbonInterface;

class InstagramService extends AbstractPlatform
{
    public function platform(): string
    {
        return 'instagram';
    }

    public function fetch(SocialMediaAccount $account, CarbonInterface $since): array
    {
        $token = $this->token($account);
        $id = rawurlencode($account->external_account_id);
        $profile = $this->officialGet($this->graph($id), $token, [
            'fields' => 'username,name,followers_count,follows_count',
        ]);

        $byDate = [];
        foreach ($this->userReach($id, $token, $since) as $date => $value) {
            $byDate[$date]['reach'] = $value;
        }
        $today = now()->toDateString();
        if (array_key_exists('followers_count', $profile)) {
            $byDate[$today]['followers'] = $profile['followers_count'];
        }
        if (array_key_exists('follows_count', $profile)) {
            $byDate[$today]['following'] = $profile['follows_count'];
        }

        $days = [];
        foreach ($byDate as $date => $values) {
            $days[] = ['date' => $date, 'metrics' => $this->mapMetrics($values), 'raw' => $values];
        }

        $posts = [];
        $pages = $this->collectPages($this->graph($id.'/media'), $token, [
            'fields' => 'id,caption,media_type,permalink,timestamp,like_count,comments_count,media_url',
            'since' => $since->getTimestamp(),
            'limit' => 50,
        ], (int) config('social_media.max_post_pages', 5));
        foreach ($pages as $page) {
            foreach ($page['data'] ?? [] as $media) {
                if (empty($media['id'])) {
                    continue;
                }
                $type = strtoupper((string) ($media['media_type'] ?? ''));
                $content = match ($type) {
                    'REELS', 'VIDEO' => 'video',
                    default => 'post',
                };
                $insights = $this->mediaInsights((string) $media['id'], $token, $content === 'video');
                $posts[] = [
                    'external_post_id' => (string) $media['id'],
                    'content_type' => $content,
                    'title' => $media['caption'] ?? null,
                    'published_at' => $media['timestamp'] ?? null,
                    'permalink' => $media['permalink'] ?? null,
                    'media_url' => $media['media_url'] ?? null,
                    'metrics' => $this->mapMetrics([
                        'likes' => $media['like_count'] ?? null,
                        'comments' => $media['comments_count'] ?? null,
                        'shares' => $insights['shares'] ?? null,
                        'saves' => $insights['saved'] ?? null,
                        'reach' => $insights['reach'] ?? null,
                        'video_views' => $content === 'video' ? ($insights['views'] ?? null) : null,
                        'watch_time' => $insights['ig_reels_avg_watch_time'] ?? null,
                    ]),
                    'raw' => ['media_type' => $media['media_type'] ?? null],
                ];
            }
        }

        return ['profile' => $profile, 'days' => $days, 'posts' => $posts];
    }

    /**
     * @return array<string, mixed>
     */
    private function userReach(string $id, string $token, CarbonInterface $since): array
    {
        try {
            $payload = $this->officialGet($this->graph($id.'/insights'), $token, [
                'metric' => 'reach',
                'period' => 'day',
                'since' => $this->since($since),
                'until' => now()->toDateString(),
                'metric_type' => 'time_series',
            ]);
        } catch (SocialMediaAuthException|SocialMediaRateLimitException $e) {
            throw $e;
        } catch (SocialMediaApiException) {
            return [];
        }

        $series = [];
        foreach ($payload['data'] ?? [] as $row) {
            foreach ($row['values'] ?? [] as $point) {
                if (! empty($point['end_time']) && array_key_exists('value', $point)) {
                    $series[$this->insightDate($point['end_time'])] = $point['value'];
                }
            }
        }

        return $series;
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaInsights(string $mediaId, string $token, bool $video): array
    {
        $metric = $video ? 'reach,saved,shares,views,ig_reels_avg_watch_time' : 'reach,saved,shares';
        try {
            $payload = $this->officialGet($this->graph(rawurlencode($mediaId).'/insights'), $token, [
                'metric' => $metric,
            ]);
        } catch (SocialMediaAuthException|SocialMediaRateLimitException $e) {
            throw $e;
        } catch (SocialMediaApiException) {
            if (! $video) {
                return [];
            }
            try {
                $payload = $this->officialGet($this->graph(rawurlencode($mediaId).'/insights'), $token, [
                    'metric' => 'reach,saved,shares,views',
                ]);
            } catch (SocialMediaAuthException|SocialMediaRateLimitException $e) {
                throw $e;
            } catch (SocialMediaApiException) {
                return [];
            }
        }

        $values = [];
        foreach ($payload['data'] ?? [] as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $values[$name] = $row['values'][0]['value'] ?? ($row['total_value']['value'] ?? null);
        }

        return $values;
    }
}
