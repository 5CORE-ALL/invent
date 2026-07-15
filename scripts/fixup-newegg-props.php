<?php

chdir(dirname(__DIR__));

$files = array_merge(
    glob('app/Services/MarketplaceManager/Newegg*.php') ?: [],
    ['app/Http/Controllers/MarketPlace/NeweggSyncController.php'],
    glob('app/Jobs/*Newegg*.php') ?: [],
    glob('app/Console/Commands/SyncNewegg*.php') ?: [],
    ['app/Models/NeweggPricingPrice.php'],
);

foreach ($files as $file) {
    if (! is_file($file)) {
        continue;
    }
    $c = file_get_contents($file);
    $n = str_replace(
        ['$this->aliExpressApi', '$aliExpressApi', '$this->aliExpressAuth', '$aliExpressAuth', 'NeweggAuthService', 'ae_stock'],
        ['$this->neweggApi', '$neweggApi', '$this->neweggApi', '$neweggApi', 'NeweggApiService', 'ne_stock'],
        $c
    );
    if ($n !== $c) {
        file_put_contents($file, $n);
        echo "FIXED {$file}\n";
    }
}
echo "DONE\n";
