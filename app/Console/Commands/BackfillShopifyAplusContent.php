<?php

namespace App\Console\Commands;

use App\Models\ProductMaster;
use App\Services\Support\ShopifyAplusContentSync;
use Illuminate\Console\Command;

class BackfillShopifyAplusContent extends Command
{
    protected $signature = 'description:shopify-aplus-backfill
        {--limit=150 : Max SKUs to fetch in this run (Shopify rate limit friendly)}
        {--sku= : Only this SKU}
        {--force : Re-fetch even when A+ content is already stored}
        {--retry-failed : Also retry SKUs whose last automatic fetch failed}
        {--reclean : Do not call Shopify; strip the top bullet-point block from already stored A+ content}
        {--sleep-ms=700 : Pause between SKUs}';

    protected $description = 'Description Master: fetch the Shopify description once per SKU and store it as A+ content';

    public function handle(ShopifyAplusContentSync $sync): int
    {
        if (! ShopifyAplusContentSync::isSchemaReady()) {
            $this->error('product_master is missing the shopify_aplus_* columns. Run php artisan migrate first.');

            return self::FAILURE;
        }

        if ($this->option('reclean')) {
            return $this->recleanStored($sync);
        }

        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $retryFailed = (bool) $this->option('retry-failed');
        $sleepUs = max(0, (int) $this->option('sleep-ms')) * 1000;
        $onlySku = trim((string) $this->option('sku'));

        $query = ProductMaster::query()
            ->select(['id', 'sku', 'shopify_aplus_content', 'shopify_aplus_images', 'shopify_aplus_fetched_at', 'shopify_aplus_fetch_error'])
            ->whereNotNull('sku')
            ->where('sku', '<>', '')
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->orderBy('id');

        if ($onlySku !== '') {
            $query->where('sku', $onlySku);
        } elseif (! $force) {
            $query->where(function ($q) {
                $q->whereNull('shopify_aplus_content')->orWhere('shopify_aplus_content', '');
            });
            if (! $retryFailed) {
                $query->whereNull('shopify_aplus_fetch_error');
            }
        }

        $products = $query->limit($limit)->get();
        if ($products->isEmpty()) {
            $this->info('Nothing to fetch — every SKU already has Shopify A+ content (or was already attempted).');

            return self::SUCCESS;
        }

        $this->info(sprintf('Fetching Shopify A+ content for %d SKU(s)...', $products->count()));
        $ok = 0;
        $fail = 0;
        $skipped = 0;

        foreach ($products as $i => $product) {
            $result = $sync->fetchAndStore($product, $force || $onlySku !== '');
            $status = (string) $result['status'];
            if ($status === 'cached') {
                $skipped++;
            } elseif ($result['success']) {
                $ok++;
            } else {
                $fail++;
            }
            $this->line(sprintf('[%d/%d] %s — %s: %s', $i + 1, $products->count(), $product->sku, $status, $result['message']));

            if ($sleepUs > 0 && $i < $products->count() - 1) {
                usleep($sleepUs);
            }
        }

        $this->info("Done. Fetched: {$ok}, failed: {$fail}, already stored: {$skipped}.");

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Rectify SKUs stored with the full Shopify body: strip the top bullet block from every stored snapshot.
     */
    private function recleanStored(ShopifyAplusContentSync $sync): int
    {
        $onlySku = trim((string) $this->option('sku'));
        $query = ProductMaster::query()
            ->select(['id', 'sku', 'shopify_aplus_content'])
            ->whereNotNull('shopify_aplus_content')
            ->where('shopify_aplus_content', '<>', '')
            ->orderBy('id');
        if ($onlySku !== '') {
            $query->where('sku', $onlySku);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No stored A+ content to re-clean.');

            return self::SUCCESS;
        }

        $this->info("Re-cleaning stored A+ content for {$total} SKU(s) (no Shopify calls)...");
        $changed = 0;
        $query->chunkById(200, function ($products) use ($sync, &$changed) {
            foreach ($products as $product) {
                $r = $sync->recleanStored($product);
                if ($r['changed']) {
                    $changed++;
                    $this->line(sprintf('  %s: %d -> %d chars', $product->sku, $r['before'], $r['after']));
                }
            }
        });

        $this->info("Done. {$changed} of {$total} SKU(s) had the top bullet block removed.");

        return self::SUCCESS;
    }
}
