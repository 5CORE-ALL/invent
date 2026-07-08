<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use App\Services\Support\MarketplaceApiConfigService;
use App\Services\Support\ProductMasterMarketplaceMaps;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$sku = $argv[1] ?? 'PL 1002';
$svc = app(MacysApiService::class);
$config = app(MarketplaceApiConfigService::class);

echo "=== Macy Title / Description / Video audit ===\n";
echo "SKU: {$sku}\n\n";

echo "API configured: ".($config->isConfigured('macy') ? 'yes' : 'no')."\n";
echo "MCM API key set: ".(trim((string) config('services.macy.mcm_api_key', '')) !== '' ? 'yes' : 'no')."\n\n";

$maps = ProductMasterMarketplaceMaps::class;
echo "Wired in Product Master:\n";
echo '  title: '.(in_array('macy', ProductMasterMarketplaceMaps::titleMarketplaces(), true) ? 'yes' : 'no')."\n";
echo '  description: '.(isset(ProductMasterMarketplaceMaps::descriptionServiceMap()['macy']) ? 'yes' : 'no')."\n";
echo '  video: '.(isset(ProductMasterMarketplaceMaps::videoPushMap()['macy']) ? 'yes' : 'no')."\n\n";

$row = DB::table('macy_metrics')->where('sku', $sku)->first();
if ($row) {
    echo "macy_metrics row: yes\n";
    echo '  title60 chars: '.mb_strlen(trim((string) ($row->title60 ?? '')))."\n";
    echo '  description_600 chars: '.mb_strlen(trim((string) ($row->description_600 ?? '')))."\n";
    $videos = json_decode((string) ($row->video_master_json ?? '[]'), true);
    echo '  video count: '.(is_array($videos) ? count(array_filter($videos)) : 0)."\n";
} else {
    echo "macy_metrics row: no\n";
}
echo "\n";

// Connect live read
$ref = new ReflectionClass($svc);
$fetch = $ref->getMethod('fetchMacyMiraklProduct');
$fetch->setAccessible(true);
$product = $fetch->invoke($svc, $sku);

if ($product === []) {
    echo "Connect product: NOT FOUND\n";
} else {
    $title = '';
    foreach ((array) ($product['titles'] ?? []) as $t) {
        if (is_array($t) && trim((string) ($t['value'] ?? '')) !== '') {
            $title = trim((string) $t['value']);
            break;
        }
    }
    $desc = '';
    foreach ((array) ($product['descriptions'] ?? []) as $d) {
        if (is_array($d) && trim((string) ($d['value'] ?? '')) !== '') {
            $desc = trim((string) $d['value']);
            break;
        }
    }
    echo "Connect product: FOUND\n";
    echo '  title: '.mb_substr($title, 0, 120).(mb_strlen($title) > 120 ? '...' : '')."\n";
    echo '  description chars: '.mb_strlen($desc)."\n";

    $videoAttrs = [];
    foreach ((array) ($product['attributes'] ?? []) as $attr) {
        if (! is_array($attr)) {
            continue;
        }
        $id = strtolower((string) ($attr['id'] ?? ''));
        if (str_contains($id, 'video')) {
            $videoAttrs[$attr['id']] = $attr['value'] ?? '';
        }
    }
    echo '  video-related Connect attrs: '.(empty($videoAttrs) ? '(none)' : json_encode($videoAttrs))."\n";
}
echo "\n";

// fetchDescriptionHtml (current code path)
$fetchDesc = $svc->fetchDescriptionHtml($sku);
echo 'fetchDescriptionHtml: '.(($fetchDesc['success'] ?? false) ? 'OK ('.mb_strlen((string) ($fetchDesc['html'] ?? '')).' chars)' : 'FAILED - '.($fetchDesc['message'] ?? '?'))."\n\n";

// PM11 video/title/desc attrs on a common hierarchy
$key = trim((string) config('services.macy.mcm_api_key', ''));
$base = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
if ($key !== '') {
    $hierarchy = DB::table('macys_price_data')->where('sku', $sku)->value('category_code') ?: 'Home Entertainment Accessories';
    $pm11 = Http::withoutVerifying()->withHeaders(['Authorization' => $key])
        ->get("{$base}/api/products/attributes", [
            'hierarchy' => $hierarchy,
            'all_operator_attributes' => 'true',
        ]);
    echo "PM11 ({$hierarchy}) title/desc/video attrs:\n";
    foreach ($pm11->json('attributes') ?? [] as $a) {
        $code = (string) ($a['code'] ?? '');
        if (! preg_match('/productName|productLongDescription|title|video|fnb/i', $code)) {
            continue;
        }
        echo "  {$code} | ".($a['requirement_level'] ?? '?')."\n";
    }
}
