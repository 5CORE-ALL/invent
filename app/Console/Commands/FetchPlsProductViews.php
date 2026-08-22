<?php

namespace App\Console\Commands;

use App\Models\PLSProduct;
use App\Models\ShopifySku;
use App\Services\ShopifyPlsTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FetchPlsProductViews extends Command
{
    protected $signature = 'app:fetch-pls-product-views
                            {--days=30 : Lookback window in days (L30 default)}
                            {--dry-run : Fetch and report match counts without writing}';

    protected $description = 'Fetch PLS Shopify product page views (L30 sessions) via ShopifyQL into pls_products.views';

    public function handle(ShopifyPlsTokenService $tokens): int
    {
        $domain = $tokens->getDomain();
        $accessToken = $tokens->getAccessToken();
        $apiVersion = (string) config('services.shopify.api_version', '2025-01');
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        if (! $domain || ! $accessToken) {
            $this->error('PLS Shopify credentials missing (services.prolightsounds).');

            return self::FAILURE;
        }

        if (! Schema::hasTable('pls_products') || ! Schema::hasColumn('pls_products', 'views')) {
            $this->error('pls_products.views missing — run migrations.');

            return self::FAILURE;
        }

        $this->info("Fetching PLS product landing-page sessions for last {$days} day(s) on {$domain}...");

        try {
            $viewsByPath = $this->fetchProductLandingSessions($domain, $accessToken, $apiVersion, $days);
        } catch (\Throwable $e) {
            Log::error('FetchPlsProductViews: ShopifyQL failed', ['error' => $e->getMessage()]);
            $this->error('ShopifyQL fetch failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Product landing paths returned: '.count($viewsByPath));

        $pathToSkus = $this->buildPathToSkusMap();
        $skuViews = [];
        $matchedPaths = 0;

        foreach ($pathToSkus as $path => $skus) {
            $views = $viewsByPath[$path] ?? 0;
            if (isset($viewsByPath[$path])) {
                $matchedPaths++;
            }
            foreach ($skus as $sku) {
                $skuViews[$sku] = ($skuViews[$sku] ?? 0) + $views;
            }
        }

        if ($dryRun) {
            $this->warn('Dry run — no DB writes');
            $this->line('Paths with traffic matched to SKUs: '.$matchedPaths);
            $this->line('SKUs that would update: '.count($skuViews));
            foreach (collect($skuViews)->sortDesc()->take(10) as $sku => $views) {
                $this->line(sprintf('  %s => %d views', $sku, $views));
            }

            return self::SUCCESS;
        }

        $existingByNorm = [];
        foreach (PLSProduct::query()->get(['id', 'sku']) as $row) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
            if ($key !== '' && ! isset($existingByNorm[$key])) {
                $existingByNorm[$key] = $row;
            }
        }

        $updated = 0;
        $skipped = 0;
        foreach ($skuViews as $sku => $views) {
            $row = $existingByNorm[$sku] ?? null;
            if (! $row) {
                $skipped++;
                continue;
            }
            $row->views = (int) $views;
            $row->save();
            $updated++;
        }

        Log::info('FetchPlsProductViews completed', [
            'days' => $days,
            'paths' => count($viewsByPath),
            'matched_paths' => $matchedPaths,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        $this->info("Updated {$updated} pls_products rows ({$matchedPaths} paths with traffic, {$skipped} catalog SKUs not in pls_products).");

        return self::SUCCESS;
    }

    /**
     * @return array<string, int> /products/{handle} => sessions
     */
    private function fetchProductLandingSessions(string $domain, string $accessToken, string $apiVersion, int $days): array
    {
        $query = 'FROM sessions SHOW sessions, pageviews'
            ." WHERE landing_page_type = 'Product'"
            ." SINCE -{$days}d"
            .' GROUP BY landing_page_path'
            .' ORDER BY sessions DESC'
            .' LIMIT 5000';

        $request = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(120);

        if (config('filesystems.default') === 'local' || env('FILESYSTEM_DRIVER') === 'local') {
            $request = $request->withoutVerifying();
        }

        $response = $request->post(
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

        $rows = $payload['data']['shopifyqlQuery']['tableData']['rows'] ?? [];
        $byPath = [];

        foreach ($rows as $row) {
            $path = $this->normalizeProductPath($row['landing_page_path'] ?? '');
            if ($path === '') {
                continue;
            }
            $sessions = (int) ($row['sessions'] ?? 0);
            $byPath[$path] = ($byPath[$path] ?? 0) + $sessions;
        }

        return $byPath;
    }

    /**
     * @return array<string, list<string>> path => [sku, ...]
     */
    private function buildPathToSkusMap(): array
    {
        if (! Schema::hasTable('shopify_catalog_products') || ! Schema::hasTable('shopify_catalog_variants')) {
            return [];
        }

        $rows = DB::table('shopify_catalog_variants as v')
            ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
            ->where('v.store', 'pls')
            ->where('p.store', 'pls')
            ->whereNotNull('p.handle')
            ->where('p.handle', '!=', '')
            ->whereNotNull('v.sku')
            ->where('v.sku', '!=', '')
            ->select('p.handle', 'v.sku')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $path = $this->normalizeProductPath('/products/'.ltrim((string) $row->handle, '/'));
            $sku = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
            if ($path === '' || $sku === '') {
                continue;
            }
            $map[$path][] = $sku;
        }

        foreach ($map as $path => $skus) {
            $map[$path] = array_values(array_unique($skus));
        }

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
            $path = str_starts_with($raw, '/') ? $raw : '/'.$raw;
        }

        $path = strtolower(rtrim($path, '/'));
        if (! str_contains($path, '/products/')) {
            return '';
        }

        if (preg_match('#(/products/[^/]+)#', $path, $m)) {
            return $m[1];
        }

        return '';
    }
}
