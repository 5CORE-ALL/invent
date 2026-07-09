<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$payload = [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
];
$companyId = trim((string) config('services.macy.company_id'));
if ($companyId !== '') {
    $payload['audience'] = $companyId;
}
$resp = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', $payload);
echo 'OAuth: HTTP '.$resp->status()."\n";
if ($resp->successful()) {
    echo "Token OK\n";
} else {
    echo $resp->body()."\n";
    exit(1);
}

$token = $resp->json('access_token');
$sku = $argv[1] ?? 'GSTOOL BLK';
$headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
$cid = config('services.macy.company_id');
if ($cid) {
    $headers['channel_id'] = $cid;
}
$ids = ['GSTOOL BLK', '810099499711_17594377_12', '810099499711'];
foreach ($ids as $id) {
    $get = Http::withoutVerifying()->withToken($token)->withHeaders($headers)
        ->get('https://miraklconnect.com/api/products/'.rawurlencode($id));
    echo "GET {$id}: HTTP ".$get->status()."\n";
}

$list = Http::withoutVerifying()->withToken($token)->withHeaders($headers)
    ->get('https://miraklconnect.com/api/products', ['limit' => 5, 'ids' => 'GSTOOL BLK']);
echo "List with ids param: HTTP ".$list->status()."\n";
if ($list->successful()) {
    $data = $list->json('data') ?? [];
    echo 'count: '.count($data)."\n";
    if (!empty($data[0])) {
        echo 'first id: '.($data[0]['id'] ?? '?')."\n";
    }
}
