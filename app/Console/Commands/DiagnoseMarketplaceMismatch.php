<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
use App\Services\MarketplaceManager\Ebay2InventorySyncService;
use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\MarketplaceManager\MarketplaceListingStockResolver;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\MarketplaceMismatchInventoryPass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only by default: why does a marketplace keep showing Inv SKU Mismatch?
 * With --push it pushes the first few mismatch SKUs and prints the marketplace's real answer.
 */
class DiagnoseMarketplaceMismatch extends Command
{
    protected $signature = 'marketplace:diagnose-mismatch
        {channel : ebay2, topdawg, alibaba, ... (Marketplace Manager slug)}
        {--limit=8 : How many mismatch SKUs to show / push}
        {--sku=* : Check these SKUs instead of the first mismatch SKUs}
        {--push : Push the shown SKUs now (exact Shopify qty) and print the marketplace response}
        {--live : eBay 2 only: read each SKU\'s eBay item now and list the variation SKUs it really holds}';

    protected $description = 'Why is a marketplace stuck on Inv SKU Mismatch? Shows settings, queue, failed jobs, per-SKU qty and (with --push) the real API answer.';

    /** @var array<string, array{table: string, sku: string, cols: list<string>}> */
    private const LOCAL = [
        'ebay2' => ['table' => 'ebay_2_metrics', 'sku' => 'sku', 'cols' => ['id', 'item_id', 'sku', 'ebay_stock', 'listing_status', 'inactive_reason', 'updated_at']],
        'topdawg' => ['table' => 'topdawg_products', 'sku' => 'sku', 'cols' => ['id', 'topdawg_listing_id', 'sku', 'remaining_inventory', 'status', 'updated_at']],
        'alibaba' => ['table' => 'alibaba_metrics', 'sku' => 'sku', 'cols' => ['id', 'product_id', 'sku', 'updated_at']],
    ];

    public function handle(): int
    {
        @set_time_limit(0);
        $channel = strtolower(trim((string) $this->argument('channel')));
        $limit = max(1, min(40, (int) $this->option('limit')));
        $pass = app(MarketplaceMismatchInventoryPass::class);

        $this->line('=== '.$channel);

        // 1. Settings gate
        $settings = MarketplaceSyncSettings::getFor($channel);
        $invOn = (bool) ($settings['inventory']['inventory_sync'] ?? false);
        $priceOn = (bool) ($settings['pricing']['price_sync'] ?? false);
        $this->line('inventory sync setting : '.($invOn ? 'ON' : 'OFF').'   price sync: '.($priceOn ? 'ON' : 'OFF')
            .(MarketplaceMismatchInventoryPass::syncEnabled($channel) ? '' : '   <-- both OFF: the mismatch pass is skipped entirely'));
        $this->line('qty percent / max / offset : '.json_encode([
            $settings['inventory']['quantity_calc_percent'] ?? 100,
            $settings['inventory']['max_quantity'] ?? null,
            $settings['inventory']['quantity_offset'] ?? 0,
        ]));

        // 2. Channel specifics
        if ($channel === 'ebay2') {
            $limited = Ebay2InventorySyncService::isTradingLimited();
            $this->line('eBay 2 Trading API limit : '.($limited ? 'LIMITED (518) - '.Ebay2InventorySyncService::tradingLimitMessage() : 'not flagged'));
        }

        // 3. Queue + failed jobs
        $queue = MarketplaceManagerRegistry::queueFor($channel);
        $this->line('queue                  : '.$queue.' (default driver: '.config('queue.default').')');
        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            $this->line('  waiting jobs         : '.DB::table('jobs')->where('queue', $queue)->count());
        }
        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->where('queue', $queue)->orderByDesc('id')->limit(5)->get(['failed_at', 'payload', 'exception']);
            $this->line('  failed jobs (last 5) : '.$failed->count());
            foreach ($failed as $f) {
                $name = json_decode((string) $f->payload, true)['displayName'] ?? '?';
                $this->line('    '.$f->failed_at.'  '.$name.'  '.mb_substr(strtok((string) $f->exception, "\n") ?: '', 0, 260));
            }
        }

        // 4. Mismatch list
        Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.$channel);
        $active = [];
        try {
            $active = app(MarketplaceListingQtyMatchService::class)->activeMismatchSkus($channel, false);
        } catch (\Throwable $e) {
            $this->warn('mismatch list failed: '.$e->getMessage());
        }
        $this->line('mismatch SKUs on the page : '.count($active));

        $skus = array_values(array_filter(array_map('trim', (array) $this->option('sku'))));
        if ($skus === []) {
            $skus = array_slice($active, 0, $limit);
        }
        if ($skus === []) {
            $this->info('Nothing to check.');

            return self::SUCCESS;
        }

        // 5. Per-SKU view
        $shopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        $mp = $pass->stockMap($channel, $skus);
        $local = self::LOCAL[$channel] ?? null;
        foreach ($skus as $sku) {
            $s = MarketplaceListingStockResolver::qtyFromMap($shopify, $sku);
            $m = MarketplaceListingStockResolver::qtyFromMap($mp, $sku);
            $within = ($s !== null && $m !== null) ? MarketplaceLiveInventoryRules::qtyWithinMismatchTolerance((int) $s, (int) $m, $channel) : null;
            $this->line('');
            $this->line('--- '.$sku.'   shopify='.var_export($s, true).'  marketplace='.var_export($m, true).'  within tolerance='.var_export($within, true));
            if ($local && Schema::hasTable($local['table'])) {
                $cols = array_values(array_filter($local['cols'], static fn ($c) => Schema::hasColumn($local['table'], $c)));
                $rows = DB::table($local['table'])->whereRaw('UPPER(TRIM('.$local['sku'].')) = ?', [strtoupper($sku)])->get($cols);
                $this->line('    rows in '.$local['table'].': '.$rows->count().($rows->count() > 1 ? '  <-- DUPLICATES' : ''));
                foreach ($rows as $r) {
                    $this->line('      '.json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
            }
            if ($channel === 'alibaba' && Schema::hasTable('alibaba_pricing_prices')) {
                $p = DB::table('alibaba_pricing_prices')->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first(['sku', 'ab_stock', 'updated_at']);
                $this->line('    alibaba_pricing_prices: '.($p ? json_encode($p) : 'none'));
            }
        }

        // 5b. What eBay itself holds on each linked item
        if ($this->option('live') && $channel === 'ebay2' && Schema::hasTable('ebay_2_metrics')) {
            $api = app(\App\Services\EbayTwoApiService::class);
            $itemIds = DB::table('ebay_2_metrics')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $s) {
                        $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($s)]);
                    }
                })
                ->whereNotNull('item_id')->where('item_id', '!=', '')
                ->pluck('item_id')->unique()->values();
            $this->line('');
            foreach ($itemIds as $itemId) {
                $raw = $api->getItem((string) $itemId);
                $item = is_array($raw) && is_array($raw['Item'] ?? null) ? $raw['Item'] : [];
                if ($item === []) {
                    $this->line('item '.$itemId.': eBay returned nothing (deleted or not this seller\'s item)');
                    continue;
                }
                $status = $item['SellingStatus']['ListingStatus'] ?? '?';
                $vars = $item['Variations']['Variation'] ?? [];
                if (is_array($vars) && ($vars === [] || isset($vars['SKU']) || isset($vars['Quantity']))) {
                    $vars = $vars === [] ? [] : [$vars];
                }
                $list = [];
                foreach ((array) $vars as $v) {
                    $list[] = (string) ($v['SKU'] ?? '?').'='.($v['Quantity'] ?? '?');
                }
                $this->line('item '.$itemId.': status '.$status.', item SKU "'.($item['SKU'] ?? '').'", '.count($list).' variation(s): '.($list === [] ? '(single listing)' : implode(' | ', $list)));
            }
        }

        // 6. Optional live push
        if ($this->option('push')) {
            $this->line('');
            $this->line('Pushing '.count($skus).' SKU(s) now ...');
            $result = $pass->pushSkus($channel, $skus);
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $back = $pass->readBack($channel, $skus);
            $this->line('read-back from marketplace: '.($back === null ? 'not supported for this channel' : json_encode($back)));
        } else {
            $this->line('');
            $this->line('Add --push to push these SKUs now and see the real error.');
        }

        return self::SUCCESS;
    }
}
