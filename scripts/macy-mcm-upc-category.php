<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;

$sku = $argv[1] ?? 'GSTOOL PRPL';
$svc = app(MacysApiService::class);
$ref = new ReflectionClass($svc);
$m = $ref->getMethod('fetchMiraklMcmProductCategoryByReference');
$m->setAccessible(true);
$upc = $argv[2] ?? '810199690339';
echo "UPC {$upc} master category: ".json_encode($m->invoke($svc, 'UPC', $upc))."\n";
