<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$hierarchy = $argv[1] ?? 'Home Entertainment Accessories';

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products/attributes", [
        'hierarchy' => $hierarchy,
        'all_operator_attributes' => 'true',
    ]);

echo "PM11 fnb* for {$hierarchy}\n";
foreach ($r->json('attributes') ?? [] as $a) {
    $code = (string) ($a['code'] ?? '');
    if (! preg_match('/^fnb[1-5]$/i', $code)) {
        continue;
    }
    echo $code.' | req='.($a['requirement_level'] ?? '?');
    echo ' | roles='.json_encode($a['roles'] ?? $a['attribute_role'] ?? null);
    echo ' | validations='.($a['validations'] ?? '');
    echo "\n";
}
