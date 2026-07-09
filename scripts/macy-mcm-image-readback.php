<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'PL 1002';
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$upc = trim((string) (DB::table('macys_price_data')->where('sku', $sku)->value('upc') ?? ''));

echo "=== MCM image attrs for {$sku} (UPC {$upc}) ===\n\n";

foreach ([
    'operator UPC no shop' => ['product_references' => "UPC|{$upc}", 'max' => 1, 'all_operator_attributes' => 'true'],
    'seller shop_sku' => ['shop_id' => (int) config('services.macy.shop_id', 2851), 'product_references' => 'shop_sku|'.rawurlencode($sku), 'max' => 1, 'all_operator_attributes' => 'true'],
    'seller product_sku' => ['shop_id' => (int) config('services.macy.shop_id', 2851), 'product_references' => 'product_sku|'.rawurlencode((string) (DB::table('macys_price_data')->where('sku', $sku)->value('product_sku') ?? '')), 'max' => 1, 'all_operator_attributes' => 'true'],
] as $label => $params) {
    if (str_contains($label, 'product_sku') && trim((string) end(explode('|', $params['product_references']))) === '') {
        continue;
    }
    $r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])->get("{$base}/api/products", $params);
    echo "--- {$label} HTTP {$r->status()} ---\n";
    $prod = $r->json('products.0') ?? [];
    if ($prod === []) {
        echo "(empty)\n\n";
        continue;
    }
    echo 'product_sku: '.($prod['product_sku'] ?? '?').' shop_sku: '.($prod['shop_sku'] ?? 'null')."\n";
    foreach (($prod['product_attributes'] ?? []) as $attr) {
        $code = (string) ($attr['code'] ?? '');
        if (preg_match('/image/i', $code)) {
            echo "  {$code}: ".mb_substr((string) ($attr['value'] ?? ''), 0, 120)."\n";
        }
    }
    echo "\n";
}
