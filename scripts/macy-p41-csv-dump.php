<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use Illuminate\Support\Facades\DB;

$sku = $argv[1] ?? 'MS DBL G WH 2 PCS';
$svc = app(MacysApiService::class);
$ref = new ReflectionClass($svc);

$local = trim((string) (DB::table('macy_metrics')->where('sku', $sku)->value('bullet_points') ?? ''));
$lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $local) ?: [])));

$build = $ref->getMethod('buildMiraklMcmP41BulletImportCsv');
$build->setAccessible(true);
$hierarchy = $ref->getMethod('resolveMiraklMcmHierarchyForP41');
$hierarchy->setAccessible(true);
$fbCodes = $ref->getMethod('resolveMiraklMcmBulletAttributeCodes');
$fbCodes->setAccessible(true);

$h = $hierarchy->invoke($svc, $sku);
$codes = $fbCodes->invoke($svc, $h);
$csv = $build->invoke($svc, $sku, $lines, $codes, $h);

echo "SKU: {$sku}\nHierarchy: {$h}\n\n";
echo "=== P41 CSV (enriched) ===\n";
echo $csv."\n";

$resolve = $ref->getMethod('resolveMiraklMcmP41RowValues');
$resolve->setAccessible(true);
$row = $resolve->invoke($svc, $sku, $lines, $codes, $h, 254);

echo "\n=== fnb + UPC in resolved row ===\n";
foreach ($row as $k => $v) {
    if (preg_match('/^fnb\d+$/i', $k) || in_array($k, ['UPC', 'inHouseUpc', 'In House UPC'], true)) {
        echo "{$k}: ".mb_substr((string) $v, 0, 120)."\n";
    }
}

$master = $ref->getMethod('fetchMiraklMcmOperatorMasterProduct');
$master->setAccessible(true);
$connect = $ref->getMethod('resolveMiraklMcmConnectCatalogContext');
$connect->setAccessible(true);
$priceRow = $ref->getMethod('fetchMiraklMcmRelatedPriceDataRow');
$priceRow->setAccessible(true);
$m = $master->invoke($svc, $sku, $connect->invoke($svc, $sku), $priceRow->invoke($svc, $sku));

echo "\n=== Master product keys (fnb*) ===\n";
$fromMaster = $ref->getMethod('miraklMcmP41RowFromMasterProduct');
$fromMaster->setAccessible(true);
$masterRow = $fromMaster->invoke($svc, $m);
foreach ($masterRow as $k => $v) {
    if (preg_match('/^fnb\d+$/i', $k)) {
        echo "{$k}: ".mb_substr((string) $v, 0, 120)."\n";
    }
}
if ($masterRow === []) {
    echo "(no master row)\n";
}
