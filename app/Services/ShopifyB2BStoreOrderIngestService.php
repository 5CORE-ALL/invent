<?php

namespace App\Services;

use App\Models\B5cB2bOrder;
use App\Models\ShopifyB2BDailyData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Flatten business5core.com B2B /api/orders into shopify_b2b_daily_data
 * so /shopify-b2b/daily-sales and B2B L30 use live store orders.
 */
class ShopifyB2BStoreOrderIngestService
{
    public const SOURCE = 'business5core';

    /**
     * @return int lines written
     */
    public function flattenOrder(B5cB2bOrder $order): int
    {
        if (! Schema::hasTable('shopify_b2b_daily_data')) {
            return 0;
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $lines = $this->lineItems($payload);
        $orderId = (string) $order->store_order_id;
        $orderDate = $order->ordered_at
            ?? $this->parseDate($payload['created_at'] ?? $payload['ordered_at'] ?? null);
        $financial = $this->financialStatus($order->status ?? ($payload['status'] ?? null));
        $tracking = trim((string) ($order->tracking_reference ?? $payload['tracking_reference'] ?? ''));
        $fulfillment = $this->fulfillmentStatus($order->status ?? ($payload['status'] ?? null), $tracking);
        $shipping = is_array($payload['shipping_address'] ?? null)
            ? $payload['shipping_address']
            : (is_array($payload['shipping'] ?? null) ? $payload['shipping'] : []);

        $written = 0;
        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = trim((string) ($line['sku'] ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $qty = (int) ($line['qty'] ?? $line['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $unit = $this->money($line['unit_price'] ?? $line['price'] ?? $line['selling_price'] ?? 0);
            $original = $this->money($line['original_price'] ?? $line['regular_price'] ?? $unit);
            if ($original <= 0) {
                $original = $unit;
            }
            $discount = $this->money($line['discount_amount'] ?? $line['discount'] ?? 0);
            if ($discount <= 0 && $original > $unit) {
                $discount = round(($original - $unit) * $qty, 2);
            }
            $total = $this->money($line['total'] ?? $line['line_total'] ?? 0);
            if ($total <= 0) {
                $total = round(($unit * $qty), 2);
            }

            $lineId = (string) ($line['id'] ?? $line['product_id'] ?? $sku.':'.$index);

            ShopifyB2BDailyData::query()->updateOrCreate(
                [
                    'order_id' => $orderId,
                    'line_item_id' => $lineId,
                ],
                [
                    'order_number' => $payload['order_number'] ?? $payload['number'] ?? $orderId,
                    'product_id' => isset($line['product_id']) ? (string) $line['product_id'] : (isset($line['id']) ? (string) $line['id'] : null),
                    'variant_id' => isset($line['variant_id']) ? (string) $line['variant_id'] : null,
                    'order_date' => $orderDate,
                    'financial_status' => $financial,
                    'fulfillment_status' => $fulfillment,
                    'sku' => $sku,
                    'product_title' => (string) ($line['name'] ?? $line['title'] ?? $sku),
                    'quantity' => $qty,
                    'price' => $unit,
                    'original_price' => $original,
                    'discount_amount' => $discount,
                    'total_amount' => $total,
                    'customer_name' => $order->customer_name ?? ($payload['customer_name'] ?? null),
                    'customer_email' => $order->customer_email ?? ($payload['customer_email'] ?? null),
                    'shipping_city' => $shipping['city'] ?? ($payload['shipping_city'] ?? null),
                    'shipping_country' => $shipping['country'] ?? $shipping['country_name'] ?? ($payload['shipping_country'] ?? null),
                    'tracking_company' => $payload['shipping_method'] ?? null,
                    'tracking_number' => $tracking !== '' ? $tracking : null,
                    'tracking_url' => $payload['tracking_url'] ?? null,
                    'source_name' => self::SOURCE,
                    'tags' => self::SOURCE,
                ]
            );
            $written++;
        }

        return $written;
    }

    /**
     * @return int lines written
     */
    public function flattenStoredOrders(?string $fromDate = null): int
    {
        if (! Schema::hasTable('b5c_b2b_orders') || ! Schema::hasTable('shopify_b2b_daily_data')) {
            return 0;
        }

        $query = B5cB2bOrder::query();
        if ($fromDate) {
            $query->where(function ($q) use ($fromDate) {
                $q->where('ordered_at', '>=', $fromDate)
                    ->orWhereNull('ordered_at');
            });
        }

        $written = 0;
        foreach ($query->orderBy('store_order_id')->cursor() as $order) {
            try {
                $written += $this->flattenOrder($order);
            } catch (\Throwable $e) {
                Log::warning('B2B store order flatten failed', [
                    'store_order_id' => $order->store_order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $written;
    }

    public function refreshPeriodLabels(): void
    {
        if (! Schema::hasTable('shopify_b2b_daily_data')) {
            return;
        }

        $today = Carbon::today();
        $l30Start = $today->copy()->subDays(30);
        $l60Start = $today->copy()->subDays(60);

        ShopifyB2BDailyData::query()->where('order_date', '>=', $l30Start)->update(['period' => 'l30']);
        ShopifyB2BDailyData::query()
            ->where('order_date', '>=', $l60Start)
            ->where('order_date', '<', $l30Start)
            ->update(['period' => 'l60']);
        ShopifyB2BDailyData::query()
            ->whereNotNull('order_date')
            ->where('order_date', '<', $l60Start)
            ->update(['period' => null]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    protected function lineItems(array $payload): array
    {
        foreach (['products', 'line_items', 'items', 'lines'] as $key) {
            if (is_array($payload[$key] ?? null)) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }
        if (is_array($payload['data']['products'] ?? null)) {
            return array_values(array_filter($payload['data']['products'], 'is_array'));
        }

        return [];
    }

    protected function financialStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return match (true) {
            in_array($status, ['refunded', 'refund', 'returned'], true) => 'refunded',
            in_array($status, ['canceled', 'cancelled'], true) => 'cancelled',
            in_array($status, ['completed', 'complete', 'paid', 'delivered', 'shipped', 'fulfilled'], true) => 'paid',
            in_array($status, ['pending', 'processing', 'on_hold', 'hold', 'unpaid', 'new'], true) => 'pending',
            $status !== '' => $status,
            default => 'paid',
        };
    }

    protected function fulfillmentStatus(?string $status, string $tracking): ?string
    {
        if ($tracking !== '') {
            return 'fulfilled';
        }
        $status = strtolower(trim((string) $status));
        if (in_array($status, ['shipped', 'delivered', 'fulfilled', 'completed', 'complete'], true)) {
            return 'fulfilled';
        }
        if (in_array($status, ['unfulfilled', 'pending', 'processing', 'new'], true)) {
            return 'unfulfilled';
        }

        return $status !== '' ? $status : null;
    }

    protected function money(mixed $value): float
    {
        if (is_array($value)) {
            $value = $value['amount'] ?? $value['value'] ?? $value['price'] ?? 0;
        }

        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
