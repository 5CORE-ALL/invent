<?php

namespace App\SocialMedia\Platforms;

use App\Models\SocialMediaAccount;
use App\SocialMedia\Exceptions\SocialMediaApiException;
use App\SocialMedia\Exceptions\SocialMediaAuthException;
use App\SocialMedia\Exceptions\SocialMediaRateLimitException;
use Carbon\CarbonInterface;

class FacebookService extends AbstractPlatform
{
    public function platform(): string
    {
        return 'facebook';
    }

    public function fetch(SocialMediaAccount $account, CarbonInterface $since): array
    {
        $token = $this->token($account);
        $id = rawurlencode($account->external_account_id);
        $profile = $this->officialGet($this->graph($id), $token, [
            'fields' => 'name,username,link,followers_count',
        ]);

        $byDate = [];
        foreach ([
            'page_total_media_view_unique' => 'reach',
            'page_video_views' => 'video_views',
        ] as $apiMetric => $internal) {
            foreach ($this->insightSeries($id, $token, $apiMetric, $since) as $date => $point) {
                $byDate[$date][$internal] = $point['value'];
                $byDate[$date]['raw'][$apiMetric] = $point['value'];
            }
        }
        if (array_key_exists('followers_count', $profile)) {
            $byDate[now()->toDateString()]['followers'] = $profile['followers_count'];
        }

        $days = [];
        foreach ($byDate as $date => $values) {
            $raw = $values['raw'] ?? [];
            unset($values['raw']);
            $days[] = ['date' => $date, 'metrics' => $this->mapMetrics($values), 'raw' => $raw];
        }

        $posts = [];
        $pages = $this->collectPages($this->graph($id.'/posts'), $token, [
            'fields' => 'id,message,created_time,permalink_url,status_type,shares,comments.summary(true),reactions.summary(true)',
            'since' => $since->getTimestamp(),
            'limit' => 50,
        ], (int) config('social_media.max_post_pages', 5));
        foreach ($pages as $page) {
            foreach ($page['data'] ?? [] as $post) {
                if (empty($post['id'])) {
                    continue;
                }
                $posts[] = [
                    'external_post_id' => (string) $post['id'],
                    'content_type' => str_contains(strtolower((string) ($post['status_type'] ?? '')), 'video') ? 'video' : 'post',
                    'title' => $post['message'] ?? null,
                    'published_at' => $post['created_time'] ?? null,
                    'permalink' => $post['permalink_url'] ?? null,
                    'media_url' => null,
                    'metrics' => $this->mapMetrics([
                        'likes' => $post['reactions']['summary']['total_count'] ?? null,
                        'comments' => $post['comments']['summary']['total_count'] ?? null,
                        'shares' => $post['shares']['count'] ?? null,
                    ]),
                    'raw' => ['status_type' => $post['status_type'] ?? null],
                ];
            }
        }

        return ['profile' => $profile, 'days' => $days, 'posts' => $posts];
    }

    /**
     * @return array<string, array{value: mixed}>
     */
    private function insightSeries(string $id, string $token, string $metric, CarbonInterface $since): array
    {
        try {
            $payload = $this->officialGet($this->graph($id.'/insights'), $token, [
                'metric' => $metric,
                'period' => 'day',
                'since' => $this->since($since),
                'until' => now()->toDateString(),
            ]);
        } catch (SocialMediaAuthException|SocialMediaRateLimitException $e) {
            throw $e;
        } catch (SocialMediaApiException $e) {
            if (str_contains(strtolower($e->getMessage()), 'valid insights metric')) {
                return [];
            }
            throw $e;
        }

        $series = [];
        foreach ($payload['data'] ?? [] as $row) {
            foreach ($row['values'] ?? [] as $point) {
                if (empty($point['end_time']) || ! array_key_exists('value', $point)) {
                    continue;
                }
                $series[$this->insightDate($point['end_time'])] = ['value' => $point['value']];
            }
        }

        return $series;
    }
}
