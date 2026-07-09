<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';

foreach ([
    ['sku' => $sku],
    ['sku' => $sku, 'shop_id' => 2851],
] as $q) {
    $r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])->get("{$base}/api/offers", $q);
    echo 'offers '.json_encode($q).' HTTP '.$r->status().' count '.count($r->json('offers') ?? [])."\n";
    foreach ($r->json('offers') ?? [] as $o) {
        if (strcasecmp((string) ($o['shop_sku'] ?? ''), $sku) === 0) {
            echo '  MATCH offer: '.json_encode([
                'category_code' => $o['category_code'] ?? null,
                'category_label' => $o['category_label'] ?? null,
                'product_sku' => $o['product_sku'] ?? null,
            ])."\n";
        }
    }
}

$r2 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products", [
        'product_references' => 'shop_sku|'.rawurlencode($sku),
        'max' => 5,
        'shop_id' => 2851,
    ]);
echo "\nproducts shop_id=2851 HTTP {$r2->status()}\n";
echo mb_substr($r2->body(), 0, 2000)."\n";

// Connect channel categories if available
$oauth = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
    'audience' => config('services.macy.company_id'),
]);
$token = $oauth->json('access_token');
$companyId = config('services.macy.company_id');
foreach ([
    "https://miraklconnect.com/api/channels/{$companyId}/categories",
    'https://miraklconnect.com/api/categories?channel_code=macys',
] as $url) {
    $r = Http::withoutVerifying()->withToken($token)->withHeaders([
        'Accept' => 'application/json',
        'channel_id' => (string) $companyId,
    ])->get($url);
    echo "\nConnect categories {$url}: HTTP {$r->status()}\n";
    echo mb_substr($r->body(), 0, 600)."\n";
}
