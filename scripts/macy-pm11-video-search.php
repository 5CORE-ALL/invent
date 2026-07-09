<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Http;
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products/attributes", ['all_operator_attributes' => 'true']);
$found = 0;
foreach ($r->json('attributes') ?? [] as $a) {
    $c = (string) ($a['code'] ?? '');
    if (preg_match('/video/i', $c)) {
        echo $c.' | '.($a['requirement_level'] ?? '?')."\n";
        $found++;
    }
}
echo $found === 0 ? "(no video attributes in Macy PM11)\n" : '';
