<?php

namespace App\Services\MarketplaceManager;

use App\Jobs\ImportAmazonOrderToShopify;
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

        $from = Carbon::parse($fromDate, 'America/Los_Angeles')->startOfDay();
        $to = Carbon::now('America/Los_Angeles')->endOfDay();
        if ($from->gt($to)) {
            return [
                'success' => false,
                'message' => 'Invalid from date.',
                'fetched' => 0,
                'stored' => 0,
            ];
        }

        $before = (int) AmazonOrder::query()->count();

        try {
            Artisan::call('app:fetch-amazon-orders', [
                '--from' => $from->toDateString(),
                '--to' => $to->toDateString(),
                '--with-items' => true,
                '--no-incremental-refresh' => true,
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
            return 0;
        }
        if (! Schema::hasColumn('amazon_orders', 'shopify_order_id')) {
            return 0;
        }

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly('amazon', $settings);
        $cutoff = AmazonOrder::shopifyImportCutoff()->timezone('UTC');

        $orders = AmazonOrder::query()
            ->whereNull('shopify_order_id')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', ['ready', 'import_failed', 'failed', 'queued']);
            })
            ->where(function ($q) {
                $q->whereNull('fulfillment_channel')
                    ->orWhere('fulfillment_channel', '!=', 'AFN');
            })
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['Canceled', 'Cancelled']);
            })
            ->where('order_date', '>=', $cutoff)
            ->orderBy('id')
            ->limit(100)
            ->get();

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
                ImportAmazonOrderToShopify::dispatch((int) $order->id);
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
