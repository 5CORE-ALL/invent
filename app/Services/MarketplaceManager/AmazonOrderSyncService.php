<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportAmazonOrderToShopify;
use App\Models\AmazonDailySync;
use App\Models\AmazonOrder;
use App\Models\MarketplaceSyncSettings;
use Carbon\Carbon;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace Manager order sync for Amazon.
 * Reuses the existing SP-API fetcher (app:fetch-amazon-orders) and local
 * amazon_orders / amazon_order_items tables, then queues FBM Shopify imports.
 */
class AmazonOrderSyncService
{
    /**
     * @return array{success: bool, message: string, fetched: int, stored: int}
     */
    public function sync(string $fromDate, bool $import = false): array
    {
        $result = $this->fetchAndStoreFromDate($fromDate);

        $dispatched = $this->dispatchImportsForNewOrders();
        if ($dispatched > 0) {
            $result['message'] .= " Dispatched {$dispatched} Shopify import job(s).";
        } elseif ($import && ! MarketplaceSyncSettings::canAutoImportToShopify('amazon')) {
            $result['message'] .= ' Auto-import is Off — no Shopify jobs queued.';
        }

        return $result;
    }

    /**
     * @return array{success: bool, message: string, fetched: int, stored: int}
     */
    public function fetchAndStore(int $days = 7): array
    {
        $days = max(1, min(90, $days));
        $from = Carbon::now('America/Los_Angeles')->subDays($days)->toDateString();

        return $this->fetchAndStoreFromDate($from);
    }

    /**
     * @return array{success: bool, message: string, fetched: int, stored: int}
     */
    public function fetchAndStoreFromDate(string $fromDate): array
    {
        if (! Schema::hasTable('amazon_orders')) {
            return [
                'success' => false,
                'message' => 'amazon_orders table missing. Run migrations.',
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $from = Carbon::parse($fromDate, 'America/Los_Angeles');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fromDate))) {
            $from = $from->startOfDay();
        }
        $to = Carbon::now('America/Los_Angeles')->endOfDay();
        if ($from->gt($to)) {
            return [
                'success' => false,
                'message' => 'Invalid from date.',
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        // Re-open today + yesterday so new Amazon orders after a mid-day "completed" mark are fetched.
        $this->reopenTrailingAmazonSyncDays(1);

        $before = (int) AmazonOrder::query()->count();

        try {
            Artisan::call('app:fetch-amazon-orders', [
                '--from' => $from->toDateString(),
                '--to' => $to->toDateString(),
                '--with-items' => true,
            ]);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            Log::error('AmazonOrderSyncService: fetch failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Amazon order fetch failed: '.$e->getMessage(),
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $after = (int) AmazonOrder::query()->count();
        $stored = max(0, $after - $before);
        $inRange = (int) AmazonOrder::query()
            ->where('order_date', '>=', $from->copy()->timezone('UTC'))
            ->count();

        $message = "Fetched Amazon orders from {$from->toDateString()} to {$to->toDateString()}.";
        if ($stored > 0) {
            $message .= " {$stored} new order(s) stored.";
        } else {
            $message .= " {$inRange} order(s) in range (upserted / already present).";
        }
        if ($output !== '') {
            $message .= ' '.$this->shortOutput($output);
        }

        return [
            'success' => true,
            'message' => $message,
            'fetched' => $inRange,
            'stored' => $stored,
        ];
    }

    public function dispatchImportsForNewOrders(): int
    {
        $settings = MarketplaceSyncSettings::getFor('amazon');
        if (! ($settings['order']['auto_import_to_shopify'] ?? false)) {
            Log::info('AmazonOrderSyncService: auto-import is Off — no Shopify jobs queued.');

            return 0;
        }
        if (! Schema::hasColumn('amazon_orders', 'shopify_order_id')) {
            Log::error('AmazonOrderSyncService: amazon_orders.shopify_order_id column missing — run migrations before auto-import can work.');

            return 0;
        }

        // Only recover rows left "queued" with no job after a previous insert failure.
        if ((int) DB::table('jobs')->where('queue', 'mm-amazon')->count() === 0) {
            $this->releaseStuckQueuedImports();
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('amazon', $settings);
        $cutoff = AmazonOrder::shopifyImportCutoff()->timezone('UTC');

        $orders = AmazonOrder::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', ['ready', 'import_failed', 'failed']);
            })
            ->where(function ($q) {
                $q->whereNull('fulfillment_channel')
                    ->orWhere('fulfillment_channel', '!=', 'AFN');
            })
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['Canceled', 'Cancelled', 'Pending']);
            })
            ->where('order_date', '>=', $cutoff)
            ->orderBy('id')
            ->limit(250)
            ->get([
                'id',
                'amazon_order_id',
                'status',
                'order_date',
                'fulfillment_channel',
                'import_status',
                'shopify_order_id',
                'raw_data',
            ]);

        $dispatched = 0;
        foreach ($orders as $order) {
            if ($order->isFba()) {
                $order->update(['import_status' => 'skipped_fba', 'fulfillment_channel' => 'AFN']);

                continue;
            }
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('amazon', $order)) {
                continue;
            }

            try {
                $job = new ImportAmazonOrderToShopify((int) $order->id);
                (new UniqueLock(app('cache.store')))->release($job);
                Queue::connection('database')->pushOn('mm-amazon', $job);
                $order->update(['import_status' => 'queued']);
                $dispatched++;
            } catch (\Throwable $e) {
                Log::warning('AmazonOrderSyncService: failed to queue import', [
                    'id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * Re-open today + N prior Pacific days so new Amazon orders are fetched after
     * a mid-day "completed" mark (CreatedBefore = now − 2 minutes).
     */
    protected function reopenTrailingAmazonSyncDays(int $priorDays = 2): void
    {
        if (! class_exists(AmazonDailySync::class) || ! Schema::hasTable('amazon_daily_syncs')) {
            return;
        }

        $from = Carbon::now('America/Los_Angeles')->subDays(max(0, $priorDays))->toDateString();
        AmazonDailySync::query()
            ->where('sync_date', '>=', $from)
            ->update([
                'status' => AmazonDailySync::STATUS_PENDING,
                'next_token' => null,
                'completed_at' => null,
                'error_message' => null,
            ]);
    }

    /**
     * Unique locks can swallow Bus::dispatch while import_status stays "queued"
     * with no row in jobs. Reset those so the next run actually pushes work.
     */
    protected function releaseStuckQueuedImports(): void
    {
        if (! Schema::hasColumn('amazon_orders', 'import_status')) {
            return;
        }

        AmazonOrder::query()
            ->where('import_status', 'queued')
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->update(['import_status' => null]);
    }

    protected function shortOutput(string $output): string
    {
        $lines = preg_split('/\R/', $output) ?: [];
        $last = '';
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $last = $line;
            }
        }

        return $last !== '' ? '('.$last.')' : '';
    }
}
