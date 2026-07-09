<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$url = "{$base}/api/products/attributes?all_operator_attributes=true";

$variants = [
    'raw Authorization header' => ['Authorization' => $key],
    'Bearer prefix' => ['Authorization' => 'Bearer '.$key],
];

foreach ($variants as $label => $headers) {
    $headers['Accept'] = 'application/json';
    $r = Http::withoutVerifying()->withHeaders($headers)->timeout(30)->get($url);
    echo "{$label}: HTTP {$r->status()}\n";
}

$shopId = config('services.macy.shop_id');
if ($shopId) {
    $r = Http::withoutVerifying()->withHeaders(['Authorization' => $key, 'Accept' => 'application/json'])
        ->get($url, ['shop_id' => (int) $shopId]);
    echo "with shop_id={$shopId}: HTTP {$r->status()}\n";
} else {
    echo "MACY_SHOP_ID not set (optional)\n";
}

echo "Key length: ".strlen($key)." chars\n";
