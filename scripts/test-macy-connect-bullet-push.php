<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
$bullets = DB::table('macy_metrics')->where('sku', $sku)->value('bullet_points');
if (!$bullets) {
    echo "No bullets\n";
    exit(1);
}

$lines = array_slice(array_values(array_filter(array_map('trim', explode("\n", $bullets)))), 0, 5);
$maxLen = 254;
$attrs = ['bulletPoints' => implode("\n", $lines)];
for ($i = 1; $i <= 5; $i++) {
    $line = $lines[$i - 1] ?? '';
    $attrs["features_and_benefits_bullet_{$i}"] = $line === '' ? '' : mb_substr($line, 0, $maxLen);
}

$formatted = [];
foreach ($attrs as $id => $value) {
    $formatted[] = ['id' => $id, 'value' => $value, 'type' => is_string($value) ? 'STRING' : 'TEXT'];
}

$resp = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
]);
$token = $resp->json('access_token');
$headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
$cid = config('services.macy.company_id');
if ($cid) {
    $headers['channel_id'] = $cid;
}

$payload = [
    'id' => $sku,
    'attributes' => $formatted,
];

$request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60);
$response = $request->post('https://miraklconnect.com/api/products', ['products' => [$payload]]);
echo "POST upsert: HTTP ".$response->status()."\n";
echo mb_substr($response->body(), 0, 2000)."\n";

if (!$response->successful()) {
    $payload2 = ['id' => $sku, 'attributes' => $attrs];
    $response2 = $request->patch('https://miraklconnect.com/api/products/'.rawurlencode($sku), $payload2);
    echo "\nPATCH: HTTP ".$response2->status()."\n";
    echo mb_substr($response2->body(), 0, 1500)."\n";
}
