<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$line = App\Models\AliexpressOrderMetric::query()
    ->where('order_id', $orderId)
    ->first();

if (! $line) {
    echo "not found\n";
    exit(1);
}

$raw = is_array($line->raw_payload) ? $line->raw_payload : [];
$order = is_array($raw['order'] ?? null) ? $raw['order'] : $raw;
$addr = is_array($order['receipt_address'] ?? null) ? $order['receipt_address'] : [];
$buyer = is_array($order['buyer_info'] ?? null) ? $order['buyer_info'] : [];

echo json_encode([
    'buyer_info' => $buyer,
    'receipt_address_keys' => array_keys($addr),
    'contact_person' => $addr['contact_person'] ?? null,
    'email_fields' => [
        'receipt_email' => $addr['email'] ?? null,
        'buyer_email' => $buyer['email'] ?? null,
        'order_email' => $order['email'] ?? null,
    ],
    'buyer_signer_fullname' => $order['buyer_signer_fullname'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
