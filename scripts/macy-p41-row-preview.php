<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;

$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';
$svc = app(MacysApiService::class);
$ref = new ReflectionClass($svc);

$hierarchy = $ref->getMethod('resolveMiraklMcmHierarchyForP41');
$hierarchy->setAccessible(true);
$connect = $ref->getMethod('resolveMiraklMcmConnectCatalogContext');
$connect->setAccessible(true);
$related = $ref->getMethod('miraklMcmRelatedSkuCandidates');
$related->setAccessible(true);
$priceRow = $ref->getMethod('fetchMiraklMcmRelatedPriceDataRow');
$priceRow->setAccessible(true);

echo "SKU: {$sku}\n";
echo 'related candidates: '.json_encode($related->invoke($svc, $sku))."\n";
echo 'hierarchy: '.json_encode($hierarchy->invoke($svc, $sku))."\n";
echo 'connect context: '.json_encode($connect->invoke($svc, $sku))."\n";
$row = $priceRow->invoke($svc, $sku);
echo 'related price row sku: '.($row->sku ?? 'none').' category: '.($row->category_code ?? 'none')."\n";
