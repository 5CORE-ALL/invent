<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$hierarchy = $argv[1] ?? 'Computer Accessories';

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products/attributes", [
        'hierarchy' => $hierarchy,
        'all_operator_attributes' => 'true',
    ]);

echo "Hierarchy: {$hierarchy}\nHTTP {$r->status()}\n\n";

foreach ($r->json('attributes') ?? [] as $a) {
    $code = (string) ($a['code'] ?? '');
    $req = (string) ($a['requirement_level'] ?? '');
    if ($req === 'REQUIRED' || preg_match('/^fnb20-90|fnb20-117|taxCode/i', $code)) {
        echo "{$code} | ".($a['label'] ?? '?')." | {$req} | type=".($a['type'] ?? '?')."\n";
    }
}

$vl = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/values_lists", ['code' => 'fnb20-90']);
echo "\n=== fnb20-90 values ===\n";
foreach ($vl->json('values_lists.0.values') ?? [] as $v) {
    echo ($v['code'] ?? '?').' | '.($v['label'] ?? '?')."\n";
}
