<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$api = app(App\Services\AliExpressApiService::class);
$pull = app(App\Services\MarketplaceManager\AliexpressOrderDetailService::class)->fetchAndPersistOrderDetail($orderId);
$line = App\Models\AliexpressOrderMetric::query()->where('order_id', $orderId)->first();
$order = is_array($line->raw_payload['order'] ?? null) ? $line->raw_payload['order'] : [];

$children = $order['child_order_list']['global_aeop_tp_child_order_dto'] ?? $order['child_order_list'] ?? [];
if (isset($children['product_id'])) {
    $children = [$children];
}

$keys = [];
$walk = function ($data, $prefix = '') use (&$walk, &$keys) {
    if (! is_array($data)) {
        return;
    }
    foreach ($data as $k => $v) {
        $path = $prefix === '' ? $k : $prefix.'.'.$k;
        if (! is_array($v)) {
            $keys[$path] = $v;
        } elseif (count($v) <= 8) {
            $walk($v, $path);
        }
    }
};
$walk($order);
$walk($children[0] ?? []);

$interesting = [];
foreach ($keys as $path => $value) {
    if (preg_match('/fee|amount|pay|commission|tax|escrow|loan|discount|promotion|settle|price|total/i', $path)) {
        $interesting[$path] = $value;
    }
}

ksort($interesting);
echo json_encode([
    'pull_success' => $pull['success'] ?? false,
    'interesting_fields' => $interesting,
    'child0' => $children[0] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
