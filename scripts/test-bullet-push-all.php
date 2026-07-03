<?php

/**
 * Live bullet push test for all API marketplaces (updates real listings).
 * Usage: php scripts/test-bullet-push-all.php [--dry-run]
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ProductMaster\BulletPointMasterController;
use App\Services\Support\ProductMasterMarketplaceMaps;
use Illuminate\Http\Request;

$dryRun = in_array('--dry-run', $argv, true);
$sku = config('marketplace_testing.bullet_point_sku', 'SP 12120 4OHM GTR');
$marketplaces = array_keys(ProductMasterMarketplaceMaps::bulletServiceMap());

echo ($dryRun ? "DRY RUN" : "LIVE PUSH")." bullet test SKU: {$sku}\n\n";

if (! $dryRun) {
    echo "WARNING: This updates live marketplace listings.\n\n";
}

$controller = app(BulletPointMasterController::class);
$results = [];

foreach ($marketplaces as $mp) {
    if ($dryRun) {
        $audit = app(\App\Services\Support\MarketplaceMasterAuditService::class)->auditBullet($sku, $mp, true);
        $r = $audit[$mp] ?? [];
        $ok = (bool) ($r['ready'] ?? false);
        $msg = implode('; ', array_merge($r['issues'] ?? [], $r['warnings'] ?? [])) ?: 'Ready';
        $results[$mp] = ['success' => $ok, 'message' => $msg];
        echo sprintf("[%s] %s — %s\n", $ok ? 'OK' : 'FAIL', $mp, $msg);
        continue;
    }

    $response = $controller->update(new Request([
        'sku' => $sku,
        'updates' => [['marketplace' => $mp, 'bullet_points' => '']],
    ]));
    $payload = $response->getData(true);
    $r = $payload['results'][$mp] ?? ['success' => false, 'message' => 'No result'];
    $results[$mp] = $r;
    $ok = (bool) ($r['success'] ?? false);
    echo sprintf("[%s] %s — %s\n", $ok ? 'OK' : 'FAIL', $mp, $r['message'] ?? '');
    sleep(2);
}

$okCount = count(array_filter($results, fn ($r) => $r['success'] ?? false));
echo "\nSummary: {$okCount}/".count($results)." succeeded.\n";

exit($okCount === count($results) ? 0 : 1);
