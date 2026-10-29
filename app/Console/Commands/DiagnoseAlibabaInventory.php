<?php

namespace App\Console\Commands;

use App\Models\AlibabaMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\AlibabaApiService;
use App\Services\MarketplaceManager\AlibabaInventorySyncService;
use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shows whether Shopify -> Alibaba inventory sync is working: settings, token, mismatch list,
 * queue, recent errors, and one live read of an Alibaba product. Read-only unless --push=SKU is given.
 */
class DiagnoseAlibabaInventory extends Command
{
    protected $signature = 'alibaba:diagnose-inventory
        {--sku= : SKU to probe on Alibaba (default: first Inv SKU Mismatch row)}
        {--title= : Find SKUs by part of the product title (e.g. "Megaphone 30W") and probe the first match}
        {--push= : Really push this one SKU from Shopify to Alibaba and print the exact result}';

    protected $description = 'Check whether Alibaba inventory is syncing from Shopify (read-only unless --push=SKU)';

    public function handle(): int
    {
        $this->line('== 1. Settings');
        $settings = MarketplaceSyncSettings::getFor('alibaba');
        $invOn = (bool) ($settings['inventory']['inventory_sync'] ?? false);
        $this->line('  inventory_sync     : '.($invOn ? 'ON' : 'OFF  <-- the 4-hourly full sync and the mismatch job are skipped when both inventory and price sync are off'));
        $this->line('  price_sync         : '.(($settings['pricing']['price_sync'] ?? false) ? 'ON' : 'off'));
        $this->line('  quantity percent   : '.($settings['inventory']['quantity_calc_percent'] ?? 100));
        $this->line('  quantity offset    : '.($settings['inventory']['quantity_offset'] ?? 0));
        $this->line('  max quantity       : '.($settings['inventory']['max_quantity'] ?? '-'));

        $this->line('');
        $this->line('== 2. Alibaba credentials');
        $api = app(AlibabaApiService::class);
        $token = (string) $api->getAccessToken();
        $this->line('  access token       : '.($token !== '' ? 'present ('.strlen($token).' chars)' : 'MISSING  <-- set ALIBABA_ACCESS_TOKEN'));
        $this->line('  app key / secret   : '.(filled(config('services.alibaba.app_key')) ? 'key ok' : 'KEY MISSING').' / '.(filled(config('services.alibaba.app_secret')) ? 'secret ok' : 'SECRET MISSING'));

        $this->line('');
        $this->line('== 3. Mappings and mismatch');
        $linked = 0;
        if (Schema::hasTable('alibaba_metrics')) {
            $linked = AlibabaMetric::query()
                ->whereNotNull('sku')->whereNotNull('product_id')
                ->where('sku', '!=', '')->whereColumn('sku', '!=', 'product_id')->count();
        }
        $this->line("  linked SKUs (alibaba_metrics): {$linked}");
        $mismatch = [];
        try {
            $mismatch = app(MarketplaceListingQtyMatchService::class)->activeMismatchSkus('alibaba', false);
        } catch (\Throwable $e) {
            $this->warn('  mismatch list failed: '.$e->getMessage());
        }
        $this->line('  Inv SKU Mismatch SKUs        : '.count($mismatch));

        $probeSku = trim((string) $this->option('sku'));
        $title = trim((string) $this->option('title'));
        if ($probeSku === '' && $title !== '' && Schema::hasTable('shopify_skus')) {
            $found = DB::table('shopify_skus')
                ->where(function ($q) use ($title) {
                    $q->where('product_title', 'like', '%'.$title.'%')->orWhere('variant_title', 'like', '%'.$title.'%');
                })
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->orderBy('sku')->limit(15)->get(['sku', 'product_title', 'variant_title']);
            $this->line('  SKUs matching title "'.$title.'": '.$found->count());
            foreach ($found as $f) {
                $linkedRow = Schema::hasTable('alibaba_metrics') ? AlibabaMetric::query()->where('sku', $f->sku)->whereNotNull('product_id')->first() : null;
                $this->line(sprintf('    %-28s %-10s %s', $f->sku, $linkedRow ? 'linked' : 'NOT linked', mb_substr(trim($f->product_title.' '.$f->variant_title), 0, 60)));
                if ($probeSku === '' && $linkedRow) {
                    $probeSku = (string) $f->sku;
                }
            }
        }
        if ($probeSku === '') {
            $probeSku = (string) ($mismatch[0] ?? '');
        }
        if ($probeSku !== '') {
            $this->showQuantities($probeSku);
            $this->showClassification($probeSku);
        }
        foreach (array_slice($mismatch, 0, 8) as $sku) {
            $map = Schema::hasTable('product_stock_mappings')
                ? DB::table('product_stock_mappings')->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first(['inventory_shopify', 'inventory_alibaba'])
                : null;
            $metric = Schema::hasTable('alibaba_metrics') ? AlibabaMetric::query()->where('sku', $sku)->first() : null;
            $this->line(sprintf(
                '    %-26s product_id %-18s shopify %-6s alibaba %-6s',
                $sku,
                (string) ($metric->product_id ?? '-'),
                (string) ($map->inventory_shopify ?? '?'),
                (string) ($map->inventory_alibaba ?? '?')
            ));
        }
        if (Schema::hasTable('alibaba_pricing_prices') && Schema::hasColumn('alibaba_pricing_prices', 'ab_stock')) {
            $last = DB::table('alibaba_pricing_prices')->whereNotNull('ab_stock')->max('updated_at');
            $this->line('  newest stock write we recorded after a push: '.($last ?: '-'));
        }

        $this->line('');
        $this->line('== 4. Queue and retries');
        try {
            $queue = MarketplaceManagerRegistry::queueFor('alibaba');
            $waiting = DB::table('jobs')->where('queue', $queue)->where('available_at', '<=', time())->count();
            $delayed = DB::table('jobs')->where('queue', $queue)->where('available_at', '>', time())->count();
            $oldest = DB::table('jobs')->where('queue', $queue)->min('created_at');
            $this->line("  queue {$queue}: {$waiting} ready, {$delayed} delayed".($oldest ? ', oldest queued '.Carbon::createFromTimestamp((int) $oldest)->diffForHumans() : ''));
            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->where('queue', $queue)->where('failed_at', '>=', now()->subDays(3))->count();
                $this->line("  failed jobs on {$queue} (last 3 days): {$failed}");
                $last = DB::table('failed_jobs')->where('queue', $queue)->orderByDesc('id')->first();
                if ($last) {
                    $this->line('  last failure: '.$last->failed_at.' '.mb_substr(strtok((string) $last->exception, "\n"), 0, 240));
                }
            }
        } catch (\Throwable $e) {
            $this->warn('  queue check failed: '.$e->getMessage());
        }
        $attempts = Cache::get('mm.sku_push.attempts.alibaba', []);
        $pending = Cache::get('mm.sku_push.pending.alibaba', []);
        $this->line('  SKUs waiting in the push set : '.(is_array($pending) ? count($pending) : 0));
        $this->line('  SKUs in retry backoff         : '.(is_array($attempts) ? count($attempts) : 0).(is_array($attempts) && $attempts !== [] ? '  e.g. '.implode(', ', array_slice(array_keys($attempts), 0, 5)) : ''));

        $this->line('');
        $this->line('== 5. Live read of one Alibaba product (read only)');
        $this->probe($api, $probeSku);

        $this->line('');
        $this->line('== 6. Recent Alibaba inventory lines in the log');
        $this->tailLog();

        $pushSku = trim((string) $this->option('push'));
        if ($pushSku !== '') {
            $this->line('');
            $this->line("== 7. Real push of {$pushSku}");
            $res = app(AlibabaInventorySyncService::class)->syncSkusFromShopify([$pushSku], null, true);
            $this->line('  '.json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    private function showClassification(string $sku): void
    {
        $this->line("  how the sync classifies {$sku}:");
        try {
            $svc = app(MarketplaceListingQtyMatchService::class);
            Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.'alibaba');
            $c = $svc->classify('alibaba');
            $norm = strtoupper(\App\Models\ShopifySku::normalizeSkuForShopifyLookup($sku) ?: $sku);
            $bucket = 'none';
            foreach (['matched', 'mismatch', 'linked_mismatch', 'zero'] as $b) {
                foreach ($c[$b] ?? [] as $s) {
                    if (strtoupper(trim((string) $s)) === strtoupper(trim($sku)) || strtoupper(\App\Models\ShopifySku::normalizeSkuForShopifyLookup((string) $s) ?: (string) $s) === $norm) {
                        $bucket = $b;
                        break 2;
                    }
                }
            }
            $this->line(sprintf(
                '    classify() totals            : matched %d / mismatch %d / linked_mismatch %d / zero %d',
                count($c['matched'] ?? []), count($c['mismatch'] ?? []), count($c['linked_mismatch'] ?? []), count($c['zero'] ?? [])
            ));
            $this->line("    this SKU is in bucket        : {$bucket}");
            $shopify = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus([$sku]);
            $shopifyQty = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::qtyFromMap($shopify, $sku);
            $catalog = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus([$sku]);
            $this->line('    catalog Shopify qty          : '.var_export(\App\Services\MarketplaceManager\MarketplaceListingStockResolver::qtyFromMap($catalog, $sku), true));
            $mp = app(\App\Services\MarketplaceManager\MarketplaceMismatchInventoryPass::class)->stockMap('alibaba', [$sku]);
            $mpQty = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::qtyFromMap($mp, $sku);
            $this->line('    sync-side Alibaba qty        : '.var_export($mpQty, true));
            if ($shopifyQty !== null) {
                $within = \App\Services\MarketplaceManager\MarketplaceLiveInventoryRules::qtyWithinMismatchTolerance((int) $shopifyQty, $mpQty, 'alibaba');
                $this->line('    within tolerance (no push)   : '.($within ? 'YES' : 'no  -> should be pushed'));
            }
            $active = $svc->activeMismatchSkus('alibaba', false);
            $this->line('    in activeMismatchSkus()      : '.(in_array($sku, $active, true) ? 'yes' : 'NO'));
        } catch (\Throwable $e) {
            $this->warn('    classification check failed: '.$e->getMessage());
        }
    }

    private function showQuantities(string $sku): void
    {
        $upper = strtoupper(trim($sku));
        $this->line("  quantities for {$sku}:");
        $shopify = \App\Services\MarketplaceManager\MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus([$sku]);
        $this->line('    live Shopify qty              : '.var_export(\App\Services\MarketplaceManager\MarketplaceListingStockResolver::qtyFromMap($shopify, $sku), true));
        if (Schema::hasTable('product_stock_mappings')) {
            $m = DB::table('product_stock_mappings')->whereRaw('UPPER(TRIM(sku)) = ?', [$upper])->first(['inventory_shopify', 'inventory_alibaba']);
            $this->line('    product_stock_mappings        : shopify '.($m->inventory_shopify ?? '?').' / alibaba '.($m->inventory_alibaba ?? '?'));
        }
        if (Schema::hasTable('alibaba_pricing_prices') && Schema::hasColumn('alibaba_pricing_prices', 'ab_stock')) {
            $p = DB::table('alibaba_pricing_prices')->whereRaw('UPPER(TRIM(sku)) = ?', [$upper])->first(['ab_stock', 'updated_at']);
            $this->line('    alibaba_pricing_prices.ab_stock: '.($p ? var_export($p->ab_stock, true).' (updated '.$p->updated_at.')' : 'no row').'   <-- this is what the listings page shows as Alibaba Qty');
        }
    }

    private function probe(AlibabaApiService $api, string $sku): void
    {
        if ($sku === '') {
            $this->line('  no SKU to probe (pass --sku=)');

            return;
        }
        $metric = Schema::hasTable('alibaba_metrics') ? AlibabaMetric::query()->where('sku', $sku)->whereNotNull('product_id')->first() : null;
        if (! $metric) {
            $this->line("  {$sku}: no Alibaba product id on file (link map missing)");

            return;
        }
        try {
            $info = $api->getProductInfo((string) $metric->product_id);
        } catch (\Throwable $e) {
            $this->error('  exception: '.$e->getMessage());

            return;
        }
        $ok = ! empty($info['success']);
        $this->line("  {$sku} (product {$metric->product_id}): ".($ok ? 'Alibaba answered' : 'FAILED'));
        if (! $ok) {
            $this->line('  message: '.mb_substr((string) ($info['message'] ?? json_encode($info)), 0, 400));
            $this->warn('  An auth/token message here means every push fails until ALIBABA_ACCESS_TOKEN is renewed.');

            return;
        }
        $data = is_array($info['data'] ?? null) ? $info['data'] : [];
        $keys = array_diff(array_keys($data), ['code', 'request_id', '_trace_id_']);
        if ($keys === []) {
            $this->warn('  Alibaba has no such product (deleted or id changed). Run Sync Alibaba link map.');
        } else {
            $this->line('  product exists on Alibaba (fields: '.implode(', ', array_slice($keys, 0, 8)).')');
        }
    }

    private function tailLog(): void
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        if ($files === []) {
            $this->line('  no log file');

            return;
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $file = $files[0];
        $size = filesize($file);
        $fh = fopen($file, 'rb');
        if (! $fh) {
            return;
        }
        fseek($fh, max(0, $size - 8 * 1024 * 1024));
        $text = (string) stream_get_contents($fh);
        fclose($fh);

        $hits = [];
        foreach (explode("\n", $text) as $line) {
            if (! preg_match('/\.(ERROR|WARNING|CRITICAL)/', $line)) {
                continue;
            }
            if (stripos($line, 'alibaba') !== false && (stripos($line, 'inventory') !== false || stripos($line, 'sku') !== false)) {
                $hits[] = mb_substr($line, 0, 380);
            }
        }
        $hits = array_slice($hits, -12);
        if ($hits === []) {
            $this->line('  no Alibaba inventory warnings/errors in the last 8 MB of '.basename($file));

            return;
        }
        foreach ($hits as $line) {
            $this->line('  '.$line);
        }
    }
}
