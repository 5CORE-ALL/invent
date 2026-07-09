<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/offers", ['shop_sku' => $sku]);
echo "offers by shop_sku:\n";
foreach ($r->json('offers') ?? [] as $o) {
    echo json_encode([
        'shop_sku' => $o['shop_sku'] ?? null,
        'sku' => $o['sku'] ?? null,
        'category_code' => $o['category_code'] ?? null,
        'category_label' => $o['category_label'] ?? null,
        'product_sku' => $o['product_sku'] ?? null,
        'active' => $o['active'] ?? null,
        'state' => $o['state'] ?? null,
    ], JSON_PRETTY_PRINT)."\n";
}
