<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Http;

/**
 * Write a marketplace tracking number onto the linked Shopify order.
 */
class ShopifyFulfillmentTrackingWriter
{
    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @return array{success: bool, message: string}
     */
    public function apply(array $config, string $shopifyOrderId, string $tracking, string $carrier = 'Other'): array
    {
        $tracking = trim($tracking);
        $shopifyOrderId = trim($shopifyOrderId);
        if ($tracking === '' || $shopifyOrderId === '') {
            return ['success' => false, 'message' => 'Shopify order id or marketplace tracking number is missing.'];
        }

        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return ['success' => false, 'message' => 'Shopify store credentials are missing.'];
        }

        $carrier = trim($carrier) !== '' ? trim($carrier) : 'Other';
        $headers = [
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ];

        try {
            $orders = Http::withHeaders($headers)
                ->timeout(30)
                ->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}/fulfillment_orders.json");

            $open = [];
            if ($orders->successful()) {
                foreach ($orders->json('fulfillment_orders') ?? [] as $fo) {
                    if (! is_array($fo) || empty($fo['id'])) {
                        continue;
                    }
                    $status = strtolower((string) ($fo['status'] ?? ''));
                    if (in_array($status, ['closed', 'cancelled'], true)) {
                        continue;
                    }
                    $open[] = ['fulfillment_order_id' => $fo['id']];
                }
            }

            if ($open !== []) {
                $created = Http::withHeaders($headers)
                    ->timeout(30)
                    ->post("https://{$storeUrl}/admin/api/2024-01/fulfillments.json", [
                        'fulfillment' => [
                            'line_items_by_fulfillment_order' => $open,
                            'tracking_info' => [
                                'number' => $tracking,
                                'company' => mb_substr($carrier, 0, 100),
                            ],
                            'notify_customer' => false,
                        ],
                    ]);
                if ($created->successful()) {
                    return ['success' => true, 'message' => 'Shopify fulfillment created from marketplace tracking.'];
                }
            }

            $order = Http::withHeaders($headers)
                ->timeout(30)
                ->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}.json", [
                    'fields' => 'id,fulfillments',
                ]);
            if (! $order->successful()) {
                return ['success' => false, 'message' => 'Could not load Shopify fulfillments to update tracking.'];
            }

            $want = strtoupper(preg_replace('/[\s\-]/', '', $tracking) ?? $tracking);
            foreach ($order->json('order.fulfillments') ?? [] as $fulfillment) {
                if (! is_array($fulfillment) || empty($fulfillment['id'])) {
                    continue;
                }
                $status = strtolower((string) ($fulfillment['status'] ?? ''));
                if (in_array($status, ['cancelled', 'error', 'failure'], true)) {
                    continue;
                }
                $existing = trim((string) ($fulfillment['tracking_number'] ?? ''));
                if ($existing === '' && ! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
                    $existing = trim((string) ($fulfillment['tracking_numbers'][0] ?? ''));
                }
                $existingNorm = strtoupper(preg_replace('/[\s\-]/', '', $existing) ?? $existing);
                if ($existing !== '' && $existingNorm === $want) {
                    return ['success' => true, 'message' => 'Shopify already has this marketplace tracking number.'];
                }

                $updated = Http::withHeaders($headers)
                    ->timeout(30)
                    ->post("https://{$storeUrl}/admin/api/2024-01/fulfillments/".((int) $fulfillment['id']).'/update_tracking.json', [
                        'fulfillment' => [
                            'notify_customer' => false,
                            'tracking_info' => [
                                'number' => $tracking,
                                'company' => mb_substr($carrier, 0, 100),
                            ],
                        ],
                    ]);
                if ($updated->successful()) {
                    return ['success' => true, 'message' => 'Shopify fulfillment tracking updated from the marketplace.'];
                }
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Marketplace tracking could not be written onto the Shopify order.'];
    }
}
