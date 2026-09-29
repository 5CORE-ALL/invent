<?php

namespace App\SocialMedia\Platforms;

use App\Models\SocialMediaAccount;
use App\SocialMedia\Exceptions\SocialMediaApiException;
use Carbon\CarbonInterface;

class TikTokService extends AbstractPlatform
{
    public function platform(): string
    {
        return 'tiktok';
    }

    public function fetch(SocialMediaAccount $account, CarbonInterface $since): array
    {
        $token = $this->token($account);
        $user = $this->tiktok($this->officialGet('https://open.tiktokapis.com/v2/user/info/', $token, [
            'fields' => 'open_id,display_name,username,follower_count,following_count,profile_deep_link',
        ]));
        $info = $user['data']['user'] ?? [];
        $videos = $this->tiktok($this->officialPost(
            'https://open.tiktokapis.com/v2/video/list/',
            $token,
            ['max_count' => 20],
            ['fields' => 'id,title,create_time,share_url,cover_image_url,view_count,like_count,comment_count,share_count']
        ));

        $today = now()->toDateString();
        $days = [[
            'date' => $today,
            'metrics' => $this->mapMetrics([
                'followers' => $info['follower_count'] ?? null,
                'following' => $info['following_count'] ?? null,
            ]),
            'raw' => [
                'follower_count' => $info['follower_count'] ?? null,
                'following_count' => $info['following_count'] ?? null,
            ],
        ]];

        $posts = [];
        foreach ($videos['data']['videos'] ?? [] as $video) {
            if (empty($video['id'])) {
                continue;
            }
            $published = isset($video['create_time']) ? now()->createFromTimestamp((int) $video['create_time']) : null;
            if ($published && $published->lt($since)) {
                continue;
            }
            $posts[] = [
                'external_post_id' => (string) $video['id'],
                'content_type' => 'video',
                'title' => $video['title'] ?? null,
                'published_at' => $published?->toIso8601String(),
                'permalink' => $video['share_url'] ?? null,
                'media_url' => $video['cover_image_url'] ?? null,
                'metrics' => $this->mapMetrics([
                    'video_views' => $video['view_count'] ?? null,
                    'likes' => $video['like_count'] ?? null,
                    'comments' => $video['comment_count'] ?? null,
                    'shares' => $video['share_count'] ?? null,
                ]),
                'raw' => [],
            ];
        }

        return ['profile' => $info, 'days' => $days, 'posts' => $posts];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function tiktok(array $payload): array
    {
        $code = (string) ($payload['error']['code'] ?? 'ok');
        if ($code !== 'ok' && $code !== '') {
            throw new SocialMediaApiException((string) ($payload['error']['message'] ?? 'TikTok returned an error.'));
        }

        return $payload;
    }
}
