<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Campaign-level sales like Shopify's Analytics > Reports > "Performance by marketing campaign".
 *
 * Shopify has no ShopifyQL dataset for that report, so it is rebuilt from two datasets
 * that the store accepts (checked against the live store):
 *   - sales    : total_sales, orders  by order_utm_campaign, referring_channel, traffic_type
 *   - sessions : sessions             by utm_campaign,       referring_channel, traffic_type
 * and merged on campaign + channel + traffic type. Ad spend / impressions / clicks live in the
 * ad platforms (Meta), not in Shopify, so they are not part of this data.
 */
class ShopifyMarketingReportService
{
    /**
     * @return array{success: bool, rows: list<array<string, mixed>>, totals: array<string, float|int>, days: int, message: ?string, cached: bool}
     */
    public function campaignPerformance(int $days = 30, string $channel = 'meta', bool $refresh = false): array
    {
        $days = max(1, min(365, $days));
        $key  = "shopify_marketing_report:v2:{$days}";

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
            $rows = array_values(array_filter($rows, fn ($r) => $this->isMetaChannel($r['marketing_channel'])));
        } else {
            // "All channels": hide the one big bucket of orders that carry no marketing attribution.
            $rows = array_values(array_filter($rows, fn ($r) => $r['marketing_channel'] !== ''));
        }

        return [
            'success' => $result['success'],
            'rows'    => $rows,
            'totals'  => $this->totals($rows),
            'days'    => $days,
            'message' => $result['message'] ?? null,
            'cached'  => $cached,
        ];
    }

    /**
     * Sales and orders per SKU that came from Meta (Facebook / Instagram) traffic,
     * straight from Shopify (ShopifyQL `sales` dataset).
     *
     * @param  string  $traffic  'paid' (ads only) or 'all' (paid + organic)
     * @return array{success: bool, skus: array<string, array{sales: float, orders: int}>, message: ?string}
     */
    public function metaSalesBySku(int $days = 30, string $traffic = 'paid', bool $refresh = false): array
    {
        $days = max(1, min(365, $days));
        $key  = "shopify_meta_sales_by_sku:{$traffic}:{$days}";

        if (! $refresh && Cache::has($key)) {
            return Cache::get($key);
        }

        $domain  = config('services.shopify.store_url');
        $token   = config('services.shopify.access_token');
        $version = config('services.shopify.api_version', '2025-01');
        if (empty($domain) || empty($token)) {
            return ['success' => false, 'skus' => [], 'message' => 'Shopify credentials are missing (SHOPIFY_STORE_URL / SHOPIFY_ACCESS_TOKEN).'];
        }

        $skus = [];
        try {
            foreach (['facebook', 'instagram'] as $source) {
                $rows = $this->runShopifyQl($domain, $token, $version,
                    'FROM sales SHOW total_sales, orders GROUP BY product_variant_sku, traffic_type'
                    ." WHERE referring_channel = '{$source}'"
                    ." SINCE -{$days}d UNTIL today ORDER BY total_sales DESC LIMIT 5000");

                foreach ($rows as $r) {
                    $sku = trim((string) ($r['product_variant_sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    if ($traffic === 'paid' && strtolower((string) ($r['traffic_type'] ?? '')) !== 'paid') {
                        continue;
                    }
                    $skus[$sku]['sales']  = ($skus[$sku]['sales'] ?? 0) + (float) ($r['total_sales'] ?? 0);
                    $skus[$sku]['orders'] = ($skus[$sku]['orders'] ?? 0) + (int) ($r['orders'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ShopifyMarketingReportService: metaSalesBySku failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'skus' => [], 'message' => 'Shopify request failed: '.$e->getMessage()];
        }

        foreach ($skus as &$v) {
            $v['sales'] = round($v['sales'], 2);
        }
        unset($v);

        $result = ['success' => true, 'skus' => $skus, 'message' => null];
        Cache::put($key, $result, now()->addMinutes(15));

        return $result;
    }

    private function fetch(int $days): array
    {
        $domain  = config('services.shopify.store_url');
        $token   = config('services.shopify.access_token');
        $version = config('services.shopify.api_version', '2025-01');

        if (empty($domain) || empty($token)) {
            return $this->failure('Shopify credentials are missing (SHOPIFY_STORE_URL / SHOPIFY_ACCESS_TOKEN).');
        }

        $range = "SINCE -{$days}d UNTIL today";

        try {
            $sales = $this->runShopifyQl($domain, $token, $version,
                "FROM sales SHOW total_sales, orders GROUP BY order_utm_campaign, referring_channel, traffic_type {$range} ORDER BY total_sales DESC LIMIT 2000");
            $sessions = $this->runShopifyQl($domain, $token, $version,
                "FROM sessions SHOW sessions GROUP BY utm_campaign, referring_channel, traffic_type {$range} ORDER BY sessions DESC LIMIT 2000");
        } catch (\Throwable $e) {
            Log::warning('ShopifyMarketingReportService: query failed', ['error' => $e->getMessage()]);

            return $this->failure('Shopify request failed: '.$e->getMessage());
        }

        $merged = [];
        $key = fn (array $r, string $campaignCol) => strtolower(trim((string) ($r[$campaignCol] ?? ''))).'|'
            .strtolower(trim((string) ($r['referring_channel'] ?? ''))).'|'
            .strtolower(trim((string) ($r['traffic_type'] ?? '')));

        $blank = fn (array $r, string $campaignCol) => [
            'marketing_campaign' => trim((string) ($r[$campaignCol] ?? '')),
            'marketing_channel'  => trim((string) ($r['referring_channel'] ?? '')),
            'traffic_type'       => trim((string) ($r['traffic_type'] ?? '')),
            'total_sales'        => 0.0,
            'sessions'           => 0,
            'orders'             => 0,
        ];

        foreach ($sales as $r) {
            $k = $key($r, 'order_utm_campaign');
            $merged[$k] ??= $blank($r, 'order_utm_campaign');
            $merged[$k]['total_sales'] += (float) ($r['total_sales'] ?? 0);
            $merged[$k]['orders']      += (int) ($r['orders'] ?? 0);
        }
        foreach ($sessions as $r) {
            $k = $key($r, 'utm_campaign');
            $merged[$k] ??= $blank($r, 'utm_campaign');
            $merged[$k]['sessions'] += (int) ($r['sessions'] ?? 0);
        }

        $rows = array_map(function (array $r) {
            $r['total_sales']     = round($r['total_sales'], 2);
            $r['conversion_rate'] = $r['sessions'] > 0 ? $r['orders'] / $r['sessions'] : null;   // fraction
            $r['average_order_value'] = $r['orders'] > 0 ? round($r['total_sales'] / $r['orders'], 2) : null;

            return $r;
        }, array_values($merged));

        usort($rows, fn ($a, $b) => [$b['total_sales'], $b['sessions']] <=> [$a['total_sales'], $a['sessions']]);

        return ['success' => true, 'rows' => $rows, 'message' => null];
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

    /** @param list<array<string, mixed>> $rows */
    private function totals(array $rows): array
    {
        $sales    = array_sum(array_column($rows, 'total_sales'));
        $sessions = array_sum(array_column($rows, 'sessions'));
        $orders   = array_sum(array_column($rows, 'orders'));

        return [
            'campaigns'       => count(array_unique(array_filter(array_column($rows, 'marketing_campaign')))),
            'total_sales'     => round($sales, 2),
            'sessions'        => (int) $sessions,
            'orders'          => (int) $orders,
            'conversion_rate' => $sessions > 0 ? round($orders / $sessions * 100, 2) : 0,
        ];
    }

    private function isMetaChannel(string $channel): bool
    {
        $c = strtolower($channel);

        return str_contains($c, 'facebook') || str_contains($c, 'instagram') || str_contains($c, 'meta')
            || $c === 'fb' || $c === 'ig';
    }

    private function failure(string $message): array
    {
        return ['success' => false, 'rows' => [], 'message' => $message];
    }
}
