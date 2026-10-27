<?php

namespace App\Console\Commands;

use App\Models\EbayMetric;
use App\Models\ShopifySku;
use App\Services\EbayApiService;
use App\Services\MarketplaceManager\EbayPortalListingStatusSync;
use App\Services\MarketplaceManager\MarketplaceMismatchInventoryPass;
use Illuminate\Console\Command;

/**
 * Read-only: for each SKU show what the app stores, what Shopify has, and what eBay 1's
 * live ActiveList (Seller Hub) says, so "eBay mismatches but the app does not" can be explained.
 * Changes nothing.
 */
class DiagnoseEbay1Qty extends Command
{
    protected $signature = 'mm:diagnose-ebay1-qty
        {skus?* : SKUs to check}
        {--file= : Text file with one SKU per line}';

    protected $description = 'Read-only: compare app qty, Shopify qty and live eBay 1 qty for SKUs';

    public function handle(EbayPortalListingStatusSync $sync, MarketplaceMismatchInventoryPass $pass): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $skus = array_map('trim', (array) $this->argument('skus'));
        $file = trim((string) $this->option('file'));
        if ($file !== '' && is_readable($file)) {
            $skus = array_merge($skus, array_map('trim', file($file, FILE_IGNORE_NEW_LINES) ?: []));
        }
        $unique = [];
        foreach ($skus as $sku) {
            if ($sku !== '') {
                $unique[strtoupper($sku)] = $sku;
            }
        }
        $skus = array_values($unique);
        if ($skus === []) {
            $this->error('Give SKUs as arguments or --file=path (one per line).');

            return self::FAILURE;
        }

        $live = $sync->fetchActiveQuantities(1);
        if ($live === null) {
            $this->error('Could not read eBay 1 ActiveList (token or API problem). Check storage/logs/laravel.log for "active qty pull".');

            return self::FAILURE;
        }
        $liveBySku = [];
        $liveByNorm = [];
        foreach ($live as $row) {
            $liveBySku[strtoupper(trim((string) $row['sku']))][] = $row;
            $norm = strtoupper(ShopifySku::normalizeSkuForShopifyLookup((string) $row['sku']));
            if ($norm !== '') {
                $liveByNorm[$norm][] = $row;
            }
        }

        $metrics = EbayMetric::query()
            ->where(function ($q) use ($skus) {
                $q->whereIn('sku', $skus)->orWhereIn('sku', array_map('strtoupper', $skus));
            })
            ->get()
            ->groupBy(fn ($m) => strtoupper(trim((string) $m->sku)));
        $shopify = ShopifySku::mapByProductSkus($skus);
        $linkedSet = array_flip(array_map('strtoupper', $pass->linkedSkus('ebay1')));

        $rows = [];
        $verdicts = [];
        foreach ($skus as $sku) {
            $key = strtoupper($sku);
            $sh = $shopify->get($sku);
            $shQty = $sh ? (int) ($sh->available_to_sell ?? $sh->inv ?? 0) : null;
            $m = $metrics->get($key) ?? collect();
            $appRows = $m->map(fn ($x) => trim((string) $x->item_id).':'.($x->ebay_stock ?? '-').'/'.($x->listing_status ?? '-'))->implode(' | ');
            $liveRows = $liveBySku[$key] ?? $liveByNorm[strtoupper(ShopifySku::normalizeSkuForShopifyLookup($sku))] ?? [];
            $liveText = $liveRows === []
                ? 'NOT IN ActiveList'
                : implode(' | ', array_map(fn ($r) => $r['item_id'].':'.$r['qty'], $liveRows));
            $isLinked = isset($linkedSet[$key]) ? 'yes' : 'NO';

            if ($m->isEmpty()) {
                $verdict = 'no ebay_metrics row (not linked in app)';
            } elseif ($liveRows === []) {
                $verdict = 'eBay ActiveList does not list this SKU (ended / not active / SKU text differs)';
            } elseif (count($liveRows) > 1) {
                $verdict = 'SKU is on '.count($liveRows).' live eBay listings; app tracks only one';
            } else {
                $linkedIds = $m->pluck('item_id')->map(fn ($v) => trim((string) $v))->all();
                $liveId = (string) $liveRows[0]['item_id'];
                if (! in_array($liveId, $linkedIds, true)) {
                    $verdict = 'live listing '.$liveId.' differs from the app item id ('.implode(',', $linkedIds).')';
                } elseif ($shQty !== null && (int) $liveRows[0]['qty'] !== $shQty) {
                    $verdict = 'REAL MISMATCH: eBay '.$liveRows[0]['qty'].' vs Shopify '.$shQty;
                } else {
                    $verdict = 'eBay ActiveList equals Shopify';
                }
            }
            $verdicts[$verdict] = ($verdicts[$verdict] ?? 0) + 1;
            $rows[] = [$sku, $shQty ?? 'none', $appRows ?: '-', $liveText, $isLinked, $verdict];
        }

        $this->table(['SKU', 'Shopify', 'App item:stock/status', 'eBay live item:qty', 'linked', 'verdict'], $rows);
        $this->line('');
        $this->line('Summary:');
        arsort($verdicts);
        foreach ($verdicts as $text => $n) {
            $this->line(sprintf('  %3d  %s', $n, preg_replace('/\d{9,}/', '#', $text)));
        }

        return self::SUCCESS;
    }
}
