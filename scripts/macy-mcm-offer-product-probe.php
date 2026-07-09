<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

$shopSku = 'GSTOOL BLK';
$productSku = '810099499711_17594377_12';

echo "=== Offers / product references probe ===\n\n";

foreach ([
    'offers by shop_sku' => "{$base}/api/offers?shop_sku=".urlencode($shopSku),
    'offers by sku' => "{$base}/api/offers?sku=".urlencode($shopSku),
    'offers by product_sku' => "{$base}/api/offers?product_sku=".urlencode($productSku),
] as $label => $url) {
    $r = Http::withoutVerifying()->withHeaders($headers)->timeout(60)->get($url);
    echo "{$label}: HTTP {$r->status()}\n";
    echo mb_substr($r->body(), 0, 800)."\n\n";
}

$refs = json_encode([
    ['shop_sku' => $shopSku],
    ['product_sku' => $productSku],
]);
$r = Http::withoutVerifying()->withHeaders($headers)->timeout(60)
    ->get("{$base}/api/products", ['product_references' => $refs, 'max' => 5]);
echo "products by product_references: HTTP {$r->status()}\n";
echo mb_substr($r->body(), 0, 2000)."\n\n";

// PM11 import template columns hint
$r = Http::withoutVerifying()->withHeaders($headers)->timeout(60)
    ->get("{$base}/api/products/attributes", [
        'hierarchy' => 'Home Entertainment Accessories',
        'all_operator_attributes' => 'true',
    ]);
echo "PM11 attributes for hierarchy: HTTP {$r->status()}\n";
$attrs = $r->json('attributes') ?? [];
echo 'count: '.count($attrs)."\n";
foreach (array_slice($attrs, 0, 5) as $a) {
    echo '  '.($a['code'] ?? '?').' => '.($a['label'] ?? '?')."\n";
}
