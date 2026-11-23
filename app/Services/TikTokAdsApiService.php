<?php

namespace App\Services;

use App\Models\TiktokAdsConnection;
use App\Models\TiktokAdsDailyReport;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * TikTok Marketing API (business-api.tiktok.com) — Ads Manager campaign reporting.
 *
 * Flow: advertiser authorizes the app -> auth_code on the redirect URL -> exchangeAuthCode()
 * stores a long-lived access token per advertiser -> syncCampaignReports() pulls daily spend.
 *
 * TikTok blocks India IPs, so every call can be routed through TIKTOK_ADS_PROXY.
 */
class TikTokAdsApiService
{
    /** Daily reports allow at most a 30 day window per request. */
    private const MAX_WINDOW_DAYS = 30;

    private const REPORT_METRICS = [
        'campaign_name', 'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm',
        'conversion', 'cost_per_conversion', 'conversion_rate',
    ];

    public function isConfigured(): bool
    {
        return filled(config('services.tiktok_ads.app_id')) && filled(config('services.tiktok_ads.app_secret'));
    }

    public function authorizationUrl(string $state): string
    {
        return rtrim((string) config('services.tiktok_ads.api_base'), '/').'/portal/auth?'.http_build_query([
            'app_id' => config('services.tiktok_ads.app_id'),
            'state' => $state,
            'redirect_uri' => config('services.tiktok_ads.redirect_uri'),
        ]);
    }

    /**
     * Exchange the one-time auth_code for an access token and store it for every authorized advertiser.
     *
     * @return array<int, array{advertiser_id: string, advertiser_name: ?string, currency: ?string}>
     */
    public function exchangeAuthCode(string $authCode): array
    {
        $data = $this->call('POST', '/open_api/v1.3/oauth2/access_token/', [
            'app_id' => config('services.tiktok_ads.app_id'),
            'secret' => config('services.tiktok_ads.app_secret'),
            'auth_code' => $authCode,
        ]);

        $token = (string) ($data['access_token'] ?? '');
        $ids = array_map('strval', (array) ($data['advertiser_ids'] ?? []));
        if ($token === '' || $ids === []) {
            throw new RuntimeException('TikTok returned no access token / advertiser ids. Check the app scopes.');
        }

        $info = $this->advertiserInfo($ids, $token);
        $saved = [];
        foreach ($ids as $id) {
            $row = $info[$id] ?? [];
            TiktokAdsConnection::updateOrCreate(
                ['advertiser_id' => $id],
                [
                    'advertiser_name' => $row['name'] ?? null,
                    'currency' => $row['currency'] ?? null,
                    'access_token' => $token,
                    'scope' => $data['scope'] ?? null,
                    'authorized_at' => now(),
                ]
            );
            $saved[] = [
                'advertiser_id' => $id,
                'advertiser_name' => $row['name'] ?? null,
                'currency' => $row['currency'] ?? null,
            ];
        }

        return $saved;
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<string, array<string, mixed>> keyed by advertiser_id
     */
    public function advertiserInfo(array $ids, string $token): array
    {
        $data = $this->call('GET', '/open_api/v1.3/advertiser/info/', [
            'advertiser_ids' => json_encode(array_values($ids)),
            'fields' => json_encode(['advertiser_id', 'name', 'currency', 'status', 'timezone']),
        ], $token);

        $out = [];
        foreach ((array) ($data['list'] ?? []) as $row) {
            $out[(string) ($row['advertiser_id'] ?? '')] = $row;
        }

        return $out;
    }

    /**
     * Daily campaign-level spend/clicks/impressions/conversions into tiktok_ads_daily_reports.
     *
     * @return array{rows: int, advertisers: int}
     */
    public function syncCampaignReports(int $days = 30, ?string $advertiserId = null, ?callable $log = null): array
    {
        $connections = TiktokAdsConnection::query()
            ->when($advertiserId, fn ($q) => $q->where('advertiser_id', $advertiserId))
            ->get();
        if ($connections->isEmpty()) {
            throw new RuntimeException('No authorized advertiser. Authorize the app first (/tiktok-ads/connect).');
        }

        $end = Carbon::now()->startOfDay();
        $start = $end->copy()->subDays(max(1, $days) - 1);
        $total = 0;

        foreach ($connections as $conn) {
            $count = 0;
            for ($from = $start->copy(); $from->lte($end); $from->addDays(self::MAX_WINDOW_DAYS)) {
                $to = $from->copy()->addDays(self::MAX_WINDOW_DAYS - 1)->min($end);
                $count += $this->syncWindow($conn, $from, $to);
                $log && $log("Advertiser {$conn->advertiser_id}: {$from->toDateString()} → {$to->toDateString()} ({$count} rows so far)");
            }
            $total += $count;
        }

        return ['rows' => $total, 'advertisers' => $connections->count()];
    }

    private function syncWindow(TiktokAdsConnection $conn, Carbon $from, Carbon $to): int
    {
        $rows = 0;
        $page = 1;
        do {
            $data = $this->call('GET', '/open_api/v1.3/report/integrated/get/', [
                'advertiser_id' => $conn->advertiser_id,
                'report_type' => 'BASIC',
                'data_level' => 'AUCTION_CAMPAIGN',
                'dimensions' => json_encode(['campaign_id', 'stat_time_day']),
                'metrics' => json_encode(self::REPORT_METRICS),
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
                'page' => $page,
                'page_size' => 1000,
            ], $conn->access_token);

            foreach ((array) ($data['list'] ?? []) as $item) {
                $dim = (array) ($item['dimensions'] ?? []);
                $m = (array) ($item['metrics'] ?? []);
                $campaignId = (string) ($dim['campaign_id'] ?? '');
                $day = substr((string) ($dim['stat_time_day'] ?? ''), 0, 10);
                if ($campaignId === '' || $day === '') {
                    continue;
                }

                TiktokAdsDailyReport::updateOrCreate(
                    ['advertiser_id' => $conn->advertiser_id, 'campaign_id' => $campaignId, 'stat_date' => $day],
                    [
                        'campaign_name' => $m['campaign_name'] ?? null,
                        'spend' => (float) ($m['spend'] ?? 0),
                        'impressions' => (int) ($m['impressions'] ?? 0),
                        'clicks' => (int) ($m['clicks'] ?? 0),
                        'ctr' => $this->num($m['ctr'] ?? null),
                        'cpc' => $this->num($m['cpc'] ?? null),
                        'cpm' => $this->num($m['cpm'] ?? null),
                        'conversions' => (int) ($m['conversion'] ?? 0),
                        'cost_per_conversion' => $this->num($m['cost_per_conversion'] ?? null),
                        'conversion_rate' => $this->num($m['conversion_rate'] ?? null),
                        'raw' => $m,
                    ]
                );
                $rows++;
            }

            $totalPages = (int) ($data['page_info']['total_page'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $rows;
    }

    /**
     * Generic authenticated GET/POST — used by `tiktok-ads:request` to try other endpoints
     * (e.g. GMV Max) without writing code first.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed> the "data" node of the response
     */
    public function call(string $method, string $path, array $params = [], ?string $token = null): array
    {
        $http = $this->http($token);
        $url = '/'.ltrim($path, '/');

        try {
            $response = strtoupper($method) === 'GET'
                ? $http->get($url, $params)
                : $http->post($url, $params);
        } catch (\Throwable $e) {
            throw new RuntimeException('TikTok request failed: '.$e->getMessage().$this->proxyHint(), 0, $e);
        }

        $json = $response->json();
        if (! is_array($json)) {
            $body = trim((string) $response->body());
            throw new RuntimeException(
                'TikTok returned a non-JSON response (HTTP '.$response->status().'): '.mb_substr($body, 0, 200)
                .(stripos($body, 'banned Country') !== false ? $this->proxyHint(true) : '')
            );
        }
        if ((int) ($json['code'] ?? -1) !== 0) {
            throw new RuntimeException('TikTok API error code '.($json['code'] ?? '?').': '.($json['message'] ?? 'unknown'));
        }

        return (array) ($json['data'] ?? []);
    }

    private function http(?string $token): PendingRequest
    {
        $http = Http::baseUrl(rtrim((string) config('services.tiktok_ads.api_base'), '/'))
            ->acceptJson()
            ->timeout((int) config('services.tiktok_ads.timeout', 30));

        if ($token) {
            $http = $http->withHeaders(['Access-Token' => $token]);
        }
        if ($proxy = config('services.tiktok_ads.proxy')) {
            $http = $http->withOptions(['proxy' => $proxy]);
        }

        return $http;
    }

    private function proxyHint(bool $banned = false): string
    {
        $hint = config('services.tiktok_ads.proxy')
            ? ' (proxy in use: check it is alive and exits outside India)'
            : ' — TikTok blocks India IPs; set TIKTOK_ADS_PROXY to a non-India proxy/VPN.';

        return $banned ? ' IP blocked.'.$hint : $hint;
    }

    private function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }
}
