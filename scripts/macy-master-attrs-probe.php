<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

$sku = $argv[1] ?? 'PL 1002';
$upc = $argv[2] ?? '810047162827';

foreach ([
    "UPC|{$upc}",
    "shop_sku|{$sku}",
] as $ref) {
    echo "=== Operator master GET /api/products?product_references={$ref} (no shop_id) ===\n";
    $r = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products", [
        'product_references' => $ref,
        'max' => 3,
        'all_operator_attributes' => 'true',
    ]);
    foreach ($r->json('products') ?? [] as $p) {
        echo json_encode([
            'product_sku' => $p['product_sku'] ?? null,
            'shop_sku' => $p['shop_sku'] ?? null,
            'category_code' => $p['category_code'] ?? null,
        ], JSON_PRETTY_PRINT)."\n";
        $attrs = $p['product_attributes'] ?? $p['data'] ?? [];
        foreach ((array) $attrs as $a) {
            if (! is_array($a)) continue;
            $code = (string) ($a['code'] ?? '');
            if (preg_match('/^(categoryCode|fnb|taxCode|fnb20|productName|brand|msrp|mainImage)/i', $code)) {
                $val = is_array($a['value'] ?? null) ? json_encode($a['value']) : (string) ($a['value'] ?? '');
                echo "  {$code}: ".mb_substr($val, 0, 120)."\n";
            }
        }
    }
    if (($r->json('products') ?? []) === []) echo "(empty)\n";
    echo "\n";
}

// Seller product with shop_id
echo "=== Seller product shop_id=2851 UPC ===\n";
$r2 = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products", [
    'shop_id' => 2851,
    'product_references' => "UPC|{$upc}",
    'max' => 1,
    'all_operator_attributes' => 'true',
]);
foreach ($r2->json('products') ?? [] as $p) {
    echo json_encode(['shop_sku' => $p['shop_sku'] ?? null, 'category' => $p['category_code'] ?? null], JSON_PRETTY_PRINT)."\n";
    foreach (($p['product_attributes'] ?? []) as $a) {
        $code = (string) ($a['code'] ?? '');
        if (preg_match('/fnb20-90|taxCode|categoryCode/i', $code)) {
            echo "  {$code}: ".json_encode($a['value'] ?? null)."\n";
        }
    }
}
if (($r2->json('products') ?? []) === []) echo "(empty seller product)\n";
