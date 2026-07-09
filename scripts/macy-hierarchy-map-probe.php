<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$search = $argv[1] ?? 'Microphone';

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key, 'Accept' => 'application/json'])
    ->timeout(120)->get("{$base}/api/hierarchies");
$hierarchies = $r->json('hierarchies') ?? [];
echo "Total hierarchies: ".count($hierarchies)."\n\n";
foreach ($hierarchies as $h) {
    $label = (string) ($h['label'] ?? $h['code'] ?? '');
    $code = (string) ($h['code'] ?? '');
    if (stripos($label, $search) !== false || stripos($code, $search) !== false) {
        echo "  label={$label} | code={$code}\n";
    }
}

$oauth = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
    'audience' => config('services.macy.company_id'),
]);
$token = $oauth->json('access_token');
$sku = $argv[2] ?? 'MS DBL G WH 2 PCS';
$connect = Http::withoutVerifying()->withToken($token)->withHeaders([
    'Accept' => 'application/json',
    'channel_id' => (string) config('services.macy.company_id'),
])->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);
foreach ($connect->json('data') ?? [] as $p) {
    if (strcasecmp((string) ($p['id'] ?? ''), $sku) !== 0) {
        continue;
    }
    echo "\nConnect product category:\n";
    echo json_encode($p['category'] ?? null, JSON_PRETTY_PRINT)."\n";
    foreach ($p['attributes'] ?? [] as $attr) {
        if (! is_array($attr)) {
            continue;
        }
        $id = strtolower((string) ($attr['id'] ?? ''));
        if (str_contains($id, 'category') || str_contains($id, 'hierarchy') || str_contains($id, 'taxon')) {
            echo '  '.$attr['id'].': '.json_encode($attr['value'] ?? null)."\n";
        }
    }
}
