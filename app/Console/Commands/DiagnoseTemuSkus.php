<?php

namespace App\Console\Commands;

use App\Models\ShopifySku;
use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Services\MarketplaceManager\MarketplaceListingStockResolver;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use App\Services\MarketplaceManager\MarketplaceMismatchInventoryPass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only: for each SKU, show what our database holds for it on Temu, what Shopify holds, what the
 * mismatch check decides, and (Temu 3) what Temu itself reports right now.
 */
class DiagnoseTemuSkus extends Command
{
    protected $signature = 'temu:diagnose-skus
        {skus* : SKUs to check (quote each one that has spaces)}
        {--channel=temu3 : temu, temu2 or temu3}
        {--live : Also ask Temu for its live per-SKU stock (Temu 3 only, a few API pages)}';

    protected $description = 'Why is a Temu SKU (not) shown as an inventory mismatch? Read-only.';

    public function handle(): int
    {
        $channel = strtolower(trim((string) $this->option('channel')));
        $table = match ($channel) {
            'temu' => 'temu_metrics',
            'temu2' => 'temu2_metrics',
            'temu3' => 'temu3_metrics',
            default => null,
        };
        if ($table === null || ! Schema::hasTable($table)) {
            $this->error('Unknown channel or table missing. Use --channel=temu|temu2|temu3.');

            return self::FAILURE;
        }

        $skus = array_values(array_filter(array_map(static fn ($s) => trim((string) $s), (array) $this->argument('skus')), static fn ($s) => $s !== ''));
        $pass = app(MarketplaceMismatchInventoryPass::class);

        Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.$channel);
        $linked = $pass->linkedSkus($channel);
        $linkedNorm = [];
        foreach ($linked as $l) {
            $linkedNorm[ShopifySku::normalizeSkuForShopifyLookup((string) $l)] = (string) $l;
        }
        $classified = app(MarketplaceListingQtyMatchService::class)->classify($channel);
        $active = [];
        try {
            $active = app(MarketplaceListingQtyMatchService::class)->activeMismatchSkus($channel, false);
        } catch (\Throwable $e) {
        }

        $live = [];
        if ($this->option('live') && $channel === 'temu3') {
            $this->line('Reading live per-SKU stock from Temu 3 ...');
            $live = app(\App\Services\Temu3ApiService::class)->skuStockSnapshotFromApi();
            $this->line('  Temu returned '.count($live).' SKU rows');
        }

        $columns = array_values(array_filter(
            ['id', 'sku', 'goods_id', 'sku_id', 'quantity', 'listing_status', 'inactive_reason', 'updated_at'],
            static fn ($c) => Schema::hasColumn($table, $c)
        ));

        foreach ($skus as $sku) {
            $this->line('');
            $this->line('=== '.$sku);
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);

            $rows = DB::table($table)->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->get($columns);
            $this->line('  rows in '.$table.': '.$rows->count().($rows->count() > 1 ? '   <-- DUPLICATES' : ''));
            foreach ($rows as $r) {
                $this->line('    '.json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
            if ($rows->isEmpty()) {
                $this->warn('  no Temu row at all: the app does not know this SKU on '.$channel.' (Not on Temu / unlinked).');
            }

            $shopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus([$sku]);
            $shopifyQty = MarketplaceListingStockResolver::qtyFromMap($shopify, $sku);
            $this->line('  live Shopify qty              : '.var_export($shopifyQty, true));

            $mp = $pass->stockMap($channel, [$sku]);
            $mpQty = MarketplaceListingStockResolver::qtyFromMap($mp, $sku);
            $this->line('  Temu qty the check compares   : '.var_export($mpQty, true).($mpQty === null ? '   <-- null = SKU is skipped, never counted as a mismatch' : ''));

            $this->line('  counted as a linked SKU       : '.(isset($linkedNorm[$norm]) ? 'yes' : 'NO (no goods_id on its row)'));
            if ($shopifyQty !== null && $mpQty !== null) {
                $within = MarketplaceLiveInventoryRules::qtyWithinMismatchTolerance((int) $shopifyQty, $mpQty, $channel);
                $this->line('  within tolerance (no push)    : '.($within ? 'yes' : 'no'));
            }

            $bucket = 'none';
            foreach (['matched', 'mismatch', 'linked_mismatch', 'zero'] as $b) {
                foreach ($classified[$b] ?? [] as $s) {
                    if (ShopifySku::normalizeSkuForShopifyLookup((string) $s) === $norm) {
                        $bucket = $b;
                        break 2;
                    }
                }
            }
            $this->line('  bucket on the page            : '.$bucket);
            $inActive = false;
            foreach ($active as $s) {
                if (ShopifySku::normalizeSkuForShopifyLookup((string) $s) === $norm) {
                    $inActive = true;
                    break;
                }
            }
            $this->line('  in the mismatch list          : '.($inActive ? 'yes' : 'no'));

            if ($live !== []) {
                $hit = $live[strtoupper($sku)] ?? null;
                if ($hit === null) {
                    foreach ($live as $key => $row) {
                        if (ShopifySku::normalizeSkuForShopifyLookup((string) $key) === $norm) {
                            $hit = $row;
                            break;
                        }
                    }
                }
                $this->line('  LIVE on Temu                  : '.($hit === null
                    ? 'NOT in Temu\'s on-sale or not-on-sale SKU list (Temu reports nothing for it)'
                    : 'qty '.var_export($hit['qty'], true).', status '.$hit['status'].', goods '.$hit['goods_id'].', sku_id '.$hit['sku_id']));
            }
        }

        return self::SUCCESS;
    }
}
