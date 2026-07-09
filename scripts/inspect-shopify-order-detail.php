<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$shopifyOrderId = $argv[1] ?? '7061669413101';
$selector = app(App\Services\ShopifyStoreSelector::class);
$config = $selector->getConfigForStore(
    (string) (App\Models\MarketplaceSyncSettings::getFor('aliexpress')['order']['shopify_store'] ?? 'main')
);

$response = Illuminate\Support\Facades\Http::withHeaders([
    'X-Shopify-Access-Token' => $config['token'],
])->timeout(30)->get('https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId.'.json');

echo json_encode([
    'status' => $response->status(),
    'shipping_address' => $response->json('order.shipping_address'),
    'billing_address' => $response->json('order.billing_address'),
    'customer' => $response->json('order.customer'),
    'total_price' => $response->json('order.total_price'),
    'subtotal_price' => $response->json('order.subtotal_price'),
    'total_tax' => $response->json('order.total_tax'),
    'note' => $response->json('order.note'),
    'note_attributes' => $response->json('order.note_attributes'),
    'transactions' => $response->json('order.transactions'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
