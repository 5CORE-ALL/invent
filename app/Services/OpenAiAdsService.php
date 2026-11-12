<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ChatGPT Ads — OpenAI Advertiser API client (https://developers.openai.com/ads).
 *
 * The API key is scoped to ONE ad account (services.openai_ads.*, set in .env as OPENAI_ADS_API_KEY / OPENAI_ADS_ACCOUNT_ID).
 * Limits: 600 requests / minute per endpoint, 1,200 / minute overall.
 */
class OpenAiAdsService
{
    public function isConfigured(): bool
    {
        return $this->apiKey() !== '' && $this->accountId() !== '';
    }

    public function accountId(): string
    {
        return trim((string) config('services.openai_ads.account_id'));
    }

    private function apiKey(): string
    {
        return trim((string) config('services.openai_ads.api_key'));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.openai_ads.base_url', 'https://api.ads.openai.com/v1'), '/');
    }

    private function client(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ChatGPT Ads is not configured (set OPENAI_ADS_API_KEY and OPENAI_ADS_ACCOUNT_ID in .env).');
        }

        return Http::withToken($this->apiKey())
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 500, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, throw: false);
    }

    /**
     * GET a path. Array values are sent as repeated `key[]=` pairs (the API's documented format), not `key[0]=`.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl().'/'.ltrim($path, '/');
        $qs = preg_replace('/%5B\d+%5D=/', '%5B%5D=', http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        if ($qs !== '') {
            $url .= '?'.$qs;
        }

        $res = $this->client()->get($url);
        if ($res->failed()) {
            $msg = (string) ($res->json('error.message') ?? $res->json('message') ?? $res->body());
            throw new RuntimeException('ChatGPT Ads API '.$res->status().' on GET /'.ltrim($path, '/').': '.mb_substr($msg, 0, 300));
        }

        return (array) $res->json();
    }

    /** GET /ad_account — also confirms the key works. */
    public function adAccount(): array
    {
        return $this->get('ad_account');
    }

    /** IANA time zone of the ad account (reporting days are in this zone). */
    public function timezone(): string
    {
        return (string) Cache::remember('openai_ads:timezone', now()->addHours(12), function () {
            try {
                return (string) ($this->adAccount()['timezone'] ?? 'UTC');
            } catch (\Throwable $e) {
                return 'UTC';
            }
        });
    }

    /**
     * Follow `after` / `last_id` until `has_more` is false.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function paginate(string $path, array $query = [], int $maxPages = 50): array
    {
        $rows = [];
        $after = null;
        for ($page = 0; $page < $maxPages; $page++) {
            $q = $query;
            if ($after !== null) {
                $q['after'] = $after;
            }
            $res = $this->get($path, $q);
            foreach ((array) ($res['data'] ?? []) as $row) {
                $rows[] = (array) $row;
            }
            if (empty($res['has_more']) || empty($res['last_id'])) {
                break;
            }
            $after = (string) $res['last_id'];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function campaigns(): array
    {
        return $this->paginate('campaigns', ['limit' => 500, 'order' => 'desc']);
    }

    /**
     * Last N full days (ending yesterday, in the ad account's time zone) as an Insights `time_ranges[]` value.
     */
    public function lastDaysRange(int $days = 30): string
    {
        $tz = $this->timezone();
        $until = Carbon::now($tz)->subDay()->toDateString();
        $since = Carbon::now($tz)->subDays($days)->toDateString();

        return json_encode(['type' => 'date_range', 'since' => $since, 'until' => $until, 'timezone' => $tz]);
    }

    /**
     * Account totals for the last N days, one row per campaign.
     *
     * @return list<array<string, mixed>>
     */
    public function campaignInsights(int $days = 30): array
    {
        return $this->paginate('ad_account/insights', [
            'aggregation_level' => 'campaign',
            'time_granularity' => 'none',
            'time_ranges' => [$this->lastDaysRange($days)],
            'fields' => ['campaign.id', 'campaign.name', 'campaign.status', 'campaign.impressions', 'campaign.clicks', 'campaign.spend', 'campaign.ctr', 'campaign.cpc'],
            'limit' => 2000,
        ]);
    }

    /**
     * Product-feed rows for the last N days, keyed by upper-cased product feed `item_id` (which is the SKU in the feed).
     * Needs a product-segment enabled ad account; returns [] with a log line when the account can't segment by product.
     *
     * @return array<string, array{impressions:int, clicks:int, spend:float}>
     */
    public function productInsightsBySku(int $days = 30): array
    {
        try {
            $rows = $this->paginate('ad_account/insights', [
                'aggregation_level' => 'ad_account',
                'time_granularity' => 'none',
                'segments' => ['product'],
                'override_segment_group_order' => ['product', 'ad_account'],
                'time_ranges' => [$this->lastDaysRange($days)],
                'fields' => ['product.feed_id', 'product.item_id', 'product.title', 'product.impressions', 'product.clicks', 'product.spend'],
                'limit' => 2000,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ChatGPT Ads product insights unavailable: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $item = strtoupper(trim((string) ($r['item_id'] ?? $r['product_item_id'] ?? '')));
            if ($item === '') {
                continue;
            }
            $out[$item] ??= ['impressions' => 0, 'clicks' => 0, 'spend' => 0.0];
            $out[$item]['impressions'] += (int) ($r['product_impressions'] ?? $r['impressions'] ?? 0);
            $out[$item]['clicks'] += (int) ($r['product_clicks'] ?? $r['clicks'] ?? 0);
            $out[$item]['spend'] += (float) ($r['product_spend'] ?? $r['spend'] ?? 0);
        }

        return $out;
    }
}
