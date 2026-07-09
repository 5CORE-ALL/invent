<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$shopId = (int) config('services.macy.shop_id', 2851);
$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';

foreach ([
    '/api/mcm/products/sources/export',
    '/api/mcm/products/export',
] as $path) {
    echo "=== {$path} ===\n";
    $r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])->timeout(120)->get($base.$path, [
        'shop_id' => $shopId,
        'provider_unique_identifier' => [$sku],
    ]);
    echo "HTTP {$r->status()}\n";
    echo mb_substr($r->body(), 0, 6000)."\n\n";
}

echo "=== PM11 fnb with_roles ===\n";
$pm11 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])->get("{$base}/api/products/attributes", [
    'hierarchy' => 'Home Entertainment Accessories',
    'all_operator_attributes' => 'true',
    'with_roles' => 'true',
    'shop_id' => $shopId,
]);
foreach ($pm11->json('attributes') ?? [] as $a) {
    $code = (string) ($a['code'] ?? '');
    if (! preg_match('/^(fnb[1-5]|UPC|inHouse)/i', $code)) {
        continue;
    }
    echo json_encode([
        'code' => $code,
        'roles' => $a['roles'] ?? [],
        'requirement_level' => $a['requirement_level'] ?? null,
        'transformations' => $a['transformations'] ?? null,
    ], JSON_PRETTY_PRINT)."\n";
}
