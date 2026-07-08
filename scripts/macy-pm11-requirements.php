<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');

$hierarchy = DB::table('macys_price_data')->where('sku', $sku)->value('category_code')
    ?? 'Home Entertainment Accessories';

echo "PM11 for SKU={$sku} hierarchy={$hierarchy}\n";
echo "Doc: https://developer.mirakl.com/content/product/mcm/rest/seller/openapi3/products/pm11\n\n";

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key, 'Accept' => 'application/json'])
    ->get("{$base}/api/products/attributes", [
        'hierarchy' => $hierarchy,
        'all_operator_attributes' => 'true',
    ]);

echo "HTTP {$r->status()}\n";
if (! $r->successful()) {
    echo mb_substr($r->body(), 0, 500)."\n";
    exit(1);
}

$attrs = $r->json('attributes') ?? [];
echo 'Total attributes: '.count($attrs)."\n\n";

echo "=== Identity columns (P41 CSV headers) ===\n";
foreach ($attrs as $a) {
    $code = (string) ($a['code'] ?? '');
    if (preg_match('/^(shopSku|categoryCode|productSku|productName|UPC|brand|pid)$/i', $code)) {
        echo "  {$code} | ".($a['label'] ?? '').' | '.($a['requirement_level'] ?? '?')."\n";
    }
}

echo "\n=== Features & Benefits (fnb*) ===\n";
for ($i = 1; $i <= 15; $i++) {
    foreach ($attrs as $a) {
        if (strcasecmp((string) ($a['code'] ?? ''), "fnb{$i}") !== 0) {
            continue;
        }
        $max = collect($a['type_parameters'] ?? [])->firstWhere('name', 'MAX_LENGTH')['value'] ?? '?';
        echo "  fnb{$i} | ".($a['requirement_level'] ?? '?')." | max={$max} | ".($a['label'] ?? '')."\n";
    }
}

foreach ($attrs as $a) {
    if (($a['requirement_level'] ?? '') !== 'REQUIRED') {
        continue;
    }
    echo '  '.($a['code'] ?? '?').' | '.($a['label'] ?? '')."\n";
}
