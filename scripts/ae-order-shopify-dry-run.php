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

$result = app(App\Services\MarketplaceManager\AliexpressOrderPushService::class)->previewShopifyPush($line);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
exit(empty($result['success']) ? 1 : 0);
