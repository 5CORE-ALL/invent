<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sku = $argv[1] ?? 'GSP 1030 16';
$only = isset($argv[2]) ? explode(',', $argv[2]) : null;

$marketplaces = [
    'shopify_main',
    'shopify_pls',
    'amazon',
    'ebay',
    'ebay2',
    'ebay3',
    'temu',
    'wayfair',
    'bestbuy',
    'macy',
    'reverb',
];

if ($only !== null) {
    $marketplaces = array_values(array_intersect($marketplaces, array_map('trim', $only)));
}

$product = App\Models\ProductMaster::query()->where('sku', $sku)->first();
if (! $product) {
    fwrite(STDERR, "Product not found: {$sku}\n");
    exit(1);
}

$videos = [];
for ($i = 1; $i <= 10; $i++) {
    $col = 'video'.$i;
    $val = trim((string) ($product->{$col} ?? ''));
    if ($val !== '') {
        $videos[] = $val;
    }
}

if ($videos === []) {
    fwrite(STDERR, "No videos on Product Master for {$sku}\n");
    exit(1);
}

echo "SKU: {$sku}\n";
echo 'Videos: '.count($videos)."\n";
foreach ($videos as $i => $u) {
    echo '  '.($i + 1).'. '.mb_substr($u, 0, 100).(strlen($u) > 100 ? '...' : '')."\n";
}
echo str_repeat('-', 72)."\n";

/** @var App\Http\Controllers\ProductMaster\VideoMasterController $controller */
$controller = app(App\Http\Controllers\ProductMaster\VideoMasterController::class);

$results = [];
foreach ($marketplaces as $mp) {
    echo "\n[{$mp}] pushing...\n";
    $start = microtime(true);
    try {
        $res = $controller->runQueuedMarketplacePush($sku, $mp, $videos, 'replace', [], false);
        $ok = (bool) ($res['success'] ?? false);
        $msg = (string) ($res['message'] ?? '');
        $elapsed = round(microtime(true) - $start, 1);
        $results[$mp] = ['ok' => $ok, 'message' => $msg, 'seconds' => $elapsed];
        echo ($ok ? 'OK' : 'FAIL')." ({$elapsed}s)\n";
        echo $msg."\n";
    } catch (Throwable $e) {
        $elapsed = round(microtime(true) - $start, 1);
        $results[$mp] = ['ok' => false, 'message' => $e->getMessage(), 'seconds' => $elapsed];
        echo "FAIL ({$elapsed}s)\n";
        echo $e->getMessage()."\n";
    }
}

echo "\n".str_repeat('=', 72)."\n";
echo "SUMMARY for {$sku}\n";
echo str_repeat('=', 72)."\n";
$okCount = 0;
foreach ($results as $mp => $r) {
    $flag = $r['ok'] ? 'OK  ' : 'FAIL';
    if ($r['ok']) {
        $okCount++;
    }
    echo sprintf("%-14s %s  (%ss) %s\n", $mp, $flag, $r['seconds'], mb_substr($r['message'], 0, 120));
}
echo str_repeat('-', 72)."\n";
echo "Passed: {$okCount}/".count($results)."\n";

exit($okCount === count($results) ? 0 : 1);
