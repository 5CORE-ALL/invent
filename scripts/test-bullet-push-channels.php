<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ProductMaster\BulletPointMasterController;
use Illuminate\Http\Request;

$sku = 'SP 12120 4OHM GTR';
$channels = $argv;
array_shift($channels);
if ($channels === []) {
    $channels = ['walmart', 'ebay3', 'doba', 'shein'];
}

$controller = app(BulletPointMasterController::class);
foreach ($channels as $mp) {
    echo "=== {$mp} ===\n";
    $t0 = microtime(true);
    $response = $controller->update(new Request([
        'sku' => $sku,
        'updates' => [['marketplace' => $mp, 'bullet_points' => '']],
    ]));
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $r = $response->getData(true)['results'][$mp] ?? [];
    $ok = ($r['success'] ?? false) ? 'OK' : 'FAIL';
    echo "{$ok} ({$ms}ms): ".($r['message'] ?? 'no message')."\n\n";
}
