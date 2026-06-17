<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ShopifyApiInventoryController;
use App\Models\ShopifySku;

/**
 * Logs each run (mode + outcome) to channel `shopify_live_inventory`:
 * storage/logs/shopify-live-inventory-YYYY-MM-DD.log
 *
 * Production cron: use `scripts/cron-shopify-sync-live-inventory.sh` (not Laravel `schedule:run` for this command).
 */
class SyncShopifyLiveInventory extends Command
{
    protected $signature = 'shopify:sync-live-inventory
                            {--sku= : Sync a single SKU only}
                            {--probe= : Fast: comma-separated SKUs only (no full catalog; seconds not hours)}
                            {--limit= : Fast: sync first N rows from shopify_skus (has variant_id), same GraphQL path as full sync}
                            {--samples=0 : Print GraphQL payloads for the first N SKUs (use with full sync; with --limit, defaults to all in batch)}';

    protected $description = 'Sync Ohio inventory via GraphQL. Use --limit=10 or --probe= for fast checks; full sync paginates all products (slow). After sync, run shopify:spot-check-sku-list to print SKUs to compare in Admin.';

    public function handle()
    {
        set_time_limit(0);

        $sku = $this->option('sku');
        $probe = $this->option('probe');
        $samplesN = max(0, (int) $this->option('samples'));
        $limitOpt = $this->option('limit');
        $limit = ($limitOpt !== null && $limitOpt !== '') ? max(0, (int) $limitOpt) : 0;

        $runId = uniqid('cmd_', true);
        $t0 = microtime(true);
        $mode = filled($probe) ? 'probe' : ($limit > 0 ? 'limit' : ($sku ? 'single_sku' : 'full_catalog'));

        Log::channel('shopify_live_inventory')->info('artisan_run_started', [
            'run_id' => $runId,
            'mode' => $mode,
            'probe_count' => filled($probe) ? count(array_filter(array_map('trim', explode(',', $probe)))) : null,
            'limit' => $limit > 0 ? $limit : null,
            'sku' => $sku ?: null,
            'samples_cli' => $samplesN,
        ]);

        if (filled($probe)) {
            $skus = array_values(array_filter(array_map('trim', explode(',', $probe))));
            if ($skus === []) {
                $this->error('Pass SKUs after --probe=, e.g. --probe="SKU A,SKU B"');
                Log::channel('shopify_live_inventory')->warning('artisan_run_finished', [
                    'run_id' => $runId,
                    'mode' => 'probe',
                    'ok' => false,
                    'reason' => 'empty_probe_list',
                ]);

                return 1;
            }

            $this->info('Fast probe: '.count($skus).' SKU(s) only (no full catalog pagination).');
            $controller = new ShopifyApiInventoryController();
            $sampleLimit = $samplesN > 0 ? $samplesN : count($skus);
            if (! $controller->syncLiveInventoryForSkuList($skus, $sampleLimit)) {
                $this->error('Probe failed (Ohio location, API token, or shopify_skus.variant_id missing).');
                Log::channel('shopify_live_inventory')->error('artisan_run_finished', [
                    'run_id' => $runId,
                    'mode' => 'probe',
                    'ok' => false,
                    'duration_seconds' => round(microtime(true) - $t0, 2),
                ]);

                return 1;
            }

            $this->printGraphQlSamples($controller->getGraphQlQuantitySamples());

            $this->newLine();
            $this->info('Probe finished; only the listed SKUs were updated in shopify_skus.');
            Log::channel('shopify_live_inventory')->info('artisan_run_finished', [
                'run_id' => $runId,
                'mode' => 'probe',
                'ok' => true,
                'duration_seconds' => round(microtime(true) - $t0, 2),
            ]);

            return 0;
        }

        if ($limit > 0) {
            if ($limit > 500) {
                $this->error('--limit cannot exceed 500.');
                Log::channel('shopify_live_inventory')->warning('artisan_run_finished', [
                    'run_id' => $runId,
                    'mode' => 'limit',
                    'ok' => false,
                    'reason' => 'limit_exceeds_500',
                ]);

                return 1;
            }

            $rows = ShopifySku::query()
                ->whereNotNull('variant_id')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->limit($limit)
                ->get(['sku']);

            $skus = $rows->pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->values()->all();

            if ($skus === []) {
                $this->error('No shopify_skus rows found with sku + variant_id (run product / SKU sync first).');
                Log::channel('shopify_live_inventory')->warning('artisan_run_finished', [
                    'run_id' => $runId,
                    'mode' => 'limit',
                    'ok' => false,
                    'reason' => 'no_rows',
                ]);

                return 1;
            }

            $this->info('Limited batch sync: '.count($skus).' SKU(s) (first '.$limit.' by id from shopify_skus, same GraphQL logic as full sync).');
            $this->line('<fg=gray>SKUs: '.implode(', ', $skus).'</>');
            $this->newLine();

            $controller = new ShopifyApiInventoryController();
            $sampleLimit = $samplesN > 0 ? min($samplesN, count($skus)) : count($skus);

            if (! $controller->syncLiveInventoryForSkuList($skus, $sampleLimit)) {
                $this->error('Batch sync failed (Ohio location, API token, or variant API errors).');
                Log::channel('shopify_live_inventory')->error('artisan_run_finished', [
                    'run_id' => $runId,
                    'mode' => 'limit',
                    'ok' => false,
                    'duration_seconds' => round(microtime(true) - $t0, 2),
                ]);

                return 1;
            }

            $this->printGraphQlSamples($controller->getGraphQlQuantitySamples());

            $this->newLine();
            $this->info('Batch finished; only these SKUs were updated in shopify_skus.');
            Log::channel('shopify_live_inventory')->info('artisan_run_finished', [
                'run_id' => $runId,
                'mode' => 'limit',
                'ok' => true,
                'duration_seconds' => round(microtime(true) - $t0, 2),
            ]);

            return 0;
        }

        if ($sku) {
            $this->info("Looking up SKU: {$sku}");
            $controller = new ShopifyApiInventoryController();
            $recordSamples = $samplesN > 0 ? 1 : 0;
            if (! $controller->syncLiveInventoryForSku($sku, $recordSamples)) {
                $this->error("Failed to sync SKU (check variant_id, Ohio location, API token): {$sku}");
                Log::channel('shopify_live_inventory')->error('artisan_run_finished', [
                    'run_id' => $runId,
                    'mode' => 'single_sku',
                    'ok' => false,
                    'sku' => $sku,
                    'duration_seconds' => round(microtime(true) - $t0, 2),
                ]);

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
            Log::channel('shopify_live_inventory')->info('artisan_run_finished', [
                'run_id' => $runId,
                'mode' => 'single_sku',
                'ok' => true,
                'sku' => $row->sku ?? $sku,
                'quantities_written' => [
                    'available_to_sell' => (int) ($row->available_to_sell ?? 0),
                    'committed' => (int) ($row->committed ?? 0),
                    'unavailable' => (int) ($row->unavailable ?? 0),
                    'on_hand' => (int) ($row->on_hand ?? 0),
                    'incoming' => (int) ($row->incoming ?? 0),
                ],
                'duration_seconds' => round(microtime(true) - $t0, 2),
            ]);

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

        $finished = [
            'run_id' => $runId,
            'mode' => 'full_catalog',
            'ok' => $success,
            'duration_seconds' => round(microtime(true) - $t0, 2),
        ];
        if ($success) {
            Log::channel('shopify_live_inventory')->info('artisan_run_finished', $finished);
        } else {
            Log::channel('shopify_live_inventory')->error('artisan_run_finished', $finished);
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
