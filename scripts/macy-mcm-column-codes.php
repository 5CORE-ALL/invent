<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$hierarchy = 'Home Entertainment Accessories';

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key, 'Accept' => 'application/json'])
    ->get("{$base}/api/products/attributes", [
        'hierarchy' => $hierarchy,
        'all_operator_attributes' => 'true',
    ]);

echo "Key PM11 codes for {$hierarchy}:\n";
foreach ($r->json('attributes') ?? [] as $attr) {
    $code = (string) ($attr['code'] ?? '');
    if (preg_match('/^(shopSku|categoryCode|productSku|fnb\d+|shop-sku|category-code)$/i', $code)) {
        echo "  {$code} | ".($attr['label'] ?? '')."\n";
    }
}
