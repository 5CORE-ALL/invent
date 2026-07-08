<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

$sku = $argv[1] ?? 'GSTOOL BLK';

$urls = [];
if (Schema::hasTable('product_master')) {
    foreach (['main_image', 'image1', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7', 'image8', 'image9', 'image10', 'image11', 'image12'] as $col) {
        if (! Schema::hasColumn('product_master', $col)) {
            continue;
        }
        $v = trim((string) (DB::table('product_master')->where('sku', $sku)->value($col) ?? ''));
        if ($v !== '') {
            $urls[] = $v;
        }
    }
}
if ($urls === []) {
    exit("No PM images for {$sku}\n");
}

echo "=== Hybrid image push: {$sku} ===\n";
echo 'PM image count: '.count($urls)."\n";
echo 'main: '.mb_substr($urls[0], 0, 90)."\n\n";

$svc = app(MacysApiService::class);

$ref = new ReflectionClass($svc);
$fetch = $ref->getMethod('fetchMacyMiraklProduct');
$fetch->setAccessible(true);
$product = $fetch->invoke($svc, $sku);
$before = [];
foreach ((array) ($product['images'] ?? []) as $img) {
    if (is_array($img) && trim((string) ($img['url'] ?? '')) !== '') {
        $before[] = trim((string) $img['url']);
    }
}
echo 'Connect BEFORE count: '.count($before)."\n";
if ($before !== []) {
    echo '  first: '.mb_substr($before[0], 0, 90)."\n";
}
echo 'Match PM main before push: '.(isset($before[0]) && basename(parse_url($before[0], PHP_URL_PATH) ?: $before[0]) === basename(parse_url($urls[0], PHP_URL_PATH) ?: $urls[0]) ? 'YES' : 'NO')."\n\n";

$result = $svc->updateImages($sku, $urls);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n";

$importId = (int) ($result['import_id'] ?? 0);
if ($importId > 0) {
    echo "=== P42 import #{$importId} ===\n";
    passthru('php '.escapeshellarg(__DIR__.'/macy-mcm-import-status.php').' '.escapeshellarg((string) $importId));
    echo "\n";
}

echo "=== Connect AFTER (poll) ===\n";
for ($i = 1; $i <= 6; $i++) {
    sleep(10);
    $product = $fetch->invoke($svc, $sku);
    $after = [];
    foreach ((array) ($product['images'] ?? []) as $img) {
        if (is_array($img) && trim((string) ($img['url'] ?? '')) !== '') {
            $after[] = trim((string) $img['url']);
        }
    }
    $match = isset($after[0]) && basename(parse_url($after[0], PHP_URL_PATH) ?: $after[0]) === basename(parse_url($urls[0], PHP_URL_PATH) ?: $urls[0]);
    echo "poll {$i}: ".($match ? 'MAIN MATCH' : 'pending').' ('.count($after)." images)\n";
    if ($match) {
        break;
    }
}

echo "\n=== MCM mainImage ===\n";
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$upc = trim((string) (DB::table('macys_price_data')->where('sku', $sku)->value('upc') ?? ''));
if ($upc !== '') {
    $r = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
        ->get("{$base}/api/products", [
            'shop_id' => (int) config('services.macy.shop_id', 2851),
            'product_references' => 'UPC|'.rawurlencode($upc),
            'max' => 1,
            'all_operator_attributes' => 'true',
        ]);
    $mcmMain = '';
    foreach (($r->json('products.0.product_attributes') ?? []) as $attr) {
        if (($attr['code'] ?? '') === 'mainImage') {
            $mcmMain = trim((string) ($attr['value'] ?? ''));
            break;
        }
    }
    echo 'mainImage: '.($mcmMain !== '' ? mb_substr($mcmMain, 0, 90) : '(empty)')."\n";
    echo 'Match PM: '.($mcmMain !== '' && basename(parse_url($mcmMain, PHP_URL_PATH) ?: $mcmMain) === basename(parse_url($urls[0], PHP_URL_PATH) ?: $urls[0]) ? 'YES' : 'NO')."\n";
}
