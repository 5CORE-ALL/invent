<?php

namespace App\Console\Commands;

use App\Jobs\ImportReverbOrderToShopify;
use App\Models\ReverbOrderMetric;
use App\Models\ReverbSyncSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessPendingReverbOrders extends Command
{
    protected $signature = 'reverb:process-pending
                            {--limit=500 : Max number of orders to dispatch}
                            {--force : Ignore import_orders_to_main_store setting}
                            {--wait-for-sync : Wait until reverb sync is not running before dispatching}
                            {--skip-sync-check : Dispatch even if sync is running (manual override)}
                            {--batch-size=100 : Orders per batch when using batch mode}
                            {--pause=5 : Seconds to pause between batches}
                            {--batch-mode : Process in batches with pause between (reduces load)}';

    protected $description = 'Dispatch ImportReverbOrderToShopify jobs for pending Reverb orders to the reverb queue';

    public function handle(): int
    {
        if ($this->option('wait-for-sync')) {
            $this->waitForSyncComplete();
        } elseif (!$this->option('skip-sync-check') && Cache::has('reverb_sync_running')) {
            $this->warn('Reverb sync is currently running. Use --wait-for-sync to wait, or --skip-sync-check to force.');
            return self::FAILURE;
        }

        $settings = ReverbSyncSettings::getForReverb();
        if (!($this->option('force') || ($settings['order']['import_orders_to_main_store'] ?? false))) {
            $this->warn('Auto-import is disabled. Enable "Import orders to main store" in Reverb Settings, or use --force');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $skipShipped = $settings['order']['skip_shipped_orders'] ?? false;

        $baseQuery = ReverbOrderMetric::query()
            ->whereNull('shopify_order_id')
            ->whereNotNull('order_paid_at')
            ->where(function ($q) {
                $q->whereNull('import_status')->orWhere('import_status', '!=', 'pending_shopify');
            })
            ->orderBy('order_date')
            ->orderBy('id');

        if ($skipShipped) {
            $baseQuery->whereNotIn('status', ['shipped', 'delivered']);
        }

        $totalDispatched = 0;

        if ($this->option('batch-mode')) {
            $batchSize = (int) $this->option('batch-size');
            $pause = (int) $this->option('pause');
            $batchNum = 0;
            $dispatchedIds = [];

            while ($totalDispatched < $limit) {
                $query = (clone $baseQuery)->limit($batchSize);
                if (!empty($dispatchedIds)) {
                    $query->whereNotIn('id', $dispatchedIds);
                }
                $orders = $query->get();
                if ($orders->isEmpty()) {
                    break;
                }

                $batchNum++;
                $batchDispatched = 0;
                foreach ($orders as $order) {
                    ImportReverbOrderToShopify::dispatch($order->id)->onQueue('reverb');
                    $dispatchedIds[] = $order->id;
                    $batchDispatched++;
                    $totalDispatched++;
                    if ($totalDispatched >= $limit) {
                        break;
                    }
                }
                $this->info("  Batch {$batchNum}: dispatched {$batchDispatched} orders (total: {$totalDispatched})");

                if ($totalDispatched >= $limit) {
                    break;
                }
                if ($pause > 0) {
                    sleep($pause);
                }
            }
        } else {
            $orders = $baseQuery->limit($limit)->get();
            foreach ($orders as $order) {
                ImportReverbOrderToShopify::dispatch($order->id)->onQueue('reverb');
                $totalDispatched++;
            }
            if ($totalDispatched > 0) {
                $this->info("  Dispatched {$totalDispatched} orders.");
            }
        }

        if ($totalDispatched === 0) {
            $this->info('No pending Reverb orders to process.');
            return self::SUCCESS;
        }

        $this->info("Dispatched {$totalDispatched} orders to reverb queue. Run: php artisan queue:work --queue=reverb");
        return self::SUCCESS;
    }

    protected function waitForSyncComplete(): void
    {
        $this->info('Waiting for reverb sync to complete...');
        $attempts = 0;
        $maxAttempts = 180; // 30 min at 10s interval

        while (Cache::has('reverb_sync_running') && $attempts < $maxAttempts) {
            $attempts++;
            $this->output->write('.');
            sleep(10);
        }

        if (Cache::has('reverb_sync_running')) {
            $this->newLine();
            $this->warn('Sync still running after 30 min. Aborting.');
            exit(self::FAILURE);
        }

        $this->newLine();
        $this->info('Sync complete. Proceeding with dispatch.');
    }
}
