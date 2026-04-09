<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ShopifyApiInventoryController;
use App\Models\ShopifySku;

class SyncShopifyLiveInventory extends Command
{
    protected $signature = 'shopify:sync-live-inventory
                            {--sku= : Sync a single SKU only}
                            {--probe= : Fast: comma-separated SKUs only (no full catalog; seconds not hours)}
                            {--samples=0 : With full sync: print GraphQL payloads for the first N SKUs only (does not speed up sync)}';

    protected $description = 'Sync Ohio inventory via GraphQL. Full store sync paginates all products (slow). Use --probe=SKU1,SKU2 for quick checks.';

    public function handle()
    {
        set_time_limit(0);

        $sku = $this->option('sku');
        $probe = $this->option('probe');
        $samplesN = max(0, (int) $this->option('samples'));

        if (filled($probe)) {
            $skus = array_values(array_filter(array_map('trim', explode(',', $probe))));
            if ($skus === []) {
                $this->error('Pass SKUs after --probe=, e.g. --probe="SKU A,SKU B"');

                return 1;
            }

            $this->info('Fast probe: '.count($skus).' SKU(s) only (no full catalog pagination).');
            $controller = new ShopifyApiInventoryController();
            $sampleLimit = $samplesN > 0 ? $samplesN : count($skus);
            if (! $controller->syncLiveInventoryForSkuList($skus, $sampleLimit)) {
                $this->error('Probe failed (Ohio location, API token, or shopify_skus.variant_id missing).');

                return 1;
            }

            $this->printGraphQlSamples($controller->getGraphQlQuantitySamples());

            $this->newLine();
            $this->info('Probe finished; only the listed SKUs were updated in shopify_skus.');

            return 0;
        }

        if ($sku) {
            $this->info("Looking up SKU: {$sku}");
            $controller = new ShopifyApiInventoryController();
            $recordSamples = $samplesN > 0 ? 1 : 0;
            if (! $controller->syncLiveInventoryForSku($sku, $recordSamples)) {
                $this->error("Failed to sync SKU (check variant_id, Ohio location, API token): {$sku}");

                return 1;
            }

            if ($samplesN > 0) {
                $this->printGraphQlSamples($controller->getGraphQlQuantitySamples());
            }

            $row = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
            $this->newLine();
            $this->info('Updated SKU: '.($row->sku ?? $sku));
            $this->info('  available_to_sell = '.(int) ($row->available_to_sell ?? 0));
            $this->info('  committed         = '.(int) ($row->committed ?? 0));
            $this->info('  unavailable       = '.(int) ($row->unavailable ?? 0));
            $this->info('  on_hand           = '.(int) ($row->on_hand ?? 0));

            return 0;
        }

        $controller = new ShopifyApiInventoryController();
        $success = $controller->syncLiveInventoryToDb($samplesN);

        if ($samplesN > 0) {
            $this->newLine();
            $this->printGraphQlSamples($controller->getGraphQlQuantitySamples());
        }

        if ($success) {
            $this->newLine();
            $this->info('Successfully synced live Shopify inventory (Ohio)');
        } else {
            $this->error('Failed to sync live Shopify inventory');
        }

        return $success ? 0 : 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     */
    protected function printGraphQlSamples(array $samples): void
    {
        if ($samples === []) {
            $this->warn('No GraphQL quantity samples were captured (GraphQL may have failed or returned no nodes).');

            return;
        }

        $this->line('<fg=cyan>── GraphQL API quantity samples (raw vs sanitized) ──</>');
        $this->line('Shopify <fg=yellow>committed</> is inventory allocated to unfulfilled orders <fg=yellow>at this location (Ohio)</>.');
        $this->line('If Admin shows Committed = 0 for that location, the API will also return 0.');
        $this->newLine();

        foreach ($samples as $i => $sample) {
            $n = $i + 1;
            $this->line("<fg=green>Sample {$n}</>");
            $this->line(json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->newLine();
        }
    }
}
