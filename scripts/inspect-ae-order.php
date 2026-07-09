<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$line = App\Models\AliexpressOrderMetric::query()
    ->where('order_id', $orderId)
    ->orderBy('id')
    ->first();

if (! $line) {
    echo "No local row for order {$orderId}\n";
    exit(1);
}

$api = app(App\Services\AliExpressApiService::class);
$pull = app(App\Services\MarketplaceManager\AliexpressOrderDetailService::class)->fetchAndPersistOrderDetail($orderId);
echo 'pull='.json_encode(['success' => $pull['success'] ?? false, 'message' => $pull['message'] ?? null])."\n";

$line->refresh();
$raw = $line->raw_payload;
$order = is_array($raw['order'] ?? null) ? $raw['order'] : $raw;

$children = $order['child_order_list']['global_aeop_tp_child_order_dto']
    ?? $order['child_order_list']
    ?? [];
if (isset($children['product_id']) || isset($children['sku_code'])) {
    $children = [$children];
}

echo "child_count=".count($children)."\n";
foreach ($children as $i => $child) {
    $child = is_array($child) ? $child : [];
    echo "child[{$i}] sku=".($child['sku_code'] ?? '?')."\n";
    foreach (['product_img_url', 'snapshot_small_photo_path', 'product_image', 'sku_image'] as $k) {
        if (! empty($child[$k])) {
            echo "  {$k}=".substr((string) $child[$k], 0, 160)."\n";
        }
    }
    if (! empty($child['product_attributes'])) {
        echo '  product_attributes_len='.strlen((string) $child['product_attributes'])."\n";
    }
}

$lines = App\Models\AliexpressOrderMetric::query()->where('order_id', $orderId)->get();
$detail = app(App\Services\MarketplaceManager\AliexpressDetailFormatter::class)->formatOrder($order, $lines, $line);
foreach ($detail['line_items'] ?? [] as $item) {
    echo 'formatted sku='.($item['sku'] ?? '?').' image='.($item['image'] ?? 'NULL')."\n";
}
