<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;

$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';
$svc = app(MacysApiService::class);
$ref = new ReflectionClass($svc);

$build = $ref->getMethod('resolveMiraklMcmP41RowValues');
$build->setAccessible(true);
$hierarchy = $ref->getMethod('resolveMiraklMcmHierarchyForP41');
$hierarchy->setAccessible(true);
$lines = $ref->getMethod('miraklMcmBulletLines');
$lines->setAccessible(true);

$bullets = "line1\nline2\nline3\nline4\nline5";
$h = $hierarchy->invoke($svc, $sku);
$row = $build->invoke($svc, $sku, $lines->invoke($svc, $bullets), ['fnb1','fnb2','fnb3','fnb4','fnb5'], $h, 254);

echo "hierarchy: {$h}\n";
echo 'categoryCode: '.($row['categoryCode'] ?? '?')."\n";
echo 'taxCode-electronics: '.($row['taxCode-electronics'] ?? '?')."\n";
echo 'fnb20-117: '.($row['fnb20-117'] ?? '?')."\n";
