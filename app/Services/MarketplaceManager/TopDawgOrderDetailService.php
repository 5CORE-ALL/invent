<?php

namespace App\Services\MarketplaceManager;

use App\Models\TopDawgOrderMetric;
use App\Services\TopDawgApiService;
use Illuminate\Support\Facades\Schema;

class TopDawgOrderDetailService
{
    public function __construct(
        protected TopDawgApiService $topdawgApi
    ) {}

    /**
     * @return array{success: bool, message?: string}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! Schema::hasTable('topdawg_order_metrics')) {
            return ['success' => false, 'message' => 'Order id missing or table unavailable.'];
        }

        $existing = TopDawgOrderMetric::query()
            ->where(function ($q) use ($orderId) {
                $q->where('order_id', $orderId)->orWhere('order_number', $orderId);
            })
            ->orderByDesc('id')
            ->first();
        $cached = is_array($existing?->raw_payload) ? $existing->raw_payload : [];
        if ($cached !== []) {
            return ['success' => true, 'message' => 'Using stored TopDawg order payload.'];
        }

        // TopDawg has no single-order endpoint. Never re-list history on push —
        // only scan the last 2 PST days when we have no stored payload.
        try {
            $result = $this->topdawgApi->fetchOrders(
                TopDawgOrderSyncService::shopifyImportCutoffDate()->toIso8601String()
            );
            $orders = $result['data'] ?? [];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $match = null;
        $wanted = array_values(array_filter(array_unique([
            strtoupper($orderId),
            strtoupper(trim((string) ($existing?->order_number ?? ''))),
            strtoupper(trim((string) ($existing?->order_id ?? ''))),
        ])));
        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }
            foreach (['order_number', 'orderNumber', 'order_id', 'id'] as $key) {
                $num = strtoupper(trim((string) ($order[$key] ?? '')));
                if ($num !== '' && in_array($num, $wanted, true)) {
                    $match = $order;
                    break 2;
                }
            }
        }

        if ($match !== null) {
            app(TopDawgOrderSyncService::class)->upsertSingleOrder($match);

            return ['success' => true, 'message' => 'Order refreshed from TopDawg API.'];
        }

        if ($cached !== []) {
            return ['success' => true, 'message' => 'Using stored TopDawg order payload.'];
        }

        return ['success' => false, 'message' => 'Order not found in recent TopDawg API results.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(TopDawgOrderMetric $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
        if ($raw !== []) {
            return $raw;
        }

        return [
            'order_number' => $line->order_number ?? $line->order_id,
            'order_id' => $line->order_id ?? $line->order_number,
            'status' => $line->status,
            'order_date' => $line->order_date,
            'amount' => $line->amount,
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    public function extractOrders(array $json): array
    {
        $orders = $json['orders'] ?? $json['results'] ?? $json['data'] ?? [];

        return is_array($orders) ? array_values(array_filter($orders, 'is_array')) : [];
    }
}
