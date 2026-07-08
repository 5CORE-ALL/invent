<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AliexpressOrderPushService
{
    public ?string $lastFailureReason = null;

    public ?int $lastApiStatus = null;

    public function importToShopify(AliexpressOrderMetric $order): ?string
    {
        if ($order->shopify_order_id) {
            return (string) $order->shopify_order_id;
        }

        $settings = MarketplaceSyncSettings::getFor('aliexpress');
        $tags = array_values(array_unique(array_merge(
            ['aliexpress', 'aliexpress-'.($order->order_number ?? $order->order_id)],
            $settings['order']['shopify_order_tags'] ?? []
        )));

        $variantId = $order->sku && $order->sku !== '__order__' && $order->sku !== '__unknown__'
            ? $this->findShopifyVariantIdBySku($order->sku)
            : null;

        if ($variantId) {
            $shopifyOrderId = $this->createWithVariant($order, (int) $variantId, $tags);
            if ($shopifyOrderId) {
                return $shopifyOrderId;
            }
        }

        return $this->createWithCustomItem($order, $variantId ? 'Variant create failed' : 'SKU not found in Shopify', $tags);
    }

    protected function createWithVariant(AliexpressOrderMetric $order, int $variantId, array $tags): ?string
    {
        $config = $this->shopifyConfig();
        $price = number_format((float) ($order->amount ?? 0), 2, '.', '');
        $quantity = max(1, (int) ($order->quantity ?? 1));
        $orderRef = (string) ($order->order_number ?? $order->order_id);

        $payload = [
            'order' => [
                'line_items' => [['variant_id' => $variantId, 'quantity' => $quantity, 'price' => $price]],
                'financial_status' => 'paid',
                'inventory_behaviour' => 'decrement_obeying_policy',
                'tags' => implode(', ', $this->cleanTags($tags)),
                'note' => 'Imported from AliExpress Order #'.$orderRef,
                'source_name' => 'aliexpress',
                'note_attributes' => [['name' => 'aliexpress_order_id', 'value' => $orderRef]],
            ],
        ];

        $response = $this->postOrder($config, $payload);

        return $response;
    }

    protected function createWithCustomItem(AliexpressOrderMetric $order, string $reason, array $tags): ?string
    {
        $config = $this->shopifyConfig();
        $orderRef = (string) ($order->order_number ?? $order->order_id);
        $title = mb_substr(trim((string) ($order->display_title ?: $order->sku ?: 'AliExpress item')), 0, 255);
        $price = number_format(max(0, (float) ($order->amount ?? 0)), 2, '.', '');
        $quantity = max(1, (int) ($order->quantity ?? 1));

        $lineItem = [
            'title' => $title !== '' ? $title : 'AliExpress order item',
            'price' => $price,
            'quantity' => $quantity,
        ];
        if ($order->sku && ! in_array($order->sku, ['__order__', '__unknown__'], true)) {
            $lineItem['properties'] = [['name' => 'SKU', 'value' => mb_substr($order->sku, 0, 255)]];
        }

        $payload = [
            'order' => [
                'line_items' => [$lineItem],
                'financial_status' => 'paid',
                'inventory_behaviour' => 'bypass',
                'tags' => implode(', ', $this->cleanTags(array_merge($tags, ['SKU Missing']))),
                'note' => 'Imported from AliExpress Order #'.$orderRef."\n".$reason,
                'source_name' => 'aliexpress',
                'note_attributes' => [['name' => 'aliexpress_order_id', 'value' => $orderRef]],
            ],
        ];

        return $this->postOrder($config, $payload);
    }

    /**
     * @param  array{store_url: string, token: string}  $config
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
