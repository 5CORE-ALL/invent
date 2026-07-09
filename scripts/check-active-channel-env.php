<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Support\Facades\DB;

$config = app(MarketplaceApiConfigService::class);

$active = DB::table('channel_master')
    ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
    ->orderBy('channel')
    ->pluck('channel');

$set = [];
$missing = [];
$noApi = [];

// Channels with no API push (from MarketplaceApiConfigService::CHANNEL_SLUG_TO_API = null)
$noApiSlugs = [
    'tiendamia', 'depop', 'instagramshop',
    'mercariwship', 'mercariwoship', 'fbmarketplace', 'fbshop',
    'shopifyb2b', 'vintedcom', 'vinted',
];

foreach ($active as $channel) {
    $slug = $config->normalizeChannelKey($channel);
    $apiKey = match ($slug) {
        'ebay' => 'ebay',
        'ebaytwo' => 'ebay2',
        'ebaythree' => 'ebay3',
        'macys' => 'macy',
        'bestbuyusa' => 'bestbuy',
        'pls' => 'shopify_pls',
        'shopifyb2c' => 'shopify_main',
        'business5core' => 'shopify_b5c',
        'tiktokshop' => 'tiktok',
        'tiktokshop2' => 'tiktok',
        default => $config->resolveKey($channel),
    };

    $hasApi = ! in_array($slug, $noApiSlugs, true);
    $configured = $config->isConfigured($channel);

    if (! $hasApi) {
        $noApi[] = $channel;
        continue;
    }

    if ($configured) {
        $set[] = $channel;
    } else {
        $missing[] = $channel;
    }
}

echo 'ACTIVE_CHANNELS=' . count($active) . PHP_EOL;
echo 'HAS_API=' . (count($set) + count($missing)) . PHP_EOL;
echo 'CONFIGURED=' . count($set) . PHP_EOL;
echo 'MISSING_CREDS=' . count($missing) . PHP_EOL;
echo 'NO_API=' . count($noApi) . PHP_EOL;
echo PHP_EOL;

echo '=== CONFIGURED ===' . PHP_EOL;
foreach ($set as $c) {
    echo 'SET — ' . $c . PHP_EOL;
}

echo PHP_EOL . '=== MISSING .env ===' . PHP_EOL;
foreach ($missing as $c) {
    echo 'MISSING — ' . $c . PHP_EOL;
}

echo PHP_EOL . '=== NO API (no .env needed) ===' . PHP_EOL;
foreach ($noApi as $c) {
    echo 'NO API — ' . $c . PHP_EOL;
}
