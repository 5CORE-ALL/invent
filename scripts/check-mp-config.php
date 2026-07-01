<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = $app->make(App\Services\Support\MarketplaceApiConfigService::class);
$keys = ['amazon', 'walmart', 'doba', 'faire', 'shein', 'aliexpress', 'tiktok', 'tiktok2', 'newegg', 'topdawg', 'shopify_b5c', 'shopify_main', 'shopify_pls', 'temu', 'reverb', 'wayfair', 'bestbuy', 'ebay', 'ebay2', 'ebay3', 'macy'];
foreach ($keys as $k) {
    echo $k . ': ' . ($s->isConfigured($k) ? 'yes' : 'no') . PHP_EOL;
}
