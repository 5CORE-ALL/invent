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
                            {--max-attempts=20 : Stop retrying after this many attempts}
                            {--custom-only : Force custom line item (skip variant) for all retries}';

    protected $description = 'Retry creating Shopify orders from pending_shopify_orders (e.g. after Shopify downtime)';

    public function handle(ReverbOrderPushService $pushService): int
    {
        $limit = (int) $this->option('limit');
        $maxAttempts = (int) $this->option('max-attempts');
        $customOnly = $this->option('custom-only');

        $pending = PendingShopifyOrder::query()
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending Shopify orders to retry.');
            return self::SUCCESS;
        }

        $this->info('Retrying ' . $pending->count() . ' pending order(s)' . ($customOnly ? ' (custom-only)' : '') . '.');

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
                $stillPending++;
                continue;
            }

            if ($order->shopify_order_id) {
                $row->delete();
                $this->line("  Order #{$order->order_number} already imported, removed from pending.");
                $success++;
                continue;
            }

            $previousError = $row->last_error;
            $row->update([
                'attempts' => $row->attempts + 1,
                'last_attempt_at' => now(),
                'last_error' => null,
            ]);

            $shopifyOrderId = null;

            try {
                if ($customOnly) {
                    $shopifyOrderId = $pushService->createOrderWithCustomItem(
                        $order,
                        'Retry from pending (custom-only). Previous: ' . substr((string) $previousError, 0, 100),
                        ['SKU Missing', 'Retry-Custom']
                    );
                } else {
                    $shopifyOrderId = $pushService->createOrderFromMarketplace($order);
                }
            } catch (\Throwable $e) {
                $row->update(['last_error' => 'Exception: ' . $e->getMessage()]);
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
                $errorDetail = $pushService->lastFailureReason ?? $pushService->lastApiErrorCode ?? 'createOrderFromMarketplace returned null';
                $row->update(['last_error' => $errorDetail]);
                Log::warning('RetryPendingShopifyOrders: create returned null', [
                    'pending_id' => $row->id,
                    'order_number' => $order->order_number,
                    'last_failure_reason' => $pushService->lastFailureReason,
                    'last_api_error' => $pushService->lastApiErrorCode,
                ]);
                $stillPending++;
            }
        }

        if ($stillPending > 0) {
            $highAttempts = PendingShopifyOrder::where('attempts', '>=', 5)->count();
            if ($highAttempts > 0) {
                Log::critical('RetryPendingShopifyOrders: repeated failures – orders still pending after 5+ attempts', [
                    'high_attempt_count' => $highAttempts,
                    'still_pending' => $stillPending,
                ]);
            }
        }

        $this->info("Done: {$success} imported, {$stillPending} still pending.");
        return self::SUCCESS;
    }
}
