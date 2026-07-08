<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');

foreach (['warranty', 'legalWarnings', 'origin', 'taxCode-electronics', 'fnb20-117', 'nrfSizeCode', 'nrfColorCode'] as $listCode) {
    $r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
        ->get("{$base}/api/values_lists", ['code' => $listCode]);
    echo "=== {$listCode}: HTTP {$r->status()} ===\n";
    $values = $r->json('values_lists.0.values') ?? $r->json('values') ?? [];
    if (is_array($values) && $values !== []) {
        foreach (array_slice($values, 0, 8) as $v) {
            echo '  '.json_encode($v)."\n";
        }
    } else {
        echo mb_substr($r->body(), 0, 400)."\n";
    }
    echo "\n";
}
