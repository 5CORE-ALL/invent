<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Support\AllMarketplaceChannelRegistry;
use App\Services\Support\MarketplaceApiConfigService;
use App\Services\Support\ProductMasterMarketplaceMaps;
use Illuminate\Support\Facades\DB;

$config = app(MarketplaceApiConfigService::class);
$registry = app(AllMarketplaceChannelRegistry::class);

$channelToApiKey = [
    'amazon' => 'amazon',
    'ebay' => 'ebay',
    'ebaytwo' => 'ebay2',
    'ebaythree' => 'ebay3',
    'macys' => 'macy',
    'bestbuyusa' => 'bestbuy',
    'pls' => 'shopify_pls',
    'shopifyb2c' => 'shopify_main',
    'business5core' => 'shopify_b5c',
    'tiktokshop' => 'tiktok',
    'tiktokshop2' => 'tiktok2',
    'purchasingpower' => 'purchasing_power',
    'alibaba' => 'alibaba',
    'aliexpress' => 'aliexpress',
];

$active = DB::table('channel_master')
    ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
    ->orderBy('channel')
    ->get(['channel']);

$noApiSlugs = [
    'tiendamia', 'depop', 'instagramshop',
    'mercariwship', 'mercariwoship', 'fbmarketplace', 'fbshop',
    'shopifyb2b', 'vintedcom', 'vinted',
];

$setChannels = [];
foreach ($active as $row) {
    $slug = $config->normalizeChannelKey($row->channel);
    if (in_array($slug, $noApiSlugs, true)) {
        continue;
    }
    if ($config->isConfigured($row->channel)) {
        $apiKey = resolveApiKey($row->channel, $config, $channelToApiKey);
        $setChannels[] = ['channel' => $row->channel, 'api_key' => $apiKey];
    }
}

$setChannels = array_column($setChannels, 'channel');

$bullet = array_keys(ProductMasterMarketplaceMaps::bulletServiceMap());
$title = ProductMasterMarketplaceMaps::titleMarketplaces();
$desc = array_keys(ProductMasterMarketplaceMaps::descriptionServiceMap());
$image = array_keys(ProductMasterMarketplaceMaps::imagePushMap());
$video = array_keys(ProductMasterMarketplaceMaps::videoPushMap());

$registryChannels = collect($registry->channels())->keyBy('key');
$uiBullet = $registry->enabledFor('bullet');
$uiTitle = array_keys($registry->titleMeta());
$uiDesc = $registry->enabledFor('description');
$uiImage = $registry->enabledFor('image');
$uiVideo = $registry->enabledFor('video');

function resolveApiKey(string $channel, MarketplaceApiConfigService $config, array $channelToApiKey): string
{
    $slug = $config->normalizeChannelKey($channel);
    if (isset($channelToApiKey[$slug])) {
        return $channelToApiKey[$slug];
    }

    return $config->resolveKey($channel);
}

function inMaster(string $apiKey, array $keys): bool
{
    return in_array($apiKey, $keys, true);
}

$masters = [
    'bullet' => ['code' => $bullet, 'ui' => $uiBullet],
    'title' => ['code' => $title, 'ui' => $uiTitle],
    'description' => ['code' => $desc, 'ui' => $uiDesc],
    'image' => ['code' => $image, 'ui' => $uiImage],
    'video' => ['code' => $video, 'ui' => $uiVideo],
];

echo 'SET_CONFIGURED_CHANNELS=' . count($setChannels) . PHP_EOL . PHP_EOL;

$matrix = [];
foreach ($setChannels as $channel) {
    $apiKey = resolveApiKey($channel, $config, $channelToApiKey);
    $row = ['channel' => $channel, 'api_key' => $apiKey];
    foreach ($masters as $master => $maps) {
        $row[$master . '_code'] = inMaster($apiKey, $maps['code']) ? 'Y' : 'N';
        $row[$master . '_ui'] = in_array($apiKey, $maps['ui'], true)
            || in_array(str_replace('_', '', $apiKey), $maps['ui'], true)
            || in_array($apiKey, array_map(fn ($k) => str_replace('ebay1', 'ebay', $k), $maps['ui']), true)
            ? 'Y' : 'N';
    }
    $matrix[] = $row;
}

// Print table header
echo str_pad('Channel', 22);
foreach (array_keys($masters) as $m) {
    echo str_pad(ucfirst($m), 14);
}
echo PHP_EOL;
echo str_repeat('-', 22 + 14 * count($masters)) . PHP_EOL;

foreach ($matrix as $row) {
    echo str_pad($row['channel'], 22);
    foreach (array_keys($masters) as $m) {
        $code = $row[$m . '_code'];
        $ui = $row[$m . '_ui'];
        $cell = ($code === 'Y' && $ui === 'Y') ? 'YES' : (($code === 'Y') ? 'code only' : (($ui === 'Y') ? 'UI only' : 'NO'));
        echo str_pad($cell, 14);
    }
    echo PHP_EOL;
}

echo PHP_EOL . '=== GAPS (SET creds but missing from master code map) ===' . PHP_EOL;
foreach ($matrix as $row) {
    $gaps = [];
    foreach (array_keys($masters) as $m) {
        if ($row[$m . '_code'] === 'N') {
            $gaps[] = $m;
        }
    }
    if ($gaps !== []) {
        echo $row['channel'] . ' (' . $row['api_key'] . ') → missing: ' . implode(', ', $gaps) . PHP_EOL;
    }
}

echo PHP_EOL . '=== IN CODE MAP BUT NOT SET (active channel) ===' . PHP_EOL;
$allCodeKeys = array_unique(array_merge($bullet, $title, $desc, $image, $video));
$setApiKeys = array_unique(array_column($matrix, 'api_key'));
foreach ($allCodeKeys as $key) {
    if (! in_array($key, $setApiKeys, true)) {
        echo $key . PHP_EOL;
    }
}
