<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';

$endpoints = [
    'products shop_sku' => ['shop_sku' => $sku],
    'products shop_sku + shop_id' => ['shop_sku' => $sku, 'shop_id' => 2851],
    'products sku' => ['sku' => $sku],
    'offers sku + shop_id' => ['sku' => $sku, 'shop_id' => 2851],
];

foreach ($endpoints as $label => $q) {
    $r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])->get("{$base}/api/products", $q);
    echo "=== products {$label}: HTTP {$r->status()} ===\n";
    echo mb_substr($r->body(), 0, 500)."\n\n";
    $r2 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])->get("{$base}/api/offers", $q);
    echo "=== offers {$label}: HTTP {$r2->status()} count ".count($r2->json('offers') ?? [])." ===\n";
    foreach ($r2->json('offers') ?? [] as $o) {
        if (strcasecmp((string) ($o['shop_sku'] ?? ''), $sku) === 0) {
            echo json_encode($o, JSON_PRETTY_PRINT)."\n";
        }
    }
    echo "\n";
}

// Paginate offers looking for exact shop_sku (max 5 pages)
$page = 0;
$max = (int) ($argv[2] ?? 5);
$offset = 0;
while ($page < $max) {
    $r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
        ->get("{$base}/api/offers", ['max' => 100, 'offset' => $offset, 'shop_id' => 2851]);
    $offers = $r->json('offers') ?? [];
    if ($offers === []) {
        break;
    }
    foreach ($offers as $o) {
        if (strcasecmp((string) ($o['shop_sku'] ?? ''), $sku) === 0) {
            echo "FOUND in offers page {$page}:\n".json_encode([
                'category_code' => $o['category_code'] ?? null,
                'product_sku' => $o['product_sku'] ?? null,
            ], JSON_PRETTY_PRINT)."\n";
            exit(0);
        }
    }
    $offset += 100;
    $page++;
}
echo "Not found in first ".($page * 100)." offers (shop_id=2851)\n";
