<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$api = app(App\Services\AliExpressApiService::class);

$info = $api->getOrderInfo($orderId);
$receipt = $api->getOrderReceiptInfo($orderId);

$findAt = function ($data, string $prefix = '') use (&$findAt, &$hits) {
    if (is_string($data) && str_contains($data, '@')) {
        $hits[] = ['path' => $prefix, 'value' => $data];
        return;
    }
    if (! is_array($data)) {
        return;
    }
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        $findAt($value, $path);
    }
};

$hits = [];
$findAt($info['data'] ?? [], 'info');
$findAt($receipt['data'] ?? [], 'receipt');

echo json_encode([
    'strings_with_at' => $hits,
    'payment_type' => $info['data']['payment_type'] ?? null,
    'pay_type' => $info['data']['pay_type'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
