<?php

$root = dirname(__DIR__);
chdir($root);

$from = 'app/Jobs/ImportAliexpressOrderToShopify.php';
$to = 'app/Jobs/ImportNeweggManagerOrderToShopify.php';
$map = [
    'Aliexpress' => 'Newegg',
    'aliexpress' => 'newegg',
    'AliExpress' => 'Newegg',
    'ALIEXPRESS' => 'NEWEGG',
];
$c = file_get_contents($from);
$c = str_replace(array_keys($map), array_values($map), $c);
$c = str_replace('AliExpressApiService', 'NeweggApiService', $c);
file_put_contents($to, $c);
echo "OK {$to}\n";

// Property renames across Newegg MM PHP files
$globs = array_merge(
    glob('app/Services/MarketplaceManager/Newegg*.php') ?: [],
    glob('app/Http/Controllers/MarketPlace/NeweggSyncController.php') ?: [],
    glob('app/Jobs/*Newegg*.php') ?: [],
    glob('app/Console/Commands/SyncNewegg*.php') ?: [],
);
foreach ($globs as $file) {
    $c = file_get_contents($file);
    $orig = $c;
    $c = str_replace('$aliExpressApi', '$neweggApi', $c);
    $c = str_replace('$aliExpressAuth', '$neweggApi', $c);
    $c = str_replace('protected NeweggApiService $neweggApi', 'protected NeweggApiService $neweggApi', $c);
    // Fix auth service type-hint if still wrong
    $c = str_replace('use App\\Services\\NeweggAuthService;', 'use App\\Services\\NeweggApiService;', $c);
    $c = str_replace('protected NeweggAuthService $neweggApi', 'protected NeweggApiService $neweggApi', $c);
    $c = str_replace('protected NeweggAuthService $aliExpressAuth', 'protected NeweggApiService $neweggApi', $c);
    $c = str_replace('NeweggAuthService', 'NeweggApiService', $c);
    $c = str_replace('ae_stock', 'ne_stock', $c);
    if ($c !== $orig) {
        file_put_contents($file, $c);
        echo "FIXED {$file}\n";
    }
}

echo "DONE\n";
