<?php

namespace App\Console\Commands;

use App\Models\InstagramShopSoldRaw;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncInstagramShopSoldRaw extends Command
{
    protected $signature = 'app:sync-instagram-shop-sold
                            {--days=365 : Lookback window in days}
                            {--dry-run : Fetch and report without writing}';

    protected $description = 'Store Facebook & Instagram sold rows from Shopify, one row per order and SKU';

    public function handle(): int
    {
        $storeUrl = config('services.shopify.store_url');
        $accessToken = config('services.shopify.access_token');
        $apiVersion = config('services.shopify.api_version', '2025-01');
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        if (empty($storeUrl) || empty($accessToken)) {
            $this->error('Missing Shopify API credentials (SHOPIFY_STORE_URL / SHOPIFY_ACCESS_TOKEN)');

            return self::FAILURE;
        }

        $this->info("Fetching Facebook & Instagram sold rows for the last {$days} day(s)...");

        try {
            $rows = $this->fetchSoldRows($storeUrl, $accessToken, $apiVersion, $days);
        } catch (\Throwable $e) {
            Log::error('SyncInstagramShopSoldRaw: ShopifyQL failed', ['error' => $e->getMessage()]);
            $this->error('ShopifyQL fetch failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $productUrls = $this->fetchProductUrls($storeUrl, $accessToken, $apiVersion, $rows);

        $saved = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sku = trim((string) ($row['product_variant_sku'] ?? ''));
            $orderName = trim((string) ($row['order_name'] ?? ''));
            if ($sku === '' || $orderName === '') {
                $skipped++;
                continue;
            }

            $quantity = (int) ($row['net_items_sold'] ?? 0);
            $gross = round((float) ($row['gross_sales'] ?? 0), 2);
            $payload = [
                'sale_date' => $row['day'] ?? null,
                'url' => $this->pageUrl($productUrls, $row),
                'product_title' => $row['product_title'] ?? null,
                'quantity' => $quantity,
                'sold_price' => $quantity > 0 ? round($gross / $quantity, 2) : 0,
                'gross_sales' => $gross,
                'net_sales' => round((float) ($row['net_sales'] ?? 0), 2),
                'discounts' => round((float) ($row['discounts'] ?? 0), 2),
                'returns' => round((float) ($row['returns'] ?? 0), 2),
                'sales_channel' => 'Facebook & Instagram',
            ];

            if ($dryRun) {
                $saved++;
                continue;
            }

            InstagramShopSoldRaw::updateOrCreate(
                ['order_name' => $orderName, 'sku' => $sku],
                $payload
            );
            $saved++;
        }

        $this->info(($dryRun ? 'Would store' : 'Stored')." {$saved} SKU rows. Skipped {$skipped} rows with no SKU or order.");

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSoldRows(string $domain, string $accessToken, string $apiVersion, int $days): array
    {
        $query = 'FROM sales SHOW net_items_sold, gross_sales, net_sales, discounts, returns'
            ." WHERE sales_channel = 'Facebook & Instagram'"
            .' GROUP BY day, order_name, product_variant_sku, product_title, product_id, product_variant_id'
            ." SINCE -{$days}d"
            .' ORDER BY day DESC'
            .' LIMIT 1000';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post(
            "https://{$domain}/admin/api/{$apiVersion}/graphql.json",
            [
                'query' => 'query($q: String!) { shopifyqlQuery(query: $q) { tableData { rows } parseErrors } }',
                'variables' => ['q' => $query],
            ]
        );

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        $payload = $response->json();
        $parseErrors = $payload['data']['shopifyqlQuery']['parseErrors'] ?? [];
        if (! empty($parseErrors)) {
            $msgs = [];
            foreach ($parseErrors as $err) {
                $msgs[] = is_string($err) ? $err : (string) ($err['message'] ?? json_encode($err));
            }
            throw new \RuntimeException('ShopifyQL parse errors: '.implode('; ', $msgs));
        }

        if (! empty($payload['errors'])) {
            $msgs = array_map(fn ($e) => $e['message'] ?? json_encode($e), $payload['errors']);
            throw new \RuntimeException('GraphQL errors: '.implode('; ', $msgs));
        }

        return $payload['data']['shopifyqlQuery']['tableData']['rows'] ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string> product id => online store URL
     */
    private function fetchProductUrls(string $domain, string $accessToken, string $apiVersion, array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $productId = trim((string) ($row['product_id'] ?? ''));
            if ($productId !== '') {
                $ids[$productId] = 'gid://shopify/Product/'.$productId;
            }
        }

        if ($ids === []) {
            return [];
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post(
            "https://{$domain}/admin/api/{$apiVersion}/graphql.json",
            [
                'query' => 'query($ids: [ID!]!) { nodes(ids: $ids) { ... on Product { id onlineStoreUrl } } }',
                'variables' => ['ids' => array_values($ids)],
            ]
        );

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        $payload = $response->json();
        if (! empty($payload['errors'])) {
            $msgs = array_map(fn ($e) => $e['message'] ?? json_encode($e), $payload['errors']);
            throw new \RuntimeException('GraphQL errors: '.implode('; ', $msgs));
        }

        $urls = [];
        foreach ($payload['data']['nodes'] ?? [] as $node) {
            if (empty($node['id']) || empty($node['onlineStoreUrl'])) {
                continue;
            }
            $numericId = basename((string) $node['id']);
            $urls[$numericId] = (string) $node['onlineStoreUrl'];
        }

        return $urls;
    }

    /**
     * Product page on the online store, without a variant query.
     *
     * @param  array<string, string>  $productUrls
     * @param  array<string, mixed>  $row
     */
    private function pageUrl(array $productUrls, array $row): ?string
    {
        $productId = trim((string) ($row['product_id'] ?? ''));
        $url = $productUrls[$productId] ?? null;
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return strtok($url, '?') ?: $url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '';

        return $scheme.'://'.$parts['host'].$path;
    }
}
