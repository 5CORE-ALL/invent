<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$upc = $argv[1] ?? '810047162827';

$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key, 'Accept' => 'application/json'])
    ->get("{$base}/api/products", [
        'product_references' => "UPC|{$upc}",
        'max' => 1,
        'all_operator_attributes' => 'true',
    ]);

$p = $r->json('products.0') ?? [];
echo json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
