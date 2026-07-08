<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products/attributes", [
        'hierarchy' => 'Home Entertainment Accessories',
        'all_operator_attributes' => 'true',
    ]);

foreach ($r->json('attributes') ?? [] as $a) {
    $code = (string) ($a['code'] ?? '');
    $label = (string) ($a['label'] ?? '');
    if (preg_match('/power|connect|output|watt|bluetooth|wireless/i', $code.' '.$label)) {
        echo $code.' | '.$label.' | '.($a['requirement_level'] ?? '').' | type='.($a['type'] ?? '')."\n";
    }
}

$r2 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/values_lists", ['code' => 'taxCode-electronics']);
$values = $r2->json('values_lists.0.values') ?? [];
echo "\nTax codes (first 30):\n";
foreach (array_slice($values, 0, 30) as $v) {
    echo ($v['code'] ?? '?').' | '.($v['label'] ?? '?')."\n";
}
