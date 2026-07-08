<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

echo "=== Macy MCM + listing check: {$sku} ===\n\n";

$row = DB::table('macys_price_data')->where('sku', $sku)
    ->orWhere('offer_sku', $sku)->orWhere('product_sku', $sku)->first();
if ($row) {
    echo "macys_price_data:\n";
    echo "  activated: ".json_encode($row->activated ?? null)."\n";
    echo "  inactivity_reason: ".($row->inactivity_reason ?? 'n/a')."\n";
    echo "  category: ".($row->category_code ?? 'n/a')."\n";
    echo "  product_sku: ".($row->product_sku ?? 'n/a')."\n\n";
} else {
    echo "macys_price_data: no row for this SKU\n\n";
}

if ($key === '') {
    echo "MCM API key not set\n";
    exit(0);
}

$offer = Http::withoutVerifying()->withHeaders($headers)->timeout(60)
    ->get("{$base}/api/offers", ['sku' => $sku]);
echo "MCM offers by sku: HTTP {$offer->status()}\n";
$offerRow = $offer->json('offers.0') ?? [];
if ($offerRow !== []) {
    echo "  shop_sku: ".($offerRow['shop_sku'] ?? '?')."\n";
    echo "  product_sku: ".($offerRow['product_sku'] ?? '?')."\n";
    echo "  active: ".json_encode($offerRow['active'] ?? null)."\n";
    echo "  category: ".($offerRow['category_code'] ?? '?')."\n";
    echo "  state: ".($offerRow['state_code'] ?? $offerRow['offer_state'] ?? '?')."\n";
} else {
    echo '  '.mb_substr($offer->body(), 0, 300)."\n";
}

$productSku = trim((string) ($row->product_sku ?? $offerRow['product_sku'] ?? ''));
$refs = $productSku !== '' ? 'product_sku|'.rawurlencode($productSku) : 'shop_sku|'.rawurlencode($sku);
$product = Http::withoutVerifying()->withHeaders($headers)->timeout(60)
    ->get("{$base}/api/products", ['product_references' => $refs, 'max' => 1]);
echo "\nMCM product ({$refs}): HTTP {$product->status()}\n";
$prod = $product->json('products.0') ?? [];
if ($prod === []) {
    echo "  (no operator catalog product — Connect-only or not synced to MCM yet)\n";
} else {
    echo "  product_sku: ".($prod['product_sku'] ?? '?')."\n";
    echo "  category: ".($prod['category_code'] ?? '?')."\n";
    foreach (($prod['product_attributes'] ?? []) as $attr) {
        if (! is_array($attr)) {
            continue;
        }
        $code = (string) ($attr['code'] ?? '');
        if (preg_match('/^fnb\d+$/i', $code)) {
            echo "  {$code}: ".mb_substr((string) ($attr['value'] ?? ''), 0, 100)."\n";
        }
    }
}

$companyId = trim((string) config('services.macy.company_id'));
$token = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
    'audience' => $companyId,
])->json('access_token');

echo "\nConnect catalog (macys channel) F&B 1:\n";
$list = Http::withoutVerifying()->withToken($token)->withHeaders(['Accept' => 'application/json', 'channel_id' => $companyId])
    ->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);
foreach (($list->json('data') ?? []) as $p) {
    if (strcasecmp((string) ($p['id'] ?? ''), $sku) !== 0) {
        continue;
    }
    foreach (($p['attributes'] ?? []) as $attr) {
        if (strcasecmp((string) ($attr['id'] ?? ''), 'features_and_benefits_bullet_1') === 0) {
            echo '  '.($attr['value'] ?? '')."\n";
        }
    }
    break;
}
