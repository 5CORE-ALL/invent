<?php

namespace App\Console\Commands;

use App\Models\ShopifySku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchShopifyProductViews extends Command
{
    protected $signature = 'app:fetch-shopify-product-views
                            {--days=30 : Lookback window in days (L30 default)}
                            {--dry-run : Fetch and report match counts without writing}';

    protected $description = 'Fetch Shopify product page views (L30 sessions) via ShopifyQL and store on shopify_skus.views';

    public function handle(): int
    {
        $storeUrl = config('services.shopify.store_url');
        $accessToken = config('services.shopify.access_token');
        $apiVersion = config('services.shopify.api_version', '2025-01');
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        if (empty($storeUrl) || empty($accessToken)) {
            $this->error('Missing Shopify API credentials (SHOPIFY_STORE_URL / SHOPIFY_ACCESS_TOKEN)');

            return 1;
        }

        $this->info("Fetching product landing-page sessions for last {$days} day(s)...");

        try {
            $viewsByPath = $this->fetchProductLandingSessions($storeUrl, $accessToken, $apiVersion, $days);
        } catch (\Throwable $e) {
            Log::error('FetchShopifyProductViews: ShopifyQL failed', [
                'error' => $e->getMessage(),
            ]);
            $this->error('ShopifyQL fetch failed: '.$e->getMessage());

            return 1;
        }

        $this->info('Product landing paths returned: '.count($viewsByPath));

        $pathToSkuIds = $this->buildPathToSkuIdsMap();
        $updated = 0;
        $matchedPaths = 0;
        $zeroed = 0;
        $skippedNoLink = 0;

        $skuUpdates = []; // id => views

        foreach ($pathToSkuIds as $path => $skuIds) {
            if (isset($viewsByPath[$path])) {
                $matchedPaths++;
                $views = $viewsByPath[$path];
                foreach ($skuIds as $id) {
                    $skuUpdates[$id] = $views;
                }
            } else {
                foreach ($skuIds as $id) {
                    $skuUpdates[$id] = 0;
                    $zeroed++;
                }
            }
        }

        $skippedNoLink = ShopifySku::query()
            ->where(function ($q) {
                $q->whereNull('product_link')->orWhere('product_link', '');
            })
            ->count();

        if ($dryRun) {
            $this->warn('Dry run — no DB writes');
            $this->line('Paths with traffic matched to SKUs: '.$matchedPaths);
            $this->line('SKU rows that would update: '.count($skuUpdates));
            $this->line('Of those, would set to 0 (linked, no L'.$days.' traffic): '.$zeroed);
            $this->line('SKUs skipped (no product_link): '.$skippedNoLink);

            $sample = collect($skuUpdates)->sortDesc()->take(10);
            foreach ($sample as $id => $views) {
                $sku = ShopifySku::find($id);
                $this->line(sprintf('  %s => %d views', $sku?->sku ?? "#{$id}", $views));
            }

            return 0;
        }

        foreach ($skuUpdates as $id => $views) {
            ShopifySku::where('id', $id)->update(['views' => $views]);
            $updated++;
        }

        Log::info('FetchShopifyProductViews completed', [
            'days' => $days,
            'paths' => count($viewsByPath),
            'matched_paths' => $matchedPaths,
            'updated' => $updated,
            'zeroed' => $zeroed,
            'skipped_no_link' => $skippedNoLink,
        ]);

        $this->info("✅ Updated {$updated} shopify_skus rows ({$matchedPaths} paths with traffic, {$zeroed} linked SKUs zeroed)");
        $this->line("Skipped (no product_link): {$skippedNoLink}");

        return 0;
    }

    /**
     * @return array<string, int> normalized /products/... path => sessions
     */
    private function fetchProductLandingSessions(string $storeUrl, string $accessToken, string $apiVersion, int $days): array
    {
        $query = "FROM sessions SHOW sessions, pageviews"
            ." WHERE landing_page_type = 'Product'"
            ." SINCE -{$days}d"
            .' GROUP BY landing_page_path'
            .' ORDER BY sessions DESC'
            .' LIMIT 5000';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post(
            "https://{$storeUrl}/admin/api/{$apiVersion}/graphql.json",
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
            throw new \RuntimeException('ShopifyQL parse errors: '.implode('; ', $parseErrors));
        }

        if (! empty($payload['errors'])) {
            $msgs = array_map(fn ($e) => $e['message'] ?? json_encode($e), $payload['errors']);
            throw new \RuntimeException('GraphQL errors: '.implode('; ', $msgs));
        }

        $rows = $payload['data']['shopifyqlQuery']['tableData']['rows'] ?? [];
        $byPath = [];

        foreach ($rows as $row) {
            $path = $this->normalizeProductPath($row['landing_page_path'] ?? '');
            if ($path === '') {
                continue;
            }
            // Prefer sessions (unique product view sessions) — matches marketplace CVR "Views"/sessions.
            $sessions = (int) ($row['sessions'] ?? 0);
            $byPath[$path] = ($byPath[$path] ?? 0) + $sessions;
        }

        return $byPath;
    }

    /**
     * @return array<string, array<int, int>> path => [shopify_skus.id, ...]
     */
    private function buildPathToSkuIdsMap(): array
    {
        $map = [];

        ShopifySku::query()
            ->whereNotNull('product_link')
            ->where('product_link', '!=', '')
            ->select('id', 'product_link')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$map) {
                foreach ($rows as $row) {
                    $path = $this->normalizeProductPath($row->product_link);
                    if ($path === '') {
                        continue;
                    }
                    $map[$path][] = (int) $row->id;
                }
            });

        return $map;
    }

    private function normalizeProductPath(?string $urlOrPath): string
    {
        if ($urlOrPath === null || trim($urlOrPath) === '') {
            return '';
        }

        $raw = trim($urlOrPath);
        $path = parse_url($raw, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            // Already a path like /products/foo
            $path = str_starts_with($raw, '/') ? $raw : '/'.$raw;
        }

        $path = strtolower(rtrim($path, '/'));
        if (! str_contains($path, '/products/')) {
            return '';
        }

        // Keep only /products/{handle} (drop locale prefixes like /en-us/products/...)
        if (preg_match('#(/products/[^/]+)#', $path, $m)) {
            return $m[1];
        }

        return '';
    }
}
