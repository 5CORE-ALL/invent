<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'SP 12120 4OHM GTR';
$tokenResp = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
]);
if (! $tokenResp->successful()) {
    echo "Auth failed: ".$tokenResp->body()."\n";
    exit(1);
}
$token = $tokenResp->json()['access_token'] ?? null;

$channels = [
    'macys' => ['code' => 'macys', 'channel_id' => config('services.macy.company_id')],
    'bestbuyusa' => ['code' => 'bestbuyusa', 'channel_id' => 'bestbuyusa'],
];

foreach ($channels as $label => $cfg) {
    echo "=== {$label} ===\n";
    $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
    if (! empty($cfg['channel_id'])) {
        $headers['channel_id'] = $cfg['channel_id'];
    }

    $list = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
        ->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => $cfg['code']]);

    echo "list status: {$list->status()}\n";
    $found = null;
    foreach (($list->json('data') ?? []) as $product) {
        if (strcasecmp((string) ($product['id'] ?? ''), $sku) === 0) {
            $found = $product;
            break;
        }
    }

    if (! $found) {
        $get = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
            ->get('https://miraklconnect.com/api/products/'.$sku);
        echo "GET by id status: {$get->status()}\n";
        echo mb_substr($get->body(), 0, 2000)."\n\n";
        continue;
    }

    echo "product id: {$found['id']}\n";
    foreach (($found['descriptions'] ?? []) as $row) {
        if (($row['locale'] ?? '') === 'en_US') {
            echo "description en_US (first 300): ".mb_substr((string) ($row['value'] ?? ''), 0, 300)."\n";
        }
    }

    foreach (($found['attributes'] ?? []) as $attr) {
        if (! is_array($attr)) {
            continue;
        }
        $id = (string) ($attr['id'] ?? $attr['name'] ?? '');
        if ($id === '' || (! str_contains(strtolower($id), 'bullet') && ! str_contains(strtolower($id), 'desc'))) {
            continue;
        }
        echo "attribute {$id}: ".mb_substr((string) ($attr['value'] ?? ''), 0, 200)."\n";
    }
    echo "\n";
}
