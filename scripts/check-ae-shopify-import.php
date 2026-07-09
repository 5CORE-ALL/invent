<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$lines = App\Models\AliexpressOrderMetric::query()
    ->where('order_id', $orderId)
    ->orderBy('id')
    ->get();

if ($lines->isEmpty()) {
    echo json_encode(['found' => false, 'message' => 'No local rows'], JSON_PRETTY_PRINT)."\n";
    exit(1);
}

$primary = $lines->first();
$result = [
    'found' => true,
    'aliexpress_order_id' => $orderId,
    'rows' => $lines->map(fn ($l) => [
        'id' => $l->id,
        'sku' => $l->sku,
        'shopify_order_id' => $l->shopify_order_id,
        'import_status' => $l->import_status,
        'pushed_to_shopify_at' => $l->pushed_to_shopify_at?->toIso8601String(),
    ])->values()->all(),
];

$shopifyOrderId = (string) ($primary->shopify_order_id ?? '');
if ($shopifyOrderId !== '') {
    $selector = app(App\Services\ShopifyStoreSelector::class);
    $config = $selector->getConfigForStore(
        (string) (App\Models\MarketplaceSyncSettings::getFor('aliexpress')['order']['shopify_store'] ?? 'main')
    );
    $url = 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json';
    try {
        $response = Illuminate\Support\Facades\Http::withHeaders([
            'X-Shopify-Access-Token' => $config['token'],
        ])->timeout(30)->get($url);
        $result['shopify_verify'] = [
            'http_status' => $response->status(),
            'found' => $response->successful(),
            'order_name' => $response->json('order.name'),
            'financial_status' => $response->json('order.financial_status'),
            'fulfillment_status' => $response->json('order.fulfillment_status'),
            'email' => $response->json('order.email'),
            'tags' => $response->json('order.tags'),
            'admin_url' => 'https://'.$config['store_url'].'/admin/orders/'.$shopifyOrderId,
        ];
        if ($response->successful()) {
            $fulfillments = $response->json('order.fulfillments') ?? [];
            $result['shopify_verify']['tracking'] = collect($fulfillments)
                ->pluck('tracking_number')
                ->filter()
                ->values()
                ->all();
        } else {
            $result['shopify_verify']['body'] = mb_substr($response->body(), 0, 300);
        }
    } catch (Throwable $e) {
        $result['shopify_verify'] = ['error' => $e->getMessage()];
    }
} else {
    $result['shopify_verify'] = ['found' => false, 'message' => 'No shopify_order_id saved locally yet'];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
exit($shopifyOrderId !== '' ? 0 : 2);
