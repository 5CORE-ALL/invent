<?php

namespace App\SocialMedia\Platforms;

use App\Models\SocialMediaAccount;
use Carbon\CarbonInterface;

class LinkedInService extends AbstractPlatform
{
    public function platform(): string
    {
        return 'linkedin';
    }

    public function fetch(SocialMediaAccount $account, CarbonInterface $since): array
    {
        $token = $this->token($account);
        $headers = $this->headers();
        $orgId = $account->external_account_id;
        $urn = 'urn:li:organization:'.$orgId;
        $profile = $this->officialGet('https://api.linkedin.com/rest/organizations/'.rawurlencode($orgId), $token, [], $headers);
        $followers = $this->officialGet('https://api.linkedin.com/rest/networkSizes/'.rawurlencode($urn), $token, [
            'edgeType' => 'COMPANY_FOLLOWED_BY_MEMBER',
        ], $headers);

        $startMs = $since->copy()->startOfDay()->getTimestampMs();
        $endMs = now()->copy()->startOfDay()->getTimestampMs();
        $statsUrl = 'https://api.linkedin.com/rest/organizationalEntityShareStatistics'
            .'?q=organizationalEntity'
            .'&organizationalEntity='.rawurlencode($urn)
            .'&timeIntervals=(timeRange:(start:'.$startMs.',end:'.$endMs.'),timeGranularityType:DAY)';
        $stats = $this->officialGet($statsUrl, $token, [], $headers);

        $byDate = [];
        foreach ($stats['elements'] ?? [] as $element) {
            $start = $element['timeRange']['start'] ?? null;
            $share = $element['totalShareStatistics'] ?? null;
            if ($start === null || ! is_array($share)) {
                continue;
            }
            $date = now()->createFromTimestampMs((int) $start)->toDateString();
            $byDate[$date] = [
                'impressions' => $share['impressionCount'] ?? null,
                'likes' => $share['likeCount'] ?? null,
                'comments' => $share['commentCount'] ?? null,
                'shares' => $share['shareCount'] ?? null,
                'clicks' => $share['clickCount'] ?? null,
                'raw' => $share,
            ];
        }
        if (array_key_exists('firstDegreeSize', $followers)) {
            $today = now()->toDateString();
            $byDate[$today]['followers'] = $followers['firstDegreeSize'];
        }

        $days = [];
        foreach ($byDate as $date => $values) {
            $raw = $values['raw'] ?? [];
            unset($values['raw']);
            $days[] = ['date' => $date, 'metrics' => $this->mapMetrics($values), 'raw' => $raw];
        }

        $posts = [];
        $feed = $this->officialGet('https://api.linkedin.com/rest/posts', $token, [
            'q' => 'author',
            'author' => $urn,
            'count' => 50,
            'sortBy' => 'LAST_MODIFIED',
        ], $headers);
        foreach ($feed['elements'] ?? [] as $post) {
            $postId = (string) ($post['id'] ?? '');
            if ($postId === '') {
                continue;
            }
            $published = $post['publishedAt'] ?? $post['createdAt'] ?? null;
            $posts[] = [
                'external_post_id' => $postId,
                'content_type' => 'post',
                'title' => $post['commentary'] ?? null,
                'published_at' => is_numeric($published) ? now()->createFromTimestampMs((int) $published)->toIso8601String() : $published,
                'permalink' => null,
                'media_url' => null,
                'metrics' => $this->mapMetrics([]),
                'raw' => ['id' => $postId],
            ];
        }

        return [
            'profile' => [
                'name' => $profile['localizedName'] ?? $account->account_name,
                'followers_count' => $followers['firstDegreeSize'] ?? null,
            ],
            'days' => $days,
            'posts' => $posts,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'LinkedIn-Version' => (string) config('social_media.linkedin_version', '202608'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
    }
}
