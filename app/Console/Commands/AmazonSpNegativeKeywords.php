<?php

namespace App\Console\Commands;

use App\Models\AmazonSpNegativeKeyword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fetch Sponsored Products negative keywords and store one row per Amazon keywordId.
 *
 *   - Campaign-level: POST /sp/campaignNegativeKeywords/list  (LEVEL_CAMPAIGN)
 *   - Ad-group-level: POST /sp/negativeKeywords/list          (LEVEL_AD_GROUP)
 *
 * Idempotent upsert keyed by keyword_id. With --prune, negatives no longer returned by
 * the API are deleted so the grid mirrors the live account (same pattern as the Google
 * Ads negative-keyword fetch).
 */
class AmazonSpNegativeKeywords extends Command
{
    protected $signature = 'app:amazon-sp-negative-keywords {--level=all : all|campaign|ad_group} {--prune : delete negatives no longer present in Amazon}';

    protected $description = 'Fetch and store Sponsored Products campaign + ad-group negative keywords';

    private const PAGE_SIZE = 1000;

    /** Only pull live negatives — skip PAUSED / ARCHIVED so the fetch is far faster. */
    private const STATE_FILTER = ['include' => ['ENABLED']];

    public function handle()
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->disconnect();
        } catch (\Exception $e) {
            $this->error('✗ Database connection failed: '.$e->getMessage());

            return 1;
        }

        $profileId = config('services.amazon_ads.profile_ids');
        if (empty($profileId)) {
            $this->error('AMAZON_ADS_PROFILE_IDS is not set in environment.');

            return 1;
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return 1;
        }

        $level = strtolower((string) $this->option('level'));
        $prune = (bool) $this->option('prune');
        $campaignNames = $this->campaignNameMap($profileId);

        $seenIds = [];

        if ($level === 'all' || $level === 'campaign') {
            $seenIds = array_merge($seenIds, $this->syncCampaignNegatives($profileId, $campaignNames));
            DB::connection()->disconnect();
        }

        if ($level === 'all' || $level === 'ad_group') {
            $seenIds = array_merge($seenIds, $this->syncAdGroupNegatives($profileId, $campaignNames));
            DB::connection()->disconnect();
        }

        if ($prune) {
            $this->pruneStale($profileId, $level, $seenIds);
        }

        $this->info('✅ SP negative keywords sync complete. Rows seen: '.count($seenIds));
        DB::connection()->disconnect();

        return 0;
    }

    /**
     * @return array<int, string> keyword ids seen
     */
    private function syncCampaignNegatives(string $profileId, array $campaignNames): array
    {
        $this->info('Fetching campaign-level negative keywords...');
        $seen = [];
        $nextToken = null;

        do {
            $body = ['maxResults' => self::PAGE_SIZE, 'stateFilter' => self::STATE_FILTER];
            if ($nextToken) {
                $body['nextToken'] = $nextToken;
            }

            $response = Http::timeout(60)
                ->retry(3, 3000)
                ->withToken($this->getAccessToken())
                ->withHeaders([
                    'Amazon-Advertising-API-Scope' => $profileId,
                    'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                    'Content-Type' => 'application/vnd.spCampaignNegativeKeyword.v3+json',
                    'Accept' => 'application/vnd.spCampaignNegativeKeyword.v3+json',
                ])
                ->post('https://advertising-api.amazon.com/sp/campaignNegativeKeywords/list', $body);

            if (! $response->ok()) {
                $this->error('Campaign negative keywords request failed: '.$response->body());
                break;
            }

            $items = $response->json('campaignNegativeKeywords') ?? [];
            foreach ($items as $item) {
                $id = $this->storeRow($item, AmazonSpNegativeKeyword::LEVEL_CAMPAIGN, $profileId, $campaignNames);
                if ($id !== null) {
                    $seen[] = $id;
                }
            }

            $nextToken = $response->json('nextToken');
        } while (! empty($nextToken));

        $this->info('Campaign-level negatives stored: '.count($seen));

        return $seen;
    }

    /**
     * @return array<int, string> keyword ids seen
     */
    private function syncAdGroupNegatives(string $profileId, array $campaignNames): array
    {
        $this->info('Fetching ad-group-level negative keywords...');
        $seen = [];
        $nextToken = null;

        do {
            $body = ['maxResults' => self::PAGE_SIZE, 'stateFilter' => self::STATE_FILTER];
            if ($nextToken) {
                $body['nextToken'] = $nextToken;
            }

            $response = Http::timeout(60)
                ->retry(3, 3000)
                ->withToken($this->getAccessToken())
                ->withHeaders([
                    'Amazon-Advertising-API-Scope' => $profileId,
                    'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                    'Content-Type' => 'application/vnd.spNegativeKeyword.v3+json',
                    'Accept' => 'application/vnd.spNegativeKeyword.v3+json',
                ])
                ->post('https://advertising-api.amazon.com/sp/negativeKeywords/list', $body);

            if (! $response->ok()) {
                $this->error('Ad-group negative keywords request failed: '.$response->body());
                break;
            }

            $items = $response->json('negativeKeywords') ?? [];
            foreach ($items as $item) {
                $id = $this->storeRow($item, AmazonSpNegativeKeyword::LEVEL_AD_GROUP, $profileId, $campaignNames);
                if ($id !== null) {
                    $seen[] = $id;
                }
            }

            $nextToken = $response->json('nextToken');
        } while (! empty($nextToken));

        $this->info('Ad-group-level negatives stored: '.count($seen));

        return $seen;
    }

    private function storeRow(array $item, string $level, string $profileId, array $campaignNames): ?string
    {
        $keywordId = $item['keywordId'] ?? null;
        if ($keywordId === null || $keywordId === '') {
            return null;
        }
        $keywordId = (string) $keywordId;
        $campaignId = isset($item['campaignId']) ? (string) $item['campaignId'] : null;

        AmazonSpNegativeKeyword::updateOrCreate(
            ['keyword_id' => $keywordId],
            [
                'profile_id' => $profileId,
                'level' => $level,
                'campaign_id' => $campaignId,
                'campaignName' => $campaignId !== null ? ($campaignNames[$campaignId] ?? null) : null,
                'ad_group_id' => isset($item['adGroupId']) ? (string) $item['adGroupId'] : null,
                'keywordText' => $item['keywordText'] ?? null,
                'matchType' => $item['matchType'] ?? null,
                'state' => $item['state'] ?? null,
            ]
        );

        return $keywordId;
    }

    private function pruneStale(string $profileId, string $level, array $seenIds): void
    {
        $query = AmazonSpNegativeKeyword::where('profile_id', $profileId);

        if ($level === 'campaign') {
            $query->where('level', AmazonSpNegativeKeyword::LEVEL_CAMPAIGN);
        } elseif ($level === 'ad_group') {
            $query->where('level', AmazonSpNegativeKeyword::LEVEL_AD_GROUP);
        }

        if (! empty($seenIds)) {
            $deleted = 0;
            foreach (array_chunk($query->pluck('keyword_id')->all(), 5000) as $chunk) {
                $stale = array_diff($chunk, $seenIds);
                if (! empty($stale)) {
                    $deleted += AmazonSpNegativeKeyword::whereIn('keyword_id', $stale)->delete();
                }
            }
            $this->info('Pruned stale negatives: '.$deleted);
        }
    }

    /**
     * Campaign id → name from the latest SP campaign report rows (best-effort labels).
     *
     * @return array<string, string>
     */
    private function campaignNameMap(string $profileId): array
    {
        try {
            return DB::table('amazon_sp_campaign_reports')
                ->where('profile_id', $profileId)
                ->whereNotNull('campaign_id')
                ->whereNotNull('campaignName')
                ->pluck('campaignName', 'campaign_id')
                ->map(fn ($v) => (string) $v)
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getAccessToken()
    {
        try {
            $clientId = config('services.amazon_ads.client_id');
            $clientSecret = config('services.amazon_ads.client_secret');
            $refreshToken = config('services.amazon_ads.refresh_token');

            if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
                $this->error('Amazon Ads credentials are not set in environment.');

                return null;
            }

            $tokenResponse = Http::timeout(15)->asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (! $tokenResponse->successful()) {
                $this->error('Token fetch failed: '.$tokenResponse->body());

                return null;
            }

            $accessToken = $tokenResponse['access_token'] ?? null;
            if (empty($accessToken)) {
                $this->error('Access token not returned in response.');

                return null;
            }

            return $accessToken;
        } catch (\Exception $e) {
            $this->error('Error getting access token: '.$e->getMessage());

            return null;
        }
    }
}
