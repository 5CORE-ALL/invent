<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

$sku = $argv[1] ?? 'GSTOOL BLK';
$svc = app(MacysApiService::class);
$ref = new ReflectionClass($svc);

$urls = [];
if (Schema::hasTable('product_master')) {
    foreach (['main_image', 'image1', 'image2', 'image3', 'image4', 'image5'] as $col) {
        if (! Schema::hasColumn('product_master', $col)) {
            continue;
        }
        $v = trim((string) (DB::table('product_master')->where('sku', $sku)->value($col) ?? ''));
        if ($v !== '') {
            $urls[] = $v;
        }
    }
}

$h = $ref->getMethod('resolveMiraklMcmHierarchyForP41');
$h->setAccessible(true);
$fb = $ref->getMethod('resolveMiraklMcmBulletAttributeCodes');
$fb->setAccessible(true);
$bl = $ref->getMethod('resolveMiraklMcmBulletLinesForP41Row');
$bl->setAccessible(true);
$build = $ref->getMethod('buildMiraklMcmP41ImageImportCsv');
$build->setAccessible(true);

$hierarchy = $h->invoke($svc, $sku);
$codes = $fb->invoke($svc, $hierarchy);
$lines = $bl->invoke($svc, $sku, $codes);
$csv = $build->invoke($svc, $sku, $urls, $lines, $codes, $hierarchy);

echo "=== P41 image CSV for {$sku} ===\n";
echo $csv."\n";

$resolve = $ref->getMethod('resolveMiraklMcmP41RowValues');
$resolve->setAccessible(true);
$row = $resolve->invoke($svc, $sku, $lines, $codes, $hierarchy, 254, null, null, $urls);

echo "\n=== Image fields in resolved row ===\n";
foreach ($row as $k => $v) {
    if (preg_match('/image/i', $k)) {
        echo "{$k}: ".mb_substr((string) $v, 0, 120)."\n";
    }
}

$fetch = $ref->getMethod('fetchMacyMiraklProduct');
$fetch->setAccessible(true);
$product = $fetch->invoke($svc, $sku);
echo "\n=== Connect product images ===\n";
echo json_encode($product['images'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$shopId = (int) config('services.macy.shop_id', 2851);

echo "\n=== MCM product by shopSku ===\n";
$r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products", ['shop_id' => $shopId, 'shop_skus' => $sku, 'max' => 1, 'all_operator_attributes' => 'true']);
$attrs = $r->json('products.0.product_attributes') ?? [];
foreach ($attrs as $attr) {
    $code = (string) ($attr['code'] ?? '');
    if (preg_match('/image/i', $code)) {
        echo "{$code}: ".mb_substr((string) ($attr['value'] ?? ''), 0, 120)."\n";
    }
}

echo "\n=== MCM offer ===\n";
$r2 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/offers", ['shop_id' => $shopId, 'sku' => $sku, 'max' => 1]);
echo mb_substr($r2->body(), 0, 2000)."\n";
