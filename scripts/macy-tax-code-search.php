<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$needle = $argv[1] ?? 'microphone';

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/values_lists", ['code' => 'taxCode-electronics']);

$values = $r->json('values_lists.0.values') ?? [];
foreach ($values as $v) {
    $label = (string) ($v['label'] ?? '');
    $code = (string) ($v['code'] ?? '');
    if ($needle === 'all'
        || stripos($label, $needle) !== false
        || stripos($label, 'stand') !== false
        || stripos($label, 'home theater') !== false
        || stripos($label, 'accessory') !== false
        || stripos($label, 'speaker') !== false
        || stripos($label, 'audio') !== false
        || stripos($label, 'entertainment') !== false) {
        echo "{$code} | {$label}\n";
    }
}
