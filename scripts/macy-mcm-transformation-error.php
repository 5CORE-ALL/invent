<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$importId = (int) ($argv[1] ?? 2265282);
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$headers = ['Authorization' => $key, 'Accept' => 'application/json'];

$p47 = Http::withoutVerifying()->withHeaders($headers)->get("{$base}/api/products/imports/{$importId}/transformation_error_report");
echo "P47 transformation error report #{$importId}: HTTP {$p47->status()}\n";
echo $p47->body()."\n";
