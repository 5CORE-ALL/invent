<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

$refs = [
    'UPC|810199692883',
    'UPC|810099499551',
    'GTIN|810199692883',
    'variant_group_code|9212627353837',
    'shop_sku|MS DBL G WH',
    'shop_sku|MS DBL G WH 2 PCS',
];

foreach ($refs as $ref) {
    $r = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products", [
        'product_references' => $ref,
        'max' => 3,
        'all_operator_attributes' => 'true',
    ]);
    echo "=== {$ref} HTTP {$r->status()} ===\n";
    foreach ($r->json('products') ?? [] as $p) {
        echo json_encode([
            'product_sku' => $p['product_sku'] ?? null,
            'shop_sku' => $p['shop_sku'] ?? null,
            'category_code' => $p['category_code'] ?? null,
            'category_label' => $p['category_label'] ?? null,
        ], JSON_PRETTY_PRINT)."\n";
        foreach ((array) ($p['data'] ?? $p['product_attributes'] ?? []) as $attr) {
            if (! is_array($attr)) continue;
            if (strcasecmp((string)($attr['code'] ?? ''), 'categoryCode') === 0) {
                echo '  categoryCode attr: '.json_encode($attr['value'] ?? null)."\n";
            }
        }
    }
    if (($r->json('products') ?? []) === []) echo "(empty)\n";
    echo "\n";
}

// CM export sources with attributes?
foreach ([
    '/api/mcm/products/sources/export',
    '/api/mcm/products/sources/status/export',
] as $path) {
    $r = Http::withoutVerifying()->withHeaders($headers)->get($base.$path, [
        'shop_id' => 2851,
        'provider_unique_identifier' => 'MS DBL G WH 2 PCS',
    ]);
    echo "=== {$path} HTTP {$r->status()} ===\n";
    echo mb_substr($r->body(), 0, 2000)."\n\n";
}
