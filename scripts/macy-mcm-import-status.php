<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$importId = (int) ($argv[1] ?? 2265282);
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

$p42 = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products/imports/{$importId}");
echo "P42 import #{$importId}: HTTP {$p42->status()}\n";
echo json_encode($p42->json(), JSON_PRETTY_PRINT)."\n\n";

$p44 = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products/imports/{$importId}/error_report");
echo "P44 error report: HTTP {$p44->status()}\n";
echo $p44->body()."\n";
