<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$search = $argv[1] ?? 'microphone';

echo "=== macys_price_data category search: {$search} ===\n";
$rows = DB::table('macys_price_data')
    ->where('category_code', 'like', "%{$search}%")
    ->orWhere('product_name', 'like', "%{$search}%")
    ->orWhere('sku', 'like', "%{$search}%")
    ->limit(20)
    ->get(['sku', 'category_code', 'upc', 'product_name']);
foreach ($rows as $r) {
    echo json_encode($r)."\n";
}

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
echo "\n=== PM11 hierarchies containing '{$search}' ===\n";
$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key, 'Accept' => 'application/json'])
    ->timeout(120)->get("{$base}/api/hierarchies");
echo 'HTTP '.$r->status()."\n";
$hierarchies = $r->json('hierarchies') ?? [];
$matches = [];
foreach ($hierarchies as $h) {
    $label = (string) ($h['label'] ?? $h['code'] ?? '');
    if (stripos($label, $search) !== false) {
        $matches[] = $label;
    }
}
echo implode("\n", array_slice($matches, 0, 30))."\n";
echo 'total hierarchies: '.count($hierarchies).', matches: '.count($matches)."\n";
