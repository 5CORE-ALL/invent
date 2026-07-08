<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
$pmTitle = trim((string) (DB::table('product_master')->where('sku', $sku)->value('title60') ?? ''));

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$shopId = (int) config('services.macy.shop_id', 2851);
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

$price = DB::table('macys_price_data')->where('sku', $sku)->first();
$upc = trim((string) ($price->upc ?? ''));
$pid = trim((string) ($price->product_sku ?? ''));

echo "SKU: {$sku}\nPM title60: {$pmTitle}\nUPC: {$upc}\nPID: {$pid}\n\n";

// Connect title
$oauth = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
    'audience' => config('services.macy.company_id'),
]);
$token = $oauth->json('access_token');
$connectTitle = '';
$list = Http::withoutVerifying()->withToken($token)->withHeaders([
    'Accept' => 'application/json',
    'channel_id' => (string) config('services.macy.company_id'),
])->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);
foreach ($list->json('data') ?? [] as $p) {
    if (strcasecmp((string) ($p['id'] ?? ''), $sku) !== 0) {
        continue;
    }
    foreach ((array) ($p['titles'] ?? []) as $t) {
        if (is_array($t) && trim((string) ($t['value'] ?? '')) !== '') {
            $connectTitle = trim((string) $t['value']);
            break 2;
        }
    }
}
echo "=== Mirakl Connect ===\n";
echo 'title: '.$connectTitle."\n";
echo 'match PM: '.(strcasecmp($connectTitle, $pmTitle) === 0 ? 'YES' : 'NO')."\n\n";

echo "=== Macy MCM (seller catalog) ===\n";
$found = false;
foreach ([
    ['shop_sku', $sku],
    ['product_sku', $pid],
    ['UPC', $upc],
] as [$type, $ref]) {
    if ($ref === '') {
        continue;
    }
    $r = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products", [
        'shop_id' => $shopId,
        'product_references' => $type.'|'.rawurlencode($ref),
        'max' => 1,
        'all_operator_attributes' => 'true',
    ]);
    $prod = $r->json('products.0') ?? [];
    if ($prod === []) {
        echo "{$type}|{$ref}: (no seller product)\n";
        continue;
    }
    $found = true;
    $mcmName = '';
    foreach (($prod['product_attributes'] ?? []) as $attr) {
        if (! is_array($attr)) {
            continue;
        }
        if (strcasecmp((string) ($attr['code'] ?? ''), 'productName') === 0) {
            $mcmName = trim((string) ($attr['value'] ?? ''));
        }
    }
    echo "{$type}|{$ref}:\n";
    echo '  shop_sku: '.($prod['shop_sku'] ?? '?')."\n";
    echo '  productName: '.($mcmName !== '' ? $mcmName : '(not in attrs)')."\n";
    echo '  match PM: '.($mcmName !== '' && strcasecmp($mcmName, $pmTitle) === 0 ? 'YES' : 'NO')."\n";
}

if (! $found) {
    echo "\n(No MCM seller product returned by API for this SKU.)\n";
}

// CM11 status
$cm11 = Http::withoutVerifying()->withHeaders($headers)->timeout(60)
    ->get("{$base}/api/mcm/products/sources/status/export", [
        'shop_id' => $shopId,
        'provider_unique_identifier' => [$sku],
    ]);
echo "\n=== CM11 ===\n";
foreach ($cm11->json() ?? [] as $item) {
    if (! is_array($item)) {
        continue;
    }
    if (strcasecmp((string) ($item['provider_unique_identifier'] ?? ''), $sku) !== 0) {
        continue;
    }
    echo 'status: '.($item['status'] ?? '?')."\n";
    if (! empty($item['errors'])) {
        echo 'errors: '.json_encode($item['errors'])."\n";
    }
}
