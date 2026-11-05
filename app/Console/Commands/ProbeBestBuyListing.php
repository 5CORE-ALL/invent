<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * TEMPORARY, read-only: shows what Best Buy's Mirakl MCM API returns for a live listing,
 * so the LQS scorer can be built on the real field names. Writes nothing. Safe to delete afterwards.
 */
class ProbeBestBuyListing extends Command
{
    protected $signature = 'bestbuy:probe-listing {sku? : shop_sku or product_sku (default: first live offer)}';

    protected $description = 'Read-only probe of Best Buy MCM offers + product data (for LQS scorer design).';

    public function handle(): int
    {
        $key = trim((string) config('services.bestbuy.mcm_api_key', ''));
        $key = preg_replace('/^(authorization:\s*|bearer\s+)/i', '', $key) ?? $key;
        $base = rtrim((string) config('services.bestbuy.mcm_base_url', ''), '/');
        $shopId = config('services.bestbuy.shop_id');

        if ($key === '' || $base === '') {
            $this->error('BESTBUY_MCM_API_KEY / BESTBUY_MCM_BASE_URL are not set on this server.');

            return self::FAILURE;
        }
        $this->line('base='.$base.' shop_id='.($shopId ?: 'n/a').' (key length '.strlen($key).')');

        $http = fn () => Http::withoutVerifying()->withHeaders(['Authorization' => $key, 'Accept' => 'application/json'])->timeout(45);
        $shopParam = ($shopId !== null && $shopId !== '') ? ['shop_id' => (int) $shopId] : [];

        // 1) OF21 offers: finds a real live shop_sku / product_sku.
        $this->info('1) GET /api/offers (OF21)');
        $r = $http()->get($base.'/api/offers', $shopParam + ['max' => 3]);
        $this->line('   HTTP '.$r->status());
        $offers = (array) ($r->json('offers') ?? []);
        $first = is_array($offers[0] ?? null) ? $offers[0] : [];
        $this->line('   offer keys: '.json_encode(array_keys($first)));
        $this->line('   first offer: '.mb_substr(json_encode($first), 0, 1800));
        if (! $r->successful()) {
            $this->line('   body: '.mb_substr($r->body(), 0, 400));
        }

        $arg = trim((string) $this->argument('sku'));
        $shopSku = $arg !== '' ? $arg : (string) ($first['shop_sku'] ?? '');
        $productSku = (string) ($first['product_sku'] ?? '');
        $this->line("   using shop_sku='{$shopSku}' product_sku='{$productSku}'");

        // 2) P31 product read, by product_sku then shop_sku.
        $tries = [];
        if ($productSku !== '') {
            $tries[] = 'product_sku|'.$productSku;
        }
        if ($shopSku !== '') {
            $tries[] = 'shop_sku|'.$shopSku;
        }
        foreach ($tries as $ref) {
            $this->info('2) GET /api/products product_references='.$ref);
            $p = $http()->get($base.'/api/products', $shopParam + [
                'product_references' => $ref,
                'max' => 1,
                'all_operator_attributes' => 'true',
            ]);
            $this->line('   HTTP '.$p->status());
            $body = $p->json() ?? [];
            $products = $body['products'] ?? $body['data'] ?? [];
            $prod = is_array($products[0] ?? null) ? $products[0] : [];
            $this->line('   top-level keys: '.json_encode(array_keys((array) $body)));
            $this->line('   product keys: '.json_encode(array_keys($prod)));
            $this->line('   product: '.mb_substr(json_encode($prod), 0, 4500));
            if (! $p->successful()) {
                $this->line('   body: '.mb_substr($p->body(), 0, 400));
            }
            if ($prod !== []) {
                break;
            }
        }

        return self::SUCCESS;
    }
}
