<?php

$map = [
    'Aliexpress' => 'Newegg',
    'aliexpress' => 'newegg',
    'AliExpress' => 'Newegg',
    'ALIEXPRESS' => 'NEWEGG',
    'Ali Express' => 'Newegg',
];

$files = [
    'app/Http/Controllers/MarketPlace/AliexpressSyncController.php' => 'app/Http/Controllers/MarketPlace/NeweggSyncController.php',
    'app/Services/MarketplaceManager/AliexpressInventorySyncService.php' => 'app/Services/MarketplaceManager/NeweggInventorySyncService.php',
    'app/Services/MarketplaceManager/AliexpressLinkMapSyncService.php' => 'app/Services/MarketplaceManager/NeweggLinkMapSyncService.php',
    'app/Services/MarketplaceManager/AliexpressOrderSyncService.php' => 'app/Services/MarketplaceManager/NeweggOrderSyncService.php',
    'app/Services/MarketplaceManager/AliexpressOrderDetailService.php' => 'app/Services/MarketplaceManager/NeweggOrderDetailService.php',
    'app/Services/MarketplaceManager/AliexpressOrderPushService.php' => 'app/Services/MarketplaceManager/NeweggOrderPushService.php',
    'app/Services/MarketplaceManager/AliexpressDetailFormatter.php' => 'app/Services/MarketplaceManager/NeweggDetailFormatter.php',
    'app/Services/MarketplaceManager/AliexpressLiveListingsService.php' => 'app/Services/MarketplaceManager/NeweggLiveListingsService.php',
    'app/Models/AliexpressMetric.php' => 'app/Models/NeweggMetric.php',
    'app/Models/AliexpressOrderMetric.php' => 'app/Models/NeweggOrderMetric.php',
    'app/Models/AliexpressPricingPrice.php' => 'app/Models/NeweggPricingPrice.php',
    'app/Console/Commands/SyncAliexpressInventoryFromShopify.php' => 'app/Console/Commands/SyncNeweggInventoryFromShopify.php',
    'app/Console/Commands/SyncAliexpressOrders.php' => 'app/Console/Commands/SyncNeweggOrders.php',
    'app/Console/Commands/SyncAliexpressManagerLinkMap.php' => 'app/Console/Commands/SyncNeweggManagerLinkMap.php',
    'app/Jobs/WarmAliexpressLiveListingsCache.php' => 'app/Jobs/WarmNeweggLiveListingsCache.php',
    'app/Jobs/SyncInventoryToAliexpress.php' => 'app/Jobs/SyncInventoryToNewegg.php',
];

$root = dirname(__DIR__);
chdir($root);

foreach ($files as $from => $to) {
    if (! is_file($from)) {
        echo "MISSING {$from}\n";
        continue;
    }
    $c = file_get_contents($from);
    $c = str_replace(array_keys($map), array_values($map), $c);
    $c = str_replace('App\\Services\\AliExpressApiService', 'App\\Services\\NeweggApiService', $c);
    $c = str_replace('AliExpressApiService', 'NeweggApiService', $c);
    $c = str_replace('AliExpressAuthService', 'NeweggApiService', $c);
    $dir = dirname($to);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($to, $c);
    echo "OK {$to}\n";
}

$vsrc = 'resources/views/marketplace/aliexpress';
$vdst = 'resources/views/marketplace/newegg';
if (! is_dir($vdst)) {
    mkdir($vdst, 0777, true);
}
foreach (scandir($vsrc) as $f) {
    if ($f === '.' || $f === '..') {
        continue;
    }
    $c = file_get_contents("{$vsrc}/{$f}");
    $c = str_replace(array_keys($map), array_values($map), $c);
    file_put_contents("{$vdst}/{$f}", $c);
    echo "OK view {$f}\n";
}

echo "DONE\n";
