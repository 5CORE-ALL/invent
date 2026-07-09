<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$hierarchy = 'Home Entertainment Accessories';

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products/attributes", ['hierarchy' => $hierarchy, 'all_operator_attributes' => 'true']);

foreach ($r->json('attributes') ?? [] as $a) {
    $code = (string) ($a['code'] ?? '');
    if (preg_match('/taxCode|fnb20|warranty|legalWarnings|origin|nrfSize/i', $code)) {
        echo $code.' | type='.($a['type'] ?? '?').' | req='.($a['requirement_level'] ?? '?')."\n";
        echo '  type_parameters: '.json_encode($a['type_parameters'] ?? [])."\n";
        if (! empty($a['values'])) {
            echo '  values sample: '.json_encode(array_slice($a['values'], 0, 3))."\n";
        }
    }
}
