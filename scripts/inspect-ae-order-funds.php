<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$line = App\Models\AliexpressOrderMetric::query()->where('order_id', $orderId)->first();
$raw = is_array($line?->raw_payload) ? $line->raw_payload : [];
$order = is_array($raw['order'] ?? null) ? $raw['order'] : $raw;

$pick = function ($data, string $prefix = '') use (&$pick, &$hits) {
    if (! is_array($data)) {
        return;
    }
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        if (is_string($key) && preg_match('/fee|amount|pay|commission|tax|escrow|loan|discount|promotion|logistics_amount|adjust/i', $key)) {
            $hits[] = ['path' => $path, 'value' => $value];
        }
        if (is_array($value) && count($value) < 30) {
            $pick($value, $path);
        }
    }
};

$hits = [];
$pick($order);

$lines = App\Models\AliexpressOrderMetric::query()->where('order_id', $orderId)->get();
$detail = app(App\Services\MarketplaceManager\AliexpressDetailFormatter::class)->formatOrder($order, $lines, $line);
$payload = app(App\Services\MarketplaceManager\AliexpressDetailFormatter::class)
    ->buildShopifyOrderPayload($detail, $lines, ['aliexpress']);

echo json_encode([
    'fund_fields_in_raw' => $hits,
    'formatted_funds' => $detail['funds'] ?? null,
    'formatted_payment' => $detail['payment'] ?? null,
    'formatted_shipping' => $detail['shipping'] ?? null,
    'shopify_payload_addresses' => [
        'shipping_address' => $payload['shipping_address'] ?? null,
        'billing_address' => $payload['billing_address'] ?? null,
    ],
    'shopify_line_items' => $payload['line_items'] ?? null,
    'receipt_address' => $order['receipt_address'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
