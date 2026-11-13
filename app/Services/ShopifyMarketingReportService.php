<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the same numbers Shopify shows in
 * Analytics > Reports > "Performance by marketing campaign"
 * (marketing campaign / channel / traffic type with total sales, sessions, orders, ...)
 * through ShopifyQL on the Admin GraphQL API (needs the read_reports scope,
 * which the other ShopifyQL jobs in this app already use).
 */
class ShopifyMarketingReportService
{
    /** Metrics the report can show, in the order Shopify lists them. */
    private const FULL_METRICS = [
        'total_sales', 'sessions', 'orders', 'conversion_rate', 'ad_spend', 'roas',
        'cpa', 'cac', 'impressions', 'clicks', 'ctr', 'average_order_value',
        'new_customers', 'returning_customers',
    ];

    /** Fallback if Shopify rejects one of the extra metrics for this store/API version. */
    private const BASIC_METRICS = ['total_sales', 'sessions', 'orders'];

    private const DIMENSIONS = ['marketing_campaign', 'marketing_channel', 'traffic_type'];

    /**
     * @return array{
     *   success: bool,
     *   rows: list<array<string, mixed>>,
     *   totals: array<string, float|int>,
     *   days: int,
     *   query: ?string,
     *   message: ?string,
     *   cached: bool
     * }
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

    private function fetch(int $days): array
    {
        $domain  = config('services.shopify.store_url');
        $token   = config('services.shopify.access_token');
        $version = config('services.shopify.api_version', '2025-01');

        if (empty($domain) || empty($token)) {
            return $this->failure('Shopify credentials are missing (SHOPIFY_STORE_URL / SHOPIFY_ACCESS_TOKEN).');
        }

        $errors = [];
        foreach ([self::FULL_METRICS, self::BASIC_METRICS] as $metrics) {
            $query = $this->buildQuery($metrics, $days);

            try {
                $rows = $this->runShopifyQl($domain, $token, $version, $query);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                Log::warning('ShopifyMarketingReportService: query failed', ['query' => $query, 'error' => $e->getMessage()]);
                continue;
            }

            return [
                'success' => true,
                'rows'    => array_map(fn ($r) => $this->normalizeRow($r), $rows),
                'query'   => $query,
                'message' => null,
            ];
        }

        return $this->failure('Shopify rejected the marketing report query: '.implode(' | ', array_unique($errors)));
    }

    private function buildQuery(array $metrics, int $days): string
    {
        return 'FROM marketing SHOW '.implode(', ', $metrics)
            .' GROUP BY '.implode(', ', self::DIMENSIONS)
            ." SINCE -{$days}d UNTIL today"
            .' ORDER BY total_sales DESC'
            .' LIMIT 1000';
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
            throw new \RuntimeException('HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300));
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

    /** @param array<string, mixed> $row */
    private function normalizeRow(array $row): array
    {
        $num = fn ($k) => isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null ? (float) $row[$k] : null;

        return [
            'marketing_campaign'  => (string) ($row['marketing_campaign'] ?? ''),
            'marketing_channel'   => (string) ($row['marketing_channel'] ?? ''),
            'traffic_type'        => (string) ($row['traffic_type'] ?? ''),
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

        return str_contains($c, 'meta') || str_contains($c, 'facebook') || str_contains($c, 'instagram');
    }

    private function failure(string $message): array
    {
        return ['success' => false, 'rows' => [], 'query' => null, 'message' => $message];
    }
}
