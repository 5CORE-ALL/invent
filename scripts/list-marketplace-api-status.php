<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Support\AllMarketplaceChannelRegistry;
use App\Services\Support\MarketplaceApiConfigService;
use App\Services\Support\ProductMasterMarketplaceMaps;

$registry = app(AllMarketplaceChannelRegistry::class);
$config = app(MarketplaceApiConfigService::class);
$channels = $registry->channels();

$bullet = array_keys(ProductMasterMarketplaceMaps::bulletServiceMap());
$title = ProductMasterMarketplaceMaps::titleMarketplaces();
$desc = array_keys(ProductMasterMarketplaceMaps::descriptionServiceMap());
$image = array_keys(ProductMasterMarketplaceMaps::imagePushMap());
$video = array_keys(ProductMasterMarketplaceMaps::videoPushMap());

$allMasters = ['title', 'bullet', 'description', 'image', 'video'];

function apiFlagsFor(string $key, array $bullet, array $title, array $desc, array $image, array $video): array
{
    $flags = [];
    if (in_array($key, $title, true)) {
        $flags[] = 'title';
    }
    if (in_array($key, $bullet, true)) {
        $flags[] = 'bullet';
    }
    if (in_array($key, $desc, true)) {
        $flags[] = 'description';
    }
    if (in_array($key, $image, true)) {
        $flags[] = 'image';
    }
    if (in_array($key, $video, true)) {
        $flags[] = 'video';
    }

    return $flags;
}

$noApi = [];
$partialApi = [];
$fullApi = [];
$notConfigured = [];

foreach ($channels as $ch) {
    $key = $ch['key'];
    $apiFlags = apiFlagsFor($key, $bullet, $title, $desc, $image, $video);
    $configured = $config->isConfigured($key);

    if (empty($apiFlags)) {
        $noApi[] = [
            'key' => $key,
            'label' => $ch['label'],
            'configured' => $configured,
            'note' => $key === 'amazon_fba' ? 'Uses Amazon account; no Product Master content push columns' : ($key === 'shopify_b5c' ? 'Shopify store exists; no bullet/desc/image/video master columns' : 'Sheet/manual only'),
        ];
        if (! $configured) {
            $notConfigured[] = ['key' => $key, 'label' => $ch['label'], 'reason' => 'No API push + credentials not set'];
        }
        continue;
    }

    if (count($apiFlags) < count($allMasters)) {
        $missing = array_values(array_diff($allMasters, $apiFlags));
        $partialApi[] = [
            'key' => $key,
            'label' => $ch['label'],
            'has' => $apiFlags,
            'missing' => $missing,
            'configured' => $configured,
        ];
    } else {
        $fullApi[] = [
            'key' => $key,
            'label' => $ch['label'],
            'configured' => $configured,
        ];
    }

    if (! $configured && ! empty($apiFlags)) {
        $notConfigured[] = [
            'key' => $key,
            'label' => $ch['label'],
            'reason' => 'API wired but .env credentials missing/incomplete',
            'api' => implode(', ', $apiFlags),
        ];
    }
}

echo "TOTAL_IN_REGISTRY=" . count($channels) . PHP_EOL;
echo "NO_API_COUNT=" . count($noApi) . PHP_EOL;
echo "PARTIAL_API_COUNT=" . count($partialApi) . PHP_EOL;
echo "FULL_API_COUNT=" . count($fullApi) . PHP_EOL;
echo "NOT_CONFIGURED_COUNT=" . count($notConfigured) . PHP_EOL;
echo PHP_EOL;

echo "=== NO API ===" . PHP_EOL;
foreach ($noApi as $row) {
    echo json_encode($row) . PHP_EOL;
}

echo PHP_EOL . "=== PARTIAL API ===" . PHP_EOL;
foreach ($partialApi as $row) {
    echo json_encode($row) . PHP_EOL;
}

echo PHP_EOL . "=== NOT CONFIGURED (credentials) ===" . PHP_EOL;
foreach ($notConfigured as $row) {
    echo json_encode($row) . PHP_EOL;
}
