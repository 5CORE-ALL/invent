<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';
$upc = $argv[2] ?? '810199692883';
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

foreach (['UPC|810199692883', 'EAN|810199692883', 'shop_sku|'.rawurlencode($sku)] as $ref) {
    $r = Http::withoutVerifying()->withHeaders($headers)->timeout(60)
        ->get("{$base}/api/products", ['product_references' => $ref, 'max' => 1, 'shop_id' => 2851]);
    echo "=== products ref {$ref}: HTTP {$r->status()} ===\n";
    $products = $r->json('products') ?? [];
    if ($products !== []) {
        $p = $products[0];
        echo '  category_code: '.json_encode($p['category_code'] ?? null)."\n";
        echo '  shop_sku: '.json_encode($p['shop_sku'] ?? null)."\n";
        echo mb_substr(json_encode($p), 0, 1500)."\n";
    } else {
        echo mb_substr($r->body(), 0, 300)."\n";
    }
    echo "\n";
}

    'offers sku' => "{$base}/api/offers?sku=".urlencode($sku),
    'offers shop_sku' => "{$base}/api/offers?shop_sku=".urlencode($sku),
    'products shop_sku ref' => "{$base}/api/products?product_references=".urlencode(json_encode([['reference_type' => 'shop_sku', 'reference' => $sku]])),
    'products UPC ref' => "{$base}/api/products?product_references=".urlencode(json_encode([['reference_type' => 'UPC', 'reference' => $upc]])),
    'products shop_sku pipe' => "{$base}/api/products?product_references=".urlencode('shop_sku|'.$sku),
];

foreach ($queries as $label => $url) {
    $r = Http::withoutVerifying()->withHeaders($headers)->timeout(60)->get($url);
    echo "=== {$label}: HTTP {$r->status()} ===\n";
    $body = $r->json();
    if (is_array($body)) {
        $products = $body['products'] ?? $body['data'] ?? [];
        $offers = $body['offers'] ?? [];
        if ($products !== []) {
            $p = $products[0] ?? $products;
            echo '  category_code: '.json_encode($p['category_code'] ?? $p['category']['code'] ?? null)."\n";
            echo '  category_label: '.json_encode($p['category_label'] ?? $p['category']['label'] ?? null)."\n";
            if (isset($p['product_sku'])) {
                echo '  product_sku: '.$p['product_sku']."\n";
            }
            if (isset($p['data'])) {
                foreach ((array) $p['data'] as $attr) {
                    if (is_array($attr) && (($attr['code'] ?? '') === 'categoryCode' || ($attr['code'] ?? '') === 'category_code')) {
                        echo '  attr category: '.json_encode($attr['value'] ?? null)."\n";
                    }
                }
            }
        }
        if ($offers !== []) {
            $o = $offers[0];
            echo '  offer category_code: '.($o['category_code'] ?? '?')."\n";
            echo '  offer product_sku: '.($o['product_sku'] ?? '?')."\n";
        }
        if ($products === [] && $offers === []) {
            echo '  (empty) '.mb_substr(json_encode($body), 0, 300)."\n";
        }
    } else {
        echo mb_substr($r->body(), 0, 300)."\n";
    }
    echo "\n";
}

// Connect category
$oauth = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
    'audience' => config('services.macy.company_id'),
]);
$token = $oauth->json('access_token');
$connect = Http::withoutVerifying()->withToken($token)->withHeaders([
    'Accept' => 'application/json',
    'channel_id' => (string) config('services.macy.company_id'),
])->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);
foreach ($connect->json('data') ?? [] as $p) {
    if (strcasecmp((string) ($p['id'] ?? ''), $sku) === 0) {
        echo "=== Connect category ===\n";
        echo json_encode($p['category'] ?? null, JSON_PRETTY_PRINT)."\n";
        foreach ($p['attributes'] ?? [] as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $id = (string) ($attr['id'] ?? '');
            if (stripos($id, 'category') !== false) {
                echo "  attr {$id}: ".json_encode($attr['value'] ?? null)."\n";
            }
        }
        break;
    }
}
