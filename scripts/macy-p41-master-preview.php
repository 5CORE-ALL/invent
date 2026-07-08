<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;

$sku = $argv[1] ?? 'PL 1002';
$svc = app(MacysApiService::class);
$ref = new ReflectionClass($svc);

$connect = $ref->getMethod('resolveMiraklMcmConnectCatalogContext');
$connect->setAccessible(true);
$priceRow = $ref->getMethod('fetchMiraklMcmPriceDataRowBySku');
$priceRow->setAccessible(true);
$relatedPrice = $ref->getMethod('fetchMiraklMcmRelatedPriceDataRow');
$relatedPrice->setAccessible(true);
$master = $ref->getMethod('fetchMiraklMcmOperatorMasterProduct');
$master->setAccessible(true);
$rowFromMaster = $ref->getMethod('miraklMcmP41RowFromMasterProduct');
$rowFromMaster->setAccessible(true);
$build = $ref->getMethod('resolveMiraklMcmP41RowValues');
$build->setAccessible(true);
$lines = $ref->getMethod('miraklMcmBulletLines');
$lines->setAccessible(true);
$hierarchy = $ref->getMethod('resolveMiraklMcmHierarchyForP41');
$hierarchy->setAccessible(true);

$ctx = $connect->invoke($svc, $sku);
$pr = $priceRow->invoke($svc, $sku) ?? $relatedPrice->invoke($svc, $sku);
$masterProduct = $master->invoke($svc, $sku, $ctx, $pr);
$masterRow = $rowFromMaster->invoke($svc, $masterProduct);
$h = $hierarchy->invoke($svc, $sku);

$bullets = "bullet one\nbullet two\nbullet three\nbullet four\nbullet five";
$row = $build->invoke($svc, $sku, $lines->invoke($svc, $bullets), ['fnb1','fnb2','fnb3','fnb4','fnb5'], $h, 254);

echo "SKU: {$sku}\n";
echo 'hierarchy: '.json_encode($h)."\n";
echo 'master product_sku: '.($masterProduct['product_sku'] ?? 'none')."\n";
echo 'master category: '.($masterProduct['category_code'] ?? 'none')."\n";
echo 'master row keys: '.implode(', ', array_keys($masterRow))."\n";
echo 'fnb20-90 from master row: '.($masterRow['fnb20-90'] ?? 'MISSING')."\n";
echo 'taxCode from master row: '.($masterRow['taxCode-electronics'] ?? 'MISSING')."\n";
echo "\nP41 row highlights:\n";
foreach (['categoryCode','taxCode-electronics','fnb20-90','fnb20-117','pid','UPC','fnb1'] as $k) {
    echo "  {$k}: ".mb_substr((string)($row[$k] ?? 'MISSING'), 0, 80)."\n";
}
