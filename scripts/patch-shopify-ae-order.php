<?php

$root = '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AliexpressOrderMetric;
use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\AliexpressDetailFormatter;
use App\Services\MarketplaceManager\AliexpressOrderDetailService;
use App\Services\ShopifyStoreSelector;
use Illuminate\Support\Facades\Http;

$orderId = $argv[1] ?? '8212289062955038';
$shopifyOrderId = $argv[2] ?? null;

$line = AliexpressOrderMetric::query()->where('order_id', $orderId)->first();
if (! $line) {
    echo "Order {$orderId} not found\n";
    exit(1);
}

$shopifyOrderId = $shopifyOrderId ?: $line->shopify_order_id;
if (! $shopifyOrderId) {
    echo "No Shopify order id for {$orderId}\n";
    exit(1);
}

app(AliexpressOrderDetailService::class)->fetchAndPersistOrderDetail($orderId);
$line->refresh();
$lines = AliexpressOrderMetric::query()->where('order_id', $orderId)->get();
$order = app(AliexpressOrderDetailService::class)->resolveOrderRoot($line);
$formatter = app(AliexpressDetailFormatter::class);
$detail = $formatter->formatOrder($order, $lines, $line);
$payload = $formatter->buildShopifyOrderPayload($detail, $lines, ['aliexpress']);

$settings = MarketplaceSyncSettings::getFor('aliexpress');
$config = app(ShopifyStoreSelector::class)->getConfigForStore((string) ($settings['order']['shopify_store'] ?? 'main'));
$base = 'https://'.$config['store_url'].'/admin/api/2024-01/orders/'.$shopifyOrderId;
$headers = [
    'X-Shopify-Access-Token' => $config['token'],
    'Content-Type' => 'application/json',
];

$update = [
    'order' => array_filter([
        'id' => (int) $shopifyOrderId,
        'note' => $payload['note'] ?? null,
        'note_attributes' => $payload['note_attributes'] ?? null,
        'shipping_address' => $payload['shipping_address'] ?? null,
        'billing_address' => $payload['billing_address'] ?? $payload['shipping_address'] ?? null,
    ], fn ($value) => $value !== null),
];

sleep(2);
$response = Http::withHeaders($headers)->timeout(60)->put($base.'.json', $update);
echo 'update_status='.$response->status()."\n";
if (! $response->successful()) {
    echo mb_substr($response->body(), 0, 500)."\n";
    exit(1);
}

$customerPaid = $detail['payment']['total_paid'] ?? null;
$currency = $detail['payment']['currency'] ?? 'USD';
if ($customerPaid !== null && (float) $customerPaid > 0) {
    sleep(2);
    $txn = Http::withHeaders($headers)->timeout(60)->post($base.'/transactions.json', [
        'transaction' => [
            'kind' => 'sale',
            'status' => 'success',
            'amount' => number_format((float) $customerPaid, 2, '.', ''),
            'gateway' => 'aliexpress',
            'currency' => $currency,
            'source' => 'external',
        ],
    ]);
    echo 'transaction_status='.$txn->status()."\n";
    if (! $txn->successful()) {
        echo mb_substr($txn->body(), 0, 500)."\n";
    }
}

echo json_encode([
    'shopify_order_id' => (string) $shopifyOrderId,
    'funds' => $detail['funds'] ?? null,
    'payment' => $detail['payment'] ?? null,
    'shipping_address' => $payload['shipping_address'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
