<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$sku = $argv[1] ?? 'GSTOOL BLK';

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/offers", ['sku' => $sku]);
$offer = $r->json('offers.0') ?? [];
echo "offer shop_sku: ".($offer['shop_sku'] ?? '?')."\n";
echo "offer product_sku: ".($offer['product_sku'] ?? '?')."\n";
echo "offer category_code: ".($offer['category_code'] ?? '?')."\n";
echo "offer active: ".json_encode($offer['active'] ?? null)."\n";

$p = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products", ['product_references' => 'shop_sku|'.rawurlencode($sku), 'max' => 1]);
echo "\nproduct by shop_sku HTTP {$p->status()}\n";
echo mb_substr($p->body(), 0, 1500)."\n";

$p2 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products", ['product_references' => 'product_sku|810099499711_17594377_12', 'max' => 1]);
echo "\nproduct by product_sku HTTP {$p2->status()}\n";
echo mb_substr($p2->body(), 0, 1500)."\n";
