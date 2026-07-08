<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

$sku = $argv[1] ?? 'GSTOOL BLK';

echo "=== Macy MCM product + hierarchy probe: {$sku} ===\n\n";

foreach ([
    'by shop-sku' => ['shop_sku' => $sku, 'max' => 5],
    'by product_sku' => ['product_sku' => '810099499711_17594377_12', 'max' => 5],
] as $label => $query) {
    $r = Http::withoutVerifying()->withHeaders($headers)->timeout(60)
        ->get("{$base}/api/products", $query);
    echo "GET /api/products ({$label}): HTTP {$r->status()}\n";
    if ($r->successful()) {
        $products = $r->json('products') ?? $r->json('data') ?? [];
        foreach ((array) $products as $p) {
            if (! is_array($p)) {
                continue;
            }
            echo '  product_sku: '.($p['product_sku'] ?? $p['product_sku'] ?? '?')."\n";
            echo '  shop_sku: '.($p['shop_sku'] ?? '?')."\n";
            echo '  category_code: '.($p['category_code'] ?? $p['category']['code'] ?? '?')."\n";
            echo '  category_label: '.($p['category_label'] ?? $p['category']['label'] ?? '?')."\n";
        }
        if ($products === []) {
            echo '  (no products) '.mb_substr($r->body(), 0, 200)."\n";
        }
    } else {
        echo '  '.mb_substr($r->body(), 0, 200)."\n";
    }
    echo "\n";
}

$cat = 'Home Entertainment Accessories';
$h = Http::withoutVerifying()->withHeaders($headers)->timeout(60)
    ->get("{$base}/api/hierarchies", ['hierarchy' => $cat, 'max_level' => 0]);
echo "GET /api/hierarchies?hierarchy={$cat}: HTTP {$h->status()}\n";
if ($h->successful()) {
    $data = $h->json('hierarchies') ?? $h->json();
    echo mb_substr(json_encode($data, JSON_PRETTY_PRINT), 0, 1500)."\n";
} else {
    echo mb_substr($h->body(), 0, 300)."\n";
}
