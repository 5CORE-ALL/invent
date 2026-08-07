<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$active = DB::table('channel_master')
    ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
    ->orderBy('channel')
    ->get(['id', 'channel', 'type', 'status']);

$controllerMapKeys = [
    'amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'macys',
    'bestbuyusa', 'newegg', 'reverb', 'doba', 'temu', 'temu2', 'walmart', 'pls',
    'wayfair', 'faire', 'purchasingpower', 'shein', 'tiktokshop', 'tiktokshop2',
    'depop', 'instagramshop', 'aliexpress', 'mercariwship', 'mercariwoship',
    'fbmarketplace', 'fbshop', 'business5core', 'topdawg', 'shopifyb2c', 'shopifyb2b',
];

echo 'CONTROLLER_MAP_COUNT=' . count($controllerMapKeys) . PHP_EOL;
echo 'ACTIVE_DB_COUNT=' . $active->count() . PHP_EOL;
echo PHP_EOL;

foreach ($active as $row) {
    $key = strtolower(str_replace([' ', '-', '&', '/'], '', trim($row->channel)));
    $inMap = in_array($key, $controllerMapKeys, true) ? 'Y' : 'N';
    echo $row->channel . ' | type=' . ($row->type ?? '') . ' | in_controller_map=' . $inMap . PHP_EOL;
}
