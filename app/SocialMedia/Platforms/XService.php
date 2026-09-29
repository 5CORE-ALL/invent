<?php

namespace App\SocialMedia\Platforms;

use App\Models\SocialMediaAccount;
use Carbon\CarbonInterface;

class XService extends AbstractPlatform
{
    public function platform(): string
    {
        return 'x';
    }

    public function fetch(SocialMediaAccount $account, CarbonInterface $since): array
    {
        $token = $this->token($account);
        $id = rawurlencode($account->external_account_id);
        $user = $this->officialGet('https://api.x.com/2/users/'.$id, $token, [
            'user.fields' => 'public_metrics,username,name,url',
        ]);
        $metrics = $user['data']['public_metrics'] ?? [];
        $today = now()->toDateString();
        $days = [[
            'date' => $today,
            'metrics' => $this->mapMetrics([
                'followers' => $metrics['followers_count'] ?? null,
                'following' => $metrics['following_count'] ?? null,
            ]),
            'raw' => $metrics,
        ]];

        $tweets = $this->officialGet('https://api.x.com/2/users/'.$id.'/tweets', $token, [
            'max_results' => 50,
            'start_time' => $since->toIso8601String(),
            'tweet.fields' => 'created_at,public_metrics,text',
            'exclude' => 'replies',
        ]);
        $posts = [];
        foreach ($tweets['data'] ?? [] as $tweet) {
            $public = $tweet['public_metrics'] ?? [];
            $posts[] = [
                'external_post_id' => (string) $tweet['id'],
                'content_type' => 'post',
                'title' => $tweet['text'] ?? null,
                'published_at' => $tweet['created_at'] ?? null,
                'permalink' => 'https://x.com/i/web/status/'.$tweet['id'],
                'media_url' => null,
                'metrics' => $this->mapMetrics([
                    'likes' => $public['like_count'] ?? null,
                    'comments' => $public['reply_count'] ?? null,
                    'shares' => $public['retweet_count'] ?? null,
                    'saves' => $public['bookmark_count'] ?? null,
                    'impressions' => array_key_exists('impression_count', $public) ? $public['impression_count'] : null,
                ]),
                'raw' => $public,
            ];
        }

        return ['profile' => $user['data'] ?? [], 'days' => $days, 'posts' => $posts];
    }
}
