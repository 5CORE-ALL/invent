<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';
$oauth = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
    'audience' => config('services.macy.company_id'),
]);
$token = $oauth->json('access_token');
$companyId = config('services.macy.company_id');
$headers = ['Accept' => 'application/json', 'channel_id' => (string) $companyId];

$connect = Http::withoutVerifying()->withToken($token)->withHeaders($headers)
    ->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);
foreach ($connect->json('data') ?? [] as $p) {
    if (strcasecmp((string) ($p['id'] ?? ''), $sku) !== 0) {
        continue;
    }
    echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    break;
}

// Channel category trees
foreach ([
    "https://miraklconnect.com/api/channels/{$companyId}/categories",
    'https://miraklconnect.com/api/categories',
] as $url) {
    $r = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->get($url, ['channel_code' => 'macys']);
    echo "\n=== {$url} HTTP {$r->status()} ===\n";
    echo mb_substr($r->body(), 0, 1000)."\n";
}
