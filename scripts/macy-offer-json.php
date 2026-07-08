<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'GSTOOL BLK';
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])->get("{$base}/api/offers", ['sku' => $sku]);
echo json_encode($r->json('offers.0'), JSON_PRETTY_PRINT);
