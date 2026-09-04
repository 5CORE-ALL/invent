<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportAmazonOrderToShopify;
use App\Models\AmazonDailySync;
use App\Models\AmazonOrder;
use App\Models\MarketplaceSyncSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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

        // Re-open today + trailing days so mid-day "completed" marks do not freeze new orders.
        $this->resetStaleAmazonDailySyncs($from);
        $this->reopenTrailingAmazonSyncDays(2);

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

        MarketplaceShopifyImportQueue::prepareForDispatch(
            AmazonOrder::class,
            MarketplaceManagerRegistry::queueFor('amazon')
        );
        MarketplaceShopifyImportQueue::prepareForDispatch(
            AmazonOrder::class,
            MarketplaceManagerRegistry::listingsQueueFor('amazon')
        );

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('amazon', $settings);
        $cutoff = AmazonOrder::shopifyImportCutoff()->timezone('UTC');

        $orders = AmazonOrder::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', MarketplaceShopifyImportQueue::DISPATCHABLE_IMPORT_STATUSES)
                    ->orWhere(function ($queued) {
                        $queued->where('import_status', 'queued')
                            ->where('updated_at', '<', now()->subMinutes(2));
                    });
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
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit(500)
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

        $seen = [];
        $dispatched = 0;
        $imported = 0;
        $inlineLimit = 40;
        $push = app(AmazonOrderPushService::class);
        foreach ($orders as $order) {
            $amazonId = trim((string) ($order->amazon_order_id ?? ''));
            if ($amazonId !== '') {
                if (isset($seen[$amazonId])) {
                    continue;
                }
                $seen[$amazonId] = true;
                $alreadyImported = AmazonOrder::query()
                    ->where('amazon_order_id', $amazonId)
                    ->whereNotNull('shopify_order_id')
                    ->where('shopify_order_id', '!=', '')
                    ->value('shopify_order_id');
                if ($alreadyImported) {
                    AmazonOrder::query()
                        ->where('amazon_order_id', $amazonId)
                        ->where(function ($q) {
                            $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                        })
                        ->update([
                            'shopify_order_id' => (string) $alreadyImported,
                            'import_status' => 'imported',
                        ]);

                    continue;
                }
            }
            if ($order->isFba()) {
                $order->update(['import_status' => 'skipped_fba', 'fulfillment_channel' => 'AFN']);

                continue;
            }
            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid('amazon', $order)) {
                continue;
            }

            if ($imported < $inlineLimit) {
                try {
                    $shopifyId = $push->importToShopify($order);
                    if ($shopifyId) {
                        $imported++;
                        continue;
                    }
                    if ($push->lastSkipStatus) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    Log::warning('AmazonOrderSyncService: inline import failed', [
                        'id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                MarketplaceShopifyImportQueue::push(
                    new ImportAmazonOrderToShopify((int) $order->id),
                    MarketplaceManagerRegistry::listingsQueueFor('amazon')
                );
                $order->update(['import_status' => 'queued']);
                $dispatched++;
            } catch (\Throwable $e) {
                Log::warning('AmazonOrderSyncService: failed to queue import', [
                    'id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($imported > 0) {
            Log::info('AmazonOrderSyncService: imported Amazon orders inline', [
                'imported' => $imported,
                'queued' => $dispatched,
            ]);
        }

        return $imported + $dispatched;
    }

    /**
     * If mm-amazon workers are down or unique-locked, still create the newest
     * FBM Shopify copies here (duplicate-guarded) so daily sync cannot stall.
     */
    public function importUnpushedInline(int $limit = 5): int
    {
        $limit = max(1, min(40, $limit));
        $cutoff = AmazonOrder::shopifyImportCutoff()->timezone('UTC');
        $orders = AmazonOrder::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->where(function ($q) {
                $q->whereIn('import_status', ['ready', 'import_failed', 'failed'])
                    ->orWhere(function ($queued) {
                        $queued->where('import_status', 'queued')
                            ->where('updated_at', '<', now()->subMinutes(2));
                    });
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
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $push = app(AmazonOrderPushService::class);
        $imported = 0;
        foreach ($orders as $order) {
            try {
                $id = $push->importToShopify($order);
                if ($id) {
                    $imported++;
                }
            } catch (\Throwable $e) {
                Log::warning('AmazonOrderSyncService: inline import failed', [
                    'id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $imported;
    }

    /**
     * Retry failed days and abandoned in_progress rows in the fetch window so
     * everyday Amazon sync cannot stay stuck forever.
     */
    protected function resetStaleAmazonDailySyncs(Carbon $from): void
    {
        if (! class_exists(AmazonDailySync::class) || ! Schema::hasTable('amazon_daily_syncs')) {
            return;
        }

        $fromDate = $from->copy()->timezone('America/Los_Angeles')->toDateString();

        AmazonDailySync::query()
            ->where('sync_date', '>=', $fromDate)
            ->where('status', AmazonDailySync::STATUS_FAILED)
            ->update([
                'status' => AmazonDailySync::STATUS_PENDING,
                'next_token' => null,
                'error_message' => null,
            ]);

        AmazonDailySync::query()
            ->where('sync_date', '>=', $fromDate)
            ->where('status', AmazonDailySync::STATUS_IN_PROGRESS)
            ->where(function ($q) {
                $q->whereNull('started_at')
                    ->orWhere('started_at', '<', now()->subHours(2));
            })
            ->update([
                'status' => AmazonDailySync::STATUS_PENDING,
                'next_token' => null,
                'error_message' => 'Reset stale in_progress daily sync',
            ]);
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
