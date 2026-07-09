<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';

echo "=== Macy Connect probe (new Client ID / Secret) ===\n\n";

$oauth = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
]);
echo 'OAuth token: HTTP '.$oauth->status()."\n";
$token = $oauth->json('access_token');
if (! $token) {
    echo 'Failed: '.mb_substr($oauth->body(), 0, 200)."\n";
    exit(1);
}
echo "Token received (".strlen($token)." chars)\n\n";

$headers = [
    'Accept' => 'application/json',
    'channel_id' => (string) config('services.macy.company_id'),
];

$product = Http::withoutVerifying()
    ->withToken($token)
    ->withHeaders($headers)
    ->timeout(45)
    ->get('https://miraklconnect.com/api/products/'.rawurlencode($sku));

echo "GET product [{$sku}]: HTTP {$product->status()}\n";
if ($product->successful()) {
    $attrs = $product->json('attributes') ?? $product->json('data.attributes') ?? [];
    $fb = [];
    if (is_array($attrs)) {
        foreach ($attrs as $k => $v) {
            if (stripos((string) $k, 'features_and_benefits') !== false || stripos((string) $k, 'bullet') !== false) {
                $fb[$k] = is_string($v) ? mb_substr($v, 0, 80) : $v;
            }
        }
    }
    echo 'Product found. F&B/bullet attrs: '.( $fb === [] ? '(none in response keys)' : json_encode($fb))."\n";
} else {
    echo mb_substr($product->body(), 0, 300)."\n";
}
