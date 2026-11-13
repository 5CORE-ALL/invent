<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls campaign-level sales the way Shopify shows them in
 * Analytics > Reports > "Performance by marketing campaign"
 * (campaign / channel / traffic type with total sales, sessions, orders, ...)
 * through ShopifyQL on the Admin GraphQL API (needs the read_reports scope,
 * which the other ShopifyQL jobs in this app already use).
 *
 * Shopify does not document one stable dataset for this report, and which
 * dataset/columns exist depends on the API version, so several strategies are
 * tried in order and the one that works is remembered.
 */
class ShopifyMarketingReportService
{
    private const FULL_METRICS  = ['total_sales', 'sessions', 'orders', 'conversion_rate', 'ad_spend', 'roas', 'cpa', 'cac', 'impressions', 'clicks', 'ctr', 'average_order_value', 'new_customers', 'returning_customers'];
    private const BASIC_METRICS = ['total_sales', 'sessions', 'orders'];

    /** Newer API versions first: the marketing dataset only exists in recent ones. */
    private const EXTRA_VERSIONS = ['2026-07', '2026-04', '2026-01', '2025-10'];

    private const STRATEGY_CACHE_KEY = 'shopify_marketing_report:strategy';

    /**
     * @return array{success: bool, rows: list<array<string, mixed>>, totals: array<string, float|int>, days: int, query: ?string, message: ?string, cached: bool}
     */
    public function campaignPerformance(int $days = 30, string $channel = 'meta', bool $refresh = false): array
    {
        $days = max(1, min(365, $days));
        $key  = "shopify_marketing_report:{$days}";

        $cached = false;
        if (! $refresh && Cache::has($key)) {
            $result = Cache::get($key);
            $cached = true;
        } else {
            $result = $this->fetch($days);
            if ($result['success']) {
                Cache::put($key, $result, now()->addMinutes(15));
            }
        }

        $rows = $result['rows'];
        if ($channel === 'meta') {
            $rows = array_values(array_filter($rows, fn ($r) => $this->isMetaChannel((string) ($r['marketing_channel'] ?? ''))));
        }

        return [
            'success' => $result['success'],
            'rows'    => $rows,
            'totals'  => $this->totals($rows),
            'days'    => $days,
            'query'   => $result['query'] ?? null,
            'message' => $result['message'] ?? null,
            'cached'  => $cached,
        ];
    }

    /**
     * Try every candidate query/version and report what Shopify says about each.
     * Used by `php artisan shopify:marketing-report --probe`.
     *
     * @return list<array{version: string, query: string, ok: bool, rows: int, error: ?string, sample: ?array}>
     */
    public function probe(int $days = 30): array
    {
        [$domain, $token] = $this->credentials();
        if (! $domain || ! $token) {
            return [];
        }

        $out = [];
        foreach ($this->probeQueries($days) as $query) {
            foreach ($this->versions() as $version) {
                try {
                    $rows = $this->runShopifyQl($domain, $token, $version, $query);
                    $out[] = ['version' => $version, 'query' => $query, 'ok' => true, 'rows' => count($rows), 'error' => null, 'sample' => $rows[0] ?? null];
                } catch (\Throwable $e) {
                    $out[] = ['version' => $version, 'query' => $query, 'ok' => false, 'rows' => 0, 'error' => mb_substr($e->getMessage(), 0, 220), 'sample' => null];
                }
            }
        }

        return $out;
    }

    private function fetch(int $days): array
    {
        [$domain, $token] = $this->credentials();
        if (! $domain || ! $token) {
            return $this->failure('Shopify credentials are missing (SHOPIFY_STORE_URL / SHOPIFY_ACCESS_TOKEN).');
        }

        $strategies = $this->strategies($days);

        // Try the strategy that worked last time first.
        $remembered = Cache::get(self::STRATEGY_CACHE_KEY);
        if (is_string($remembered)) {
            usort($strategies, fn ($a, $b) => ($b['id'] === $remembered) <=> ($a['id'] === $remembered));
        }

        $errors = [];
        foreach ($strategies as $s) {
            try {
                $rows = $this->runShopifyQl($domain, $token, $s['version'], $s['query']);
            } catch (\Throwable $e) {
                $errors[$s['version'].' '.$s['label']] = mb_substr($e->getMessage(), 0, 160);
                continue;
            }

            Cache::put(self::STRATEGY_CACHE_KEY, $s['id'], now()->addHours(12));

            return [
                'success' => true,
                'rows'    => array_map(fn ($r) => $this->normalizeRow($r, $s['map']), $rows),
                'query'   => $s['query'].'  [API '.$s['version'].']',
                'message' => null,
            ];
        }

        Log::warning('ShopifyMarketingReportService: no strategy worked', ['errors' => $errors]);
        $detail = collect($errors)->map(fn ($e, $k) => "{$k}: {$e}")->take(4)->implode(' | ');

        return $this->failure('Shopify rejected every marketing report query. Run "php artisan shopify:marketing-report --probe" on the server. '.$detail);
    }

    /**
     * @return list<array{id: string, label: string, version: string, query: string, map: array<string, ?string>}>
     */
    private function strategies(int $days): array
    {
        $since = "SINCE -{$days}d UNTIL today ORDER BY total_sales DESC LIMIT 1000";
        $dims  = 'marketing_campaign, marketing_channel, traffic_type';
        $map   = ['campaign' => 'marketing_campaign', 'channel' => 'marketing_channel', 'traffic' => 'traffic_type'];

        $list = [];
        foreach ($this->versions() as $version) {
            $list[] = ['id' => "mkt-full@{$version}", 'label' => 'marketing(full)', 'version' => $version, 'map' => $map,
                'query' => 'FROM marketing SHOW '.implode(', ', self::FULL_METRICS)." GROUP BY {$dims} {$since}"];
            $list[] = ['id' => "mkt-basic@{$version}", 'label' => 'marketing(basic)', 'version' => $version, 'map' => $map,
                'query' => 'FROM marketing SHOW '.implode(', ', self::BASIC_METRICS)." GROUP BY {$dims} {$since}"];
        }

        // Fallback built on UTM tags: sales and sessions per UTM campaign, merged by campaign.
        $version = $this->versions()[0];
        $list[] = ['id' => "sales-utm@{$version}", 'label' => 'sales(utm)', 'version' => $version,
            'map' => ['campaign' => 'utm_campaign_name', 'channel' => 'utm_campaign_source', 'traffic' => null],
            'query' => 'FROM sales SHOW total_sales, orders GROUP BY utm_campaign_name, utm_campaign_source '
                ."SINCE -{$days}d UNTIL today ORDER BY total_sales DESC LIMIT 1000"];

        return $list;
    }

    /** @return list<string> */
    private function probeQueries(int $days): array
    {
        $w = "SINCE -{$days}d UNTIL today LIMIT 5";

        return [
            "FROM marketing SHOW total_sales, sessions, orders GROUP BY marketing_campaign, marketing_channel, traffic_type {$w}",
            "FROM sales SHOW total_sales, orders GROUP BY marketing_campaign, marketing_channel {$w}",
            "FROM sales SHOW total_sales, orders GROUP BY utm_campaign_name, utm_campaign_source {$w}",
            "FROM sales SHOW total_sales, orders GROUP BY referrer_source, referrer_name {$w}",
            "FROM sessions SHOW sessions GROUP BY utm_campaign_name, utm_campaign_source {$w}",
            "FROM sessions SHOW sessions GROUP BY referrer_source, referrer_name {$w}",
        ];
    }

    /** @return list<string> */
    private function versions(): array
    {
        $configured = (string) config('services.shopify.api_version', '2025-01');

        return array_values(array_unique(array_merge([$configured], self::EXTRA_VERSIONS)));
    }

    /** @return array{0: ?string, 1: ?string} */
    private function credentials(): array
    {
        $domain = config('services.shopify.store_url');
        $token  = config('services.shopify.access_token');

        return [$domain ?: null, $token ?: null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runShopifyQl(string $domain, string $token, string $version, string $query): array
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type'           => 'application/json',
        ])->timeout(120)->post(
            "https://{$domain}/admin/api/{$version}/graphql.json",
            [
                'query'     => 'query($q: String!) { shopifyqlQuery(query: $q) { tableData { rows } parseErrors } }',
                'variables' => ['q' => $query],
            ]
        );

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200));
        }

        $payload     = $response->json();
        $parseErrors = $payload['data']['shopifyqlQuery']['parseErrors'] ?? [];
        if (! empty($parseErrors)) {
            $msgs = array_map(fn ($e) => is_string($e) ? $e : (string) ($e['message'] ?? json_encode($e)), (array) $parseErrors);
            throw new \RuntimeException('ShopifyQL: '.implode('; ', $msgs));
        }

        if (! empty($payload['errors'])) {
            $msgs = array_map(fn ($e) => $e['message'] ?? json_encode($e), $payload['errors']);
            throw new \RuntimeException('GraphQL: '.implode('; ', $msgs));
        }

        return $payload['data']['shopifyqlQuery']['tableData']['rows'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, ?string>  $map  which Shopify column feeds campaign / channel / traffic
     */
    private function normalizeRow(array $row, array $map): array
    {
        $num = fn ($k) => isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null ? (float) $row[$k] : null;
        $str = fn ($col) => $col ? (string) ($row[$col] ?? '') : '';

        return [
            'marketing_campaign'  => $str($map['campaign'] ?? null),
            'marketing_channel'   => $str($map['channel'] ?? null),
            'traffic_type'        => $str($map['traffic'] ?? null),
            'total_sales'         => $num('total_sales') ?? 0.0,
            'sessions'            => (int) ($num('sessions') ?? 0),
            'orders'              => (int) ($num('orders') ?? 0),
            'conversion_rate'     => $num('conversion_rate'),
            'ad_spend'            => $num('ad_spend'),
            'roas'                => $num('roas'),
            'cpa'                 => $num('cpa'),
            'cac'                 => $num('cac'),
            'impressions'         => $num('impressions'),
            'clicks'              => $num('clicks'),
            'ctr'                 => $num('ctr'),
            'average_order_value' => $num('average_order_value'),
            'new_customers'       => $num('new_customers'),
            'returning_customers' => $num('returning_customers'),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function totals(array $rows): array
    {
        $sales    = array_sum(array_column($rows, 'total_sales'));
        $sessions = array_sum(array_column($rows, 'sessions'));
        $orders   = array_sum(array_column($rows, 'orders'));
        $spend    = array_sum(array_map(fn ($r) => (float) ($r['ad_spend'] ?? 0), $rows));

        return [
            'campaigns'       => count(array_unique(array_column($rows, 'marketing_campaign'))),
            'total_sales'     => round($sales, 2),
            'sessions'        => (int) $sessions,
            'orders'          => (int) $orders,
            'ad_spend'        => round($spend, 2),
            'conversion_rate' => $sessions > 0 ? round($orders / $sessions * 100, 2) : 0,
            'roas'            => $spend > 0 ? round($sales / $spend, 2) : 0,
        ];
    }

    private function isMetaChannel(string $channel): bool
    {
        $c = strtolower($channel);

        return str_contains($c, 'meta') || str_contains($c, 'facebook') || str_contains($c, 'instagram')
            || $c === 'fb' || $c === 'ig';
    }

    private function failure(string $message): array
    {
        return ['success' => false, 'rows' => [], 'query' => null, 'message' => $message];
    }
}
