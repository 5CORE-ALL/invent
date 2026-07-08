<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];
$shopId = (int) config('services.macy.shop_id', 2851);

$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';
$importId = (int) ($argv[2] ?? 2265575);
$upc = '810199692883';
$pid = '810099499551_19269239_12';

echo "=== P42 import #{$importId} ===\n";
$p42 = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products/imports/{$importId}", ['shop_id' => $shopId]);
echo json_encode($p42->json(), JSON_PRETTY_PRINT)."\n\n";

foreach ([
    ['UPC', $upc],
    ['shop_sku', $sku],
    ['product_sku', $pid],
] as [$type, $value]) {
    echo "=== GET /api/products ref {$type}|{$value} (shop_id={$shopId}) ===\n";
    $r = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products", [
        'shop_id' => $shopId,
        'product_references' => $type.'|'.rawurlencode($value),
        'max' => 3,
        'all_operator_attributes' => 'true',
    ]);
    echo "HTTP {$r->status()}\n";
    $products = $r->json('products') ?? [];
    if ($products === []) {
        echo "(no seller products)\n\n";
        continue;
    }
    foreach ($products as $prod) {
        if (! is_array($prod)) {
            continue;
        }
        echo 'shop_sku: '.($prod['shop_sku'] ?? '?')."\n";
        echo 'product_sku: '.($prod['product_sku'] ?? '?')."\n";
        echo 'category_code: '.($prod['category_code'] ?? '?')."\n";
        foreach (($prod['product_attributes'] ?? []) as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $code = (string) ($attr['code'] ?? '');
            if (preg_match('/^fnb\d+$/i', $code)) {
                echo "  {$code}: ".mb_substr((string) ($attr['value'] ?? ''), 0, 150)."\n";
            }
        }
    }
    echo "\n";
}

echo "=== CM11 status for {$sku} ===\n";
$cm11 = Http::withoutVerifying()->withHeaders($headers)->timeout(120)->get("{$base}/api/mcm/products/sources/status/export", [
    'shop_id' => $shopId,
    'provider_unique_identifier' => [$sku],
]);
if ($cm11->successful()) {
    $items = $cm11->json();
    if (is_array($items)) {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $puid = (string) ($item['provider_unique_identifier'] ?? '');
            if (strcasecmp($puid, $sku) !== 0) {
                continue;
            }
            echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        }
    }
} else {
    echo "HTTP {$cm11->status()}\n".mb_substr($cm11->body(), 0, 500)."\n";
}
