<?php

namespace App\Services\MarketplaceManager;

use App\Models\AliexpressOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Models\ShopifySku;
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

    /**
     * @return array<string, mixed>
     */
    public function previewShopifyPush(AliexpressOrderMetric $order): array
    {
        $plan = $this->buildImportPlan($order);
        $plan['dry_run'] = true;
        if (! empty($plan['success'])) {
            $plan['message'] = 'Dry run only — no Shopify order was created.';
        }

        return $plan;
    }

    public function importToShopify(AliexpressOrderMetric $order): ?string
    {
        if ($order->shopify_order_id) {
            return (string) $order->shopify_order_id;
        }

        $plan = $this->buildImportPlan($order);
        if (empty($plan['success'])) {
            $this->lastFailureReason = $plan['message'] ?? 'Could not build Shopify import plan.';

            return null;
        }

        $shopifyOrderId = $this->postOrder($this->shopifyConfig(), ['order' => $plan['payload']]);
        if (! $shopifyOrderId) {
            return null;
        }

        $fulfillment = is_array($plan['fulfillment'] ?? null) ? $plan['fulfillment'] : [];
        $tracking = (string) ($fulfillment['tracking'] ?? '');
        $carrier = (string) ($fulfillment['carrier'] ?? 'AliExpress');
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
     * @return array<string, mixed>
     */
    public function buildImportPlan(AliexpressOrderMetric $order): array
    {
        if ($order->shopify_order_id) {
            return [
                'success' => false,
                'message' => 'Already imported.',
                'shopify_order_id' => (string) $order->shopify_order_id,
            ];
        }

        $detailResult = $this->orderDetailService->fetchAndPersistOrderDetail((string) $order->order_id);
        if (empty($detailResult['success'])) {
            return [
                'success' => false,
                'message' => $detailResult['message'] ?? 'Could not load AliExpress order details before Shopify push.',
            ];
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
        [$orderPayload, $lineResolution] = $this->resolveLineItemsForShopify($orderPayload, $lines);

        $config = $this->shopifyConfig();
        $tracking = (string) (($detail['shipment']['tracking'] ?? '') ?: '');
        $carrier = (string) (($detail['shipment']['service'] ?? '') ?: 'AliExpress');

        $warnings = $this->buildImportWarnings($detail, $lineResolution, $orderPayload);

        return [
            'success' => true,
            'aliexpress_order_id' => (string) $order->order_id,
            'shopify_store' => $config['store_url'],
            'shopify_store_key' => $config['store_key'] ?? null,
            'payload' => $orderPayload,
            'fulfillment' => [
                'tracking' => $tracking !== '' ? $tracking : null,
                'carrier' => $carrier !== '' ? $carrier : null,
                'will_create' => $tracking !== '',
            ],
            'line_resolution' => $lineResolution,
            'warnings' => $warnings,
            'preview' => $this->buildHumanPreview($detail, $orderPayload, $lineResolution, $fulfillment = [
                'tracking' => $tracking,
                'carrier' => $carrier,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<int, array<string, mixed>>  $lineResolution
     * @param  array<string, mixed>  $orderPayload
     * @return array<int, string>
     */
    protected function buildImportWarnings(array $detail, array $lineResolution, array $orderPayload): array
    {
        $warnings = [];
        $shipping = $detail['shipping'] ?? [];

        if (empty($orderPayload['shipping_address']['address1'] ?? null)) {
            $warnings[] = 'No shipping address — Shopify order will be created without shipping_address.';
        }
        if (empty($shipping['email'] ?? null) && empty($orderPayload['customer']['email'] ?? null)) {
            $warnings[] = 'No buyer email on AliExpress order.';
        }
        if (empty($shipping['phone'] ?? null)) {
            $warnings[] = 'No buyer phone on AliExpress order.';
        }

        foreach ($lineResolution as $row) {
            if (($row['match_type'] ?? '') === 'custom') {
                $warnings[] = 'SKU not found in Shopify: '.($row['sku'] ?? '?').' — will use custom line item.';
            }
        }

        if (($lineResolution === [])) {
            $warnings[] = 'No resolvable line items.';
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            $warnings[] = 'Shopify store credentials are not configured for the selected import store.';
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $orderPayload
     * @param  array<int, array<string, mixed>>  $lineResolution
     * @param  array<string, mixed>  $fulfillment
     * @return array<string, mixed>
     */
    protected function buildHumanPreview(array $detail, array $orderPayload, array $lineResolution, array $fulfillment): array
    {
        $shipping = $orderPayload['shipping_address'] ?? [];
        $customer = $orderPayload['customer'] ?? [];

        return [
            'customer' => [
                'name' => trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? '')),
                'email' => $customer['email'] ?? null,
                'phone' => $shipping['phone'] ?? null,
            ],
            'shipping_address' => [
                'address1' => $shipping['address1'] ?? null,
                'address2' => $shipping['address2'] ?? null,
                'city' => $shipping['city'] ?? null,
                'province' => $shipping['province'] ?? null,
                'zip' => $shipping['zip'] ?? null,
                'country_code' => $shipping['country_code'] ?? null,
            ],
            'payment_method' => $detail['payment']['method'] ?? null,
            'tracking' => $fulfillment['tracking'] ?? null,
            'shipping_method' => $fulfillment['carrier'] ?? null,
            'line_items' => $lineResolution,
            'tags' => $orderPayload['tags'] ?? null,
            'note' => $orderPayload['note'] ?? null,
            'note_attributes' => $orderPayload['note_attributes'] ?? [],
            'shipping_lines' => $orderPayload['shipping_lines'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @param  Collection<int, AliexpressOrderMetric>  $lines
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    protected function resolveLineItemsForShopify(array $orderPayload, Collection $lines): array
    {
        $resolved = [];
        $meta = [];
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
            $matchSource = null;
            if ($sku !== '') {
                $localRow = ShopifySku::firstForProductSku($sku);
                if ($localRow && ! empty($localRow->variant_id)) {
                    $matchSource = 'shopify_skus';
                }
            }
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = (string) ($item['price'] ?? '0.00');
            $title = mb_substr(trim((string) ($item['title'] ?? $sku ?: 'AliExpress item')), 0, 255);

            if ($variantId) {
                $line = ['variant_id' => $variantId, 'quantity' => $quantity];
                if ((float) $price > 0) {
                    $line['price'] = $price;
                }
                $resolved[] = $line;
                $meta[] = [
                    'sku' => $sku,
                    'title' => $title,
                    'quantity' => $quantity,
                    'price' => $price,
                    'variant_id' => $variantId,
                    'match_type' => 'variant',
                    'match_source' => $matchSource ?? 'shopify_api',
                ];
                continue;
            }

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
            $meta[] = [
                'sku' => $sku,
                'title' => $title,
                'quantity' => $quantity,
                'price' => $price,
                'variant_id' => null,
                'match_type' => 'custom',
            ];
        }

        if ($resolved === []) {
            $line = $lines->first();
            $resolved[] = [
                'title' => (string) ($line?->display_title ?: 'AliExpress order item'),
                'price' => number_format((float) ($line?->amount ?? 0), 2, '.', ''),
                'quantity' => max(1, (int) ($line?->quantity ?? 1)),
            ];
            $meta[] = [
                'sku' => (string) ($line?->sku ?? ''),
                'title' => (string) ($line?->display_title ?: 'AliExpress order item'),
                'quantity' => max(1, (int) ($line?->quantity ?? 1)),
                'price' => number_format((float) ($line?->amount ?? 0), 2, '.', ''),
                'variant_id' => null,
                'match_type' => 'custom',
            ];
        }

        $orderPayload['line_items'] = $resolved;

        $missing = collect($meta)->where('match_type', 'custom')->filter(fn ($r) => ($r['sku'] ?? '') !== '');
        if ($missing->isNotEmpty()) {
            $orderPayload['tags'] = trim((string) ($orderPayload['tags'] ?? '').', SKU Missing', ', ');
        }

        return [$orderPayload, $meta];
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
        $row = ShopifySku::firstForProductSku($sku);
        if ($row && ! empty($row->variant_id)) {
            return (int) $row->variant_id;
        }

        $config = $this->shopifyConfig();
        if (($config['store_url'] ?? '') === '' || ($config['token'] ?? '') === '') {
            return null;
        }

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
     * @return array{store_url: string, token: string, store_key: string}
     */
    protected function shopifyConfig(): array
    {
        $settings = MarketplaceSyncSettings::getFor('aliexpress');
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return app(ShopifyStoreSelector::class)->getConfigForStore($storeKey);
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
