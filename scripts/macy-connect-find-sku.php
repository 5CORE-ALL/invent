<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$search = $argv[1] ?? 'GSTOOL BLK';

$oauth = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
]);
$token = $oauth->json('access_token');
if (! $token) {
    echo "OAuth failed\n";
    exit(1);
}

$headers = ['Accept' => 'application/json', 'channel_id' => (string) config('services.macy.company_id')];
$found = null;
$pageToken = null;
$pages = 0;

do {
    $url = 'https://miraklconnect.com/api/products?limit=100';
    if ($pageToken) {
        $url .= '&page_token='.urlencode($pageToken);
    }
    $resp = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)->get($url);
    if (! $resp->successful()) {
        echo 'List failed: HTTP '.$resp->status().' '.mb_substr($resp->body(), 0, 200)."\n";
        exit(1);
    }
    $pages++;
    foreach ($resp->json('data') ?? [] as $p) {
        $id = (string) ($p['id'] ?? '');
        if (strcasecmp($id, $search) === 0 || stripos($id, 'GSTOOL') !== false) {
            $found = $p;
            if (strcasecmp($id, $search) === 0) {
                break 2;
            }
        }
    }
    $pageToken = $resp->json('next_page_token');
} while ($pageToken && $pages < 20);

if (! $found) {
    echo "SKU [{$search}] not found in Connect catalog (scanned {$pages} pages).\n";
    exit(1);
}

echo 'Found id: '.($found['id'] ?? '')."\n";
$attrs = $found['attributes'] ?? [];
foreach ($attrs as $k => $v) {
    if (preg_match('/bullet|feature|benefit|description/i', (string) $k)) {
        $preview = is_string($v) ? mb_substr($v, 0, 100) : json_encode($v);
        echo "  {$k}: {$preview}\n";
    }
}
