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
    'product_sku|810099499551_19269239_12',
    'GTIN|810199692883',
    'EAN|810199692883',
];

foreach ($refs as $ref) {
    foreach ([null, 2851] as $shopId) {
        $params = ['product_references' => $ref, 'max' => 5];
        if ($shopId !== null) {
            $params['shop_id'] = $shopId;
        }
        $r = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products", $params);
        $label = $shopId === null ? 'operator' : "shop {$shopId}";
        echo "=== {$ref} ({$label}) HTTP {$r->status()} ===\n";
        foreach ($r->json('products') ?? [] as $p) {
            echo json_encode([
                'product_sku' => $p['product_sku'] ?? null,
                'shop_sku' => $p['shop_sku'] ?? null,
                'category_code' => $p['category_code'] ?? null,
                'category_label' => $p['category_label'] ?? null,
                'data_origin' => $p['data_origin'] ?? null,
                'sources' => $p['sources'] ?? null,
            ], JSON_PRETTY_PRINT)."\n";
        }
        if (($r->json('products') ?? []) === []) {
            echo "(empty)\n";
        }
        echo "\n";
    }
}

// H11 parent chain for Home Entertainment Accessories
$r = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/hierarchies", [
    'hierarchy' => 'Home Entertainment Accessories',
    'max_level' => 3,
]);
echo "=== H11 Home Entertainment Accessories ===\n";
foreach ($r->json('hierarchies') ?? [] as $h) {
    echo "  L{$h['level']}: ".($h['code'] ?? '?').' | '.($h['label'] ?? '?').' parent='.($h['parent_code'] ?? '')."\n";
}
