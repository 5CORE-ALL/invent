<?php

namespace App\Console\Commands;

use App\Models\SheinListingStatus;
use App\Models\SheinMmMetric;
use App\Models\ShopifySku;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use App\Services\MarketplaceManager\MarketplaceMismatchInventoryPass;
use App\Services\MarketplaceManager\MarketplaceQtyReadBack;
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
    protected $signature = 'shein:qty-check {sku?* : Seller SKU(s)} {--mismatch : Use the first SKUs listed under Inv SKU Mismatch instead} {--limit=3 : How many mismatch SKUs with --mismatch} {--live : Use SKUs that are in the Shein product list (known-good listings)} {--summary : Count how many linked Shein SKUs are really on Shein (read-only)} {--push : Push the live Shopify qty to Shein, then read it back}';

    protected $description = 'Compare live Shopify qty with what Shein holds for a SKU; optionally push and read back';

    public function handle(SheinApiService $api, ShopifyApiService $shopify, SheinInventorySyncService $sync): int
    {
        if ($this->option('summary')) {
            return $this->summary($api);
        }

        $skus = array_values(array_filter(array_map('trim', (array) $this->argument('sku'))));
        if ($this->option('mismatch')) {
            $skus = array_slice(app(MarketplaceMismatchInventoryPass::class)->pageMismatchSkus('shein'), 0, max(1, (int) $this->option('limit')));
            $this->line('Mismatch SKUs picked: '.json_encode($skus));
        }
        if ($this->option('live')) {
            $inList = [];
            foreach ($api->skcSkuCodeCatalog() as $list) {
                foreach ($list as $c) {
                    $inList[strtoupper($c)] = true;
                }
            }
            $skus = SheinMmMetric::query()->whereNotNull('sku')->where('sku', '!=', '')->get(['sku', 'product_id'])
                ->filter(fn ($r) => isset($inList[strtoupper(trim((string) $r->product_id))]))
                ->pluck('sku')->map(fn ($v) => trim((string) $v))->take(max(1, (int) $this->option('limit')))->values()->all();
            $this->line('Live SKUs picked: '.json_encode($skus));
        }
        if ($skus === []) {
            $this->warn('Give a SKU, or use --mismatch / --live.');

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
                $this->warn('No skuCode: Shein cannot be pushed for this SKU.');
                $catalog = $api->skcSkuCodeCatalog();
                $this->line('Shein product list holds '.count($catalog).' SKC(s). (0 = the product list call failed.)');
                $this->line('Local SKC/code saved for this SKU: '.json_encode($api->probeSku($sku)['local'] ?? []));

                continue;
            }

            $this->showShein($api, $code, 'BEFORE');

            if ($this->option('push')) {
                $result = $sync->syncSkusFromShopify([$sku], null, true);
                $this->line('Push result: '.json_encode($result));
                sleep(6);
                $this->showShein($api, $code, 'AFTER');
                $read = app(MarketplaceQtyReadBack::class)->read('shein', [$sku]);
                $this->line('Read-back qty: '.json_encode($read));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Compare every linked Shein listing with Shein's own product list, so we can tell live listings from dead ones.
     */
    private function summary(SheinApiService $api): int
    {
        $catalog = $api->skcSkuCodeCatalog(true);
        $skcs = $catalog;
        $codes = [];
        foreach ($catalog as $list) {
            foreach ($list as $c) {
                $codes[strtoupper($c)] = true;
            }
        }
        $this->line('Shein product list: '.count($skcs).' SKC(s), '.count($codes).' skuCode(s).');

        $stat = ['code_in_list' => 0, 'code_not_in_list' => 0, 'skc_in_list' => 0, 'skc_not_in_list' => 0, 'empty' => 0];
        $deadExamples = [];
        $mismatch = array_flip(array_map('strtoupper', app(MarketplaceMismatchInventoryPass::class)->pageMismatchSkus('shein')));
        $mismatchStat = ['live' => 0, 'not_on_shein' => 0];
        foreach (SheinMmMetric::query()->whereNotNull('sku')->where('sku', '!=', '')->get(['sku', 'product_id']) as $row) {
            $pid = trim((string) $row->product_id);
            $isMismatch = isset($mismatch[strtoupper(trim((string) $row->sku))]);
            if ($pid === '' || strcasecmp($pid, trim((string) $row->sku)) === 0) {
                $stat['empty']++;

                continue;
            }
            if (preg_match('/^(?!i)[a-z]{1,2}\d{10,}$/i', $pid)) {
                $in = isset($skcs[strtoupper($pid)]);
                $stat[$in ? 'skc_in_list' : 'skc_not_in_list']++;
            } else {
                $in = isset($codes[strtoupper($pid)]);
                $stat[$in ? 'code_in_list' : 'code_not_in_list']++;
            }
            if ($isMismatch) {
                $mismatchStat[$in ? 'live' : 'not_on_shein']++;
            }
            if (! $in && count($deadExamples) < 8) {
                $deadExamples[] = $row->sku.' => '.$pid;
            }
        }
        $this->line('Saved Shein id per listing vs the Shein list: '.json_encode($stat));
        $this->line('Inv SKU Mismatch SKUs: '.count($mismatch).' listed on the page; of those found in shein_metric: '.json_encode($mismatchStat));
        $this->line('Examples not on Shein: '.json_encode($deadExamples));

        return self::SUCCESS;
    }
    private function showShein(SheinApiService $api, string $code, string $label): void
    {
        $endpoint = '/open-api/stock/stock-query';
        // warehouseType 1/2/3 are different warehouse kinds; the push goes to one seller warehouse, so look at all of them.
        foreach (['1', '2', '3'] as $type) {
            try {
                $response = Http::withoutVerifying()->timeout(90)
                    ->withHeaders($this->callProtected($api, 'buildSheinAuthHeaders', [$endpoint]))
                    ->post($this->callProtected($api, null, [], 'baseUrl').$endpoint, [
                        'languageList' => ['en'],
                        'skuCodeList' => [$code],
                        'skcNameList' => [],
                        'spuNameList' => [],
                        'warehouseType' => $type,
                    ]);
                $json = $response->json();
                $lines = [];
                foreach ((array) ($json['info'] ?? []) as $info) {
                    foreach ((array) ($info['goodsInventory'] ?? []) as $goods) {
                        foreach ((array) ($goods['skuList'] ?? []) as $sku) {
                            foreach ((array) ($sku['warehouseInventoryList'] ?? []) as $wh) {
                                $lines[] = 'wh='.($wh['warehouseCode'] ?? '?')
                                    .' qty='.($wh['inventoryQuantity'] ?? '?')
                                    .' locked='.($wh['lockedQuantity'] ?? '?')
                                    .' usable='.($wh['usableInventory'] ?? '?')
                                    .' spu='.($goods['spuName'] ?? '?');
                            }
                        }
                    }
                }
                $this->line($label.' stock-query type '.$type.': '.($lines === [] ? 'no rows ('.mb_substr((string) $response->body(), 0, 120).')' : implode(' | ', $lines)));
            } catch (\Throwable $e) {
                $this->warn($label.' stock-query type '.$type.' failed: '.$e->getMessage());
            }
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
