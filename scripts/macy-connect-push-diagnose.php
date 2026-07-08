<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
Cache::forget('macy_connect_access_token');

$companyId = trim((string) config('services.macy.company_id'));
$payload = [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
];
if ($companyId !== '') {
    $payload['audience'] = $companyId;
}

$tokenResp = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', $payload);
echo "OAuth: HTTP {$tokenResp->status()}\n";
$token = $tokenResp->json('access_token');
if (! $token) {
    echo $tokenResp->body()."\n";
    exit(1);
}

$headers = [
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
    'channel_id' => $companyId,
];

$get = Http::withoutVerifying()->withToken($token)->withHeaders($headers)
    ->get('https://miraklconnect.com/api/products', ['limit' => 5, 'channel_code' => 'macys']);
echo "GET products: HTTP {$get->status()}\n";

$productPayload = [
    'id' => $sku,
    'attributes' => [
        ['id' => 'features_and_benefits_bullet_1', 'name' => 'features_and_benefits_bullet_1', 'type' => 'STRING', 'value' => 'Test bullet push '.date('H:i:s')],
    ],
];

$post = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
    ->post('https://miraklconnect.com/api/products', ['products' => [$productPayload]]);
echo "POST upsert: HTTP {$post->status()}\n";
echo mb_substr($post->body(), 0, 500)."\n";

if (! $post->successful()) {
    $patch = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
        ->patch('https://miraklconnect.com/api/products/'.rawurlencode($sku), $productPayload);
    echo "PATCH: HTTP {$patch->status()}\n";
    echo mb_substr($patch->body(), 0, 500)."\n";
}
