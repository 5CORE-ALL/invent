<?php

namespace App\Console\Commands;

use App\Models\PendingShopifyOrder;
use App\Models\ReverbOrderMetric;
use App\Services\ReverbOrderPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryPendingShopifyOrders extends Command
{
    protected $signature = 'shopify:retry-pending-orders
                            {--limit=50 : Max pending orders to process per run}
                            {--max-attempts=20 : Stop retrying after this many attempts}';

    protected $description = 'Retry creating Shopify orders from pending_shopify_orders (e.g. after Shopify downtime)';

    public function handle(ReverbOrderPushService $pushService): int
    {
        $limit = (int) $this->option('limit');
        $maxAttempts = (int) $this->option('max-attempts');

        $pending = PendingShopifyOrder::query()
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending Shopify orders to retry.');
            return self::SUCCESS;
        }

        $this->info('Retrying ' . $pending->count() . ' pending order(s).');

        $success = 0;
        $stillPending = 0;

        foreach ($pending as $row) {
            $metricId = $row->order_data['reverb_order_metric_id'] ?? $row->reverb_order_metric_id;
            $order = $metricId ? ReverbOrderMetric::find($metricId) : null;

            if (!$order) {
                Log::warning('RetryPendingShopifyOrders: ReverbOrderMetric not found', ['pending_id' => $row->id, 'metric_id' => $metricId]);
                $row->update([
                    'attempts' => $row->attempts + 1,
                    'last_attempt_at' => now(),
                    'last_error' => 'ReverbOrderMetric not found',
                ]);
                continue;
            }

            if ($order->shopify_order_id) {
                $row->delete();
                $this->line("  Order #{$order->order_number} already imported, removed from pending.");
                $success++;
                continue;
            }

            $row->update([
                'attempts' => $row->attempts + 1,
                'last_attempt_at' => now(),
                'last_error' => null,
            ]);

            try {
                $shopifyOrderId = $pushService->createOrderFromMarketplace($order);
            } catch (\Throwable $e) {
                $row->update(['last_error' => $e->getMessage()]);
                Log::warning('RetryPendingShopifyOrders: attempt failed', [
                    'pending_id' => $row->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
                $stillPending++;
                continue;
            }

            if ($shopifyOrderId) {
                $order->update([
                    'shopify_order_id' => (string) $shopifyOrderId,
                    'pushed_to_shopify_at' => now(),
                    'import_status' => 'imported',
                ]);
                if (class_exists(\App\Services\ReverbSyncLogService::class)) {
                    app(\App\Services\ReverbSyncLogService::class)->logOrderPushedToShopify(
                        (string) ($order->order_number ?? $order->id),
                        (int) $shopifyOrderId,
                        $order->sku,
                        'Reverb order #' . ($order->order_number ?? $order->id) . ' (retry from pending)'
                    );
                }
                $row->delete();
                $this->line("  Order #{$order->order_number} -> Shopify #{$shopifyOrderId}");
                $success++;
            } else {
                $row->update(['last_error' => 'createOrderFromMarketplace returned null']);
                $stillPending++;
            }
        }

        $this->info("Done: {$success} imported, {$stillPending} still pending.");
        return self::SUCCESS;
    }
}
