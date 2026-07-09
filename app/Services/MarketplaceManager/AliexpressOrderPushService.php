<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AliexpressOrderPushService
{
    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    public function __construct(
        protected AliexpressOrderDetailService $orderDetailService,
        protected AliexpressDetailFormatter $formatter
    ) {}

    public function importToShopify(AliexpressOrderMetric $order): ?string
    {
        if ($order->shopify_order_id) {
            return (string) $order->shopify_order_id;
        }

        $detailResult = $this->orderDetailService->fetchAndPersistOrderDetail((string) $order->order_id);
        if (empty($detailResult['success'])) {
            $this->lastFailureReason = $detailResult['message'] ?? 'Could not load AliExpress order details before Shopify push.';

            return null;
        }

        $lines = AliexpressOrderMetric::query()
            ->where('order_id', (string) $order->order_id)
            ->orderBy('id')
            ->get();

        $orderRoot = $this->orderDetailService->resolveOrderRoot($order->fresh() ?? $order);
        $detail = $this->formatter->formatOrder($orderRoot, $lines, $order);

        $settings = MarketplaceSyncSettings::getFor('aliexpress');
        $tags = array_values(array_unique(array_merge(
            ['aliexpress', 'aliexpress-'.($order->order_number ?? $order->order_id)],
            $settings['order']['shopify_order_tags'] ?? []
        )));

        $orderPayload = $this->formatter->buildShopifyOrderPayload($detail, $lines, $this->cleanTags($tags));
        $orderPayload = $this->resolveLineItemsForShopify($orderPayload, $lines);

        $shopifyOrderId = $this->postOrder($this->shopifyConfig(), ['order' => $orderPayload]);
        if (! $shopifyOrderId) {
            return null;
        }

        $tracking = (string) (($detail['shipment']['tracking'] ?? '') ?: '');
        $carrier = (string) (($detail['shipment']['service'] ?? '') ?: 'AliExpress');
        if ($tracking !== '') {
            $this->addFulfillmentTracking($shopifyOrderId, $tracking, $carrier);
        }

        AliexpressOrderMetric::query()
            ->where('order_id', (string) $order->order_id)
            ->update([
                'shopify_order_id' => $shopifyOrderId,
                'pushed_to_shopify_at' => now(),
                'import_status' => 'imported',
            ]);

        return $shopifyOrderId;
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     * @return array<string, mixed>
     */
    protected function resolveLineItemsForShopify(array $orderPayload, Collection $lines): array
    {
        $resolved = [];
        $sourceItems = $orderPayload['line_items'] ?? [];

        if ($sourceItems === []) {
            foreach ($lines as $line) {
                $sku = (string) $line->sku;
                if (in_array($sku, ['__order__', '__unknown__', ''], true)) {
                    continue;
                }
                $sourceItems[] = [
                    'sku' => $sku,
                    'title' => (string) ($line->display_title ?: $sku),
                    'quantity' => max(1, (int) ($line->quantity ?? 1)),
                    'price' => number_format((float) ($line->amount ?? 0), 2, '.', ''),
                ];
            }
        }

        foreach ($sourceItems as $item) {
            $sku = (string) ($item['sku'] ?? '');
            $variantId = $sku !== '' ? $this->findShopifyVariantIdBySku($sku) : null;
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = (string) ($item['price'] ?? '0.00');

            if ($variantId) {
                $line = ['variant_id' => $variantId, 'quantity' => $quantity];
                if ((float) $price > 0) {
                    $line['price'] = $price;
                }
                $resolved[] = $line;
                continue;
            }

            $title = mb_substr(trim((string) ($item['title'] ?? $sku ?: 'AliExpress item')), 0, 255);
            $custom = [
                'title' => $title !== '' ? $title : 'AliExpress order item',
                'price' => $price,
                'quantity' => $quantity,
            ];
            if ($sku !== '') {
                $custom['sku'] = $sku;
                $custom['properties'] = [['name' => 'SKU', 'value' => mb_substr($sku, 0, 255)]];
            }
            $resolved[] = $custom;
        }

        if ($resolved === []) {
            $line = $lines->first();
            $resolved[] = [
                'title' => (string) ($line?->display_title ?: 'AliExpress order item'),
                'price' => number_format((float) ($line?->amount ?? 0), 2, '.', ''),
                'quantity' => max(1, (int) ($line?->quantity ?? 1)),
            ];
        }

        $orderPayload['line_items'] = $resolved;

        if (! str_contains((string) ($orderPayload['note'] ?? ''), 'SKU not found')) {
            $missing = collect($sourceItems)->filter(function ($item) {
                $sku = (string) ($item['sku'] ?? '');

                return $sku !== '' && ! $this->findShopifyVariantIdBySku($sku);
            });
            if ($missing->isNotEmpty()) {
                $orderPayload['tags'] = trim((string) ($orderPayload['tags'] ?? '').', SKU Missing', ', ');
            }
        }

        return $orderPayload;
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $payload
     */
    protected function postOrder(array $config, array $payload): ?string
    {
        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders.json';

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($url, $payload);

            $this->lastApiStatus = $response->status();

            if (! $response->successful()) {
                $this->lastFailureReason = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
                Log::error('AliexpressOrderPushService: Shopify order create failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $id = (string) ($response->json('order.id') ?? '');
            if ($id === '') {
                $this->lastFailureReason = 'Shopify returned no order id';

                return null;
            }

            return $id;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();
            Log::error('AliexpressOrderPushService: exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function addFulfillmentTracking(string $shopifyOrderId, string $trackingNumber, string $carrier): void
    {
        $config = $this->shopifyConfig();
        $storeUrl = $config['store_url'];
        $token = $config['token'];

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}/fulfillment_orders.json");

            if (! $response->successful()) {
                return;
            }

            $lineItems = [];
            foreach ($response->json('fulfillment_orders') ?? [] as $fo) {
                if (! empty($fo['id'])) {
                    $lineItems[] = ['fulfillment_order_id' => $fo['id']];
                }
            }
            if ($lineItems === []) {
                return;
            }

            Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("https://{$storeUrl}/admin/api/2024-01/fulfillments.json", [
                'fulfillment' => [
                    'line_items_by_fulfillment_order' => $lineItems,
                    'tracking_info' => [
                        'number' => $trackingNumber,
                        'company' => mb_substr($carrier, 0, 100),
                    ],
                    'notify_customer' => false,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('AliexpressOrderPushService: fulfillment tracking failed', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function findShopifyVariantIdBySku(string $sku): ?int
    {
        $config = $this->shopifyConfig();
        $url = 'https://'.$config['store_url'].'/admin/api/2024-01/variants.json?sku='.urlencode($sku);

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
            ])->timeout(30)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $variants = $response->json('variants') ?? [];
            if (! is_array($variants) || $variants === []) {
                return null;
            }

            return (int) ($variants[0]['id'] ?? 0) ?: null;
        } catch (\Throwable $e) {
            Log::warning('AliexpressOrderPushService: variant lookup failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{store_url: string, token: string}
     */
    protected function shopifyConfig(): array
    {
        $selector = app(ShopifyStoreSelector::class);

        return [
            'store_url' => str_replace(['https://', 'http://'], '', $selector->getStoreUrl()),
            'token' => $selector->getPassword(),
        ];
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    protected function cleanTags(array $tags): array
    {
        $cleaned = [];
        foreach ($tags as $tag) {
            $t = trim((string) $tag);
            if ($t === '') {
                continue;
            }
            $t = str_replace([',', "\r", "\n"], ' ', $t);
            $t = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $t);
            $t = trim(preg_replace('/\s+/', ' ', $t));
            if ($t !== '') {
                $cleaned[] = $t;
            }
        }

        return array_values(array_unique($cleaned));
    }
}
