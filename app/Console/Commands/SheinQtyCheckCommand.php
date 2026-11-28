<?php

namespace App\Console\Commands;

use App\Models\SheinListingStatus;

use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;


use App\Services\MarketplaceManager\SheinInventorySyncService;
use App\Services\SheinApiService;
use App\Services\ShopifyApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Why does an "Inv SKU Mismatch" SKU not clear on /marketplace/shein/products?link=mismatch?
 * Shows, per SKU: live Shopify qty, the Shein skuCode, what Shein reports (full-detail + stock-query), and with --push
 * sends the Shopify qty to Shein and reads it back, printing Shein's raw answer.
 *
 *   php artisan shein:qty-check "SC 026"
 *   php artisan shein:qty-check "SC 026" --push
 *   php artisan shein:qty-check --mismatch --limit=3 --push
 */
class SheinQtyCheckCommand extends Command
{
    protected $signature = 'shein:qty-check {sku?* : Seller SKU(s)} {--mismatch : Use the first SKUs listed under Inv SKU Mismatch instead} {--limit=3 : How many mismatch SKUs with --mismatch} {--push : Push the live Shopify qty to Shein, then read it back}';

    protected $description = 'Compare live Shopify qty with what Shein holds for a SKU; optionally push and read back';

    public function handle(SheinApiService $api, ShopifyApiService $shopify, SheinInventorySyncService $sync): int
    {
        $skus = array_values(array_filter(array_map('trim', (array) $this->argument('sku'))));
        if ($this->option('mismatch')) {
            $skus = array_slice(app(MarketplaceMismatchInventoryPass::class)->pageMismatchSkus('shein'), 0, max(1, (int) $this->option('limit')));
            $this->line('Mismatch SKUs picked: '.json_encode($skus));
        }
        if ($skus === []) {
            $this->warn('Give a SKU, or use --mismatch.');

            return self::FAILURE;
        }

        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            $this->info('=== '.$sku.' ===');

            try {
                // Same source the push uses: live Admin API merged with the listings page's Shopify qty.
                $live = MarketplaceLiveInventoryRules::applyListingsShopifyQtyForPush([], [$sku], true);
            } catch (\Throwable $e) {
                $live = ['error' => $e->getMessage()];
            }
            $this->line('Shopify qty (as pushed): '.json_encode($live));

            $resolved = $api->resolvePlatformSkuCodesForSellerSkus([$sku], SheinListingStatus::spuNamesForSellerSkus([$sku]), true);
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $hit = ($norm !== '' && isset($resolved[$norm])) ? $resolved[$norm] : ($resolved[$sku] ?? null);
            $this->line('Shein skuCode lookup: '.json_encode($hit));
            $code = trim((string) ($hit['sku_code'] ?? ''));
            if ($code === '') {
                $this->warn('No skuCode: Shein cannot be pushed for this SKU. Shein probe:');
                $this->line(json_encode($api->probeSku($sku), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                continue;
            }

            $this->showShein($api, $code, 'BEFORE');

            if ($this->option('push')) {
                $result = $sync->syncSkusFromShopify([$sku], null, true);
                $this->line('Push result: '.json_encode($result));
                sleep(3);
                $this->showShein($api, $code, 'AFTER');
                $read = app(MarketplaceQtyReadBack::class)->read('shein', [$sku]);
                $this->line('Read-back qty: '.json_encode($read));
            }
        }

        return self::SUCCESS;
    }

    private function showShein(SheinApiService $api, string $code, string $label): void
    {
        try {
            $rows = $api->getStock([$code]);
            $this->line($label.' full-detail (app qty): '.json_encode($rows, JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            $this->warn($label.' full-detail failed: '.$e->getMessage());
        }

        $endpoint = '/open-api/stock/stock-query';
        try {
            $response = Http::withoutVerifying()->timeout(90)
                ->withHeaders($this->callProtected($api, 'buildSheinAuthHeaders', [$endpoint]))
                ->post($this->callProtected($api, null, [], 'baseUrl').$endpoint, [
                    'languageList' => ['en'],
                    'skuCodeList' => [$code],
                    'skcNameList' => [],
                    'spuNameList' => [],
                    'warehouseType' => '3',
                ]);
            $this->line($label.' stock-query (raw, warehouse/locked/available): '.mb_substr((string) $response->body(), 0, 2500));
        } catch (\Throwable $e) {
            $this->warn($label.' stock-query failed: '.$e->getMessage());
        }
    }

    /**
     * Read-only access to the service's protected auth headers / base URL (keeps this diagnostic out of SheinApiService).
     */
    private function callProtected(object $obj, ?string $method, array $args = [], ?string $property = null): mixed
    {
        $ref = new \ReflectionClass($obj);
        if ($property !== null) {
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);

            return $prop->getValue($obj);
        }
        $m = $ref->getMethod((string) $method);
        $m->setAccessible(true);

        return $m->invokeArgs($obj, $args);
    }
}
