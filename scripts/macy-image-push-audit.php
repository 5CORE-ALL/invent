<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use App\Services\Support\MarketplaceApiConfigService;
use App\Services\Support\ProductMasterMarketplaceMaps;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

$sku = $argv[1] ?? 'GSTOOL BLK';

echo "=== Macy Image push audit: {$sku} ===\n\n";
echo 'API configured: '.(app(MarketplaceApiConfigService::class)->isConfigured('macy') ? 'yes' : 'no')."\n";
echo 'MCM API key: '.(trim((string) config('services.macy.mcm_api_key', '')) !== '' ? 'yes' : 'no')."\n";
echo 'Image Master wired: '.(isset(ProductMasterMarketplaceMaps::imagePushMap()['macy']) ? 'yes' : 'no')."\n\n";

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
echo 'PM image URLs: '.count($urls)."\n";
if ($urls !== []) {
    echo '  main: '.mb_substr($urls[0], 0, 90)."\n";
}

$mm = DB::table('macy_metrics')->where('sku', $sku)->first();
$metricsUrls = json_decode((string) ($mm->image_master_json ?? $mm->image_urls ?? '[]'), true);
if (is_array($metricsUrls)) {
    echo 'macy_metrics images: '.count(array_filter($metricsUrls))."\n";
}

$ref = new ReflectionClass(app(MacysApiService::class));
$fetch = $ref->getMethod('fetchMacyMiraklProduct');
$fetch->setAccessible(true);
$product = $fetch->invoke(app(MacysApiService::class), $sku);

$connectImages = [];
foreach ((array) ($product['images'] ?? []) as $img) {
    if (is_array($img) && trim((string) ($img['url'] ?? '')) !== '') {
        $connectImages[] = trim((string) $img['url']);
    }
}
echo "\nConnect images array: ".count($connectImages)."\n";
if ($connectImages !== []) {
    echo '  first: '.mb_substr($connectImages[0], 0, 90)."\n";
}

$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
$hierarchy = trim((string) (DB::table('macys_price_data')->where('sku', $sku)->value('category_code') ?? 'Home Entertainment Accessories'));
$pm11 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
    ->get("{$base}/api/products/attributes", ['hierarchy' => $hierarchy, 'all_operator_attributes' => 'true']);
echo "\nPM11 image attrs ({$hierarchy}):\n";
foreach ($pm11->json('attributes') ?? [] as $a) {
    $code = (string) ($a['code'] ?? '');
    if (preg_match('/image/i', $code)) {
        echo "  {$code} | ".($a['requirement_level'] ?? '?')."\n";
    }
}

echo "\nHybrid path: Connect images[] array + MCM P41 (mainImage/secondImage/thirdImage + images_media:image3-10).\n";
echo 'mcm_image_push: '.(filter_var(config('services.macy.mcm_image_push', true), FILTER_VALIDATE_BOOL) ? 'enabled' : 'disabled')."\n";
