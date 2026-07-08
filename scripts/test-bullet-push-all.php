<?php

/**
 * Live bullet push test for all API marketplaces (updates real listings).
 *
 * Usage:
 *   php scripts/test-bullet-push-all.php [--dry-run] [--sku="SP 12120 4OHM GTR"]
 *
 * Logs:
 *   storage/logs/bullet-push-all-{timestamp}.log
 *   storage/app/bullet-push-test/latest.json
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ProductMaster\BulletPointMasterController;
use App\Services\Support\ProductMasterMarketplaceMaps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

$dryRun = in_array('--dry-run', $argv, true);
$sku = config('marketplace_testing.bullet_point_sku', 'SP 12120 4OHM GTR');
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--sku=')) {
        $sku = trim(substr($arg, 6), " \t\n\r\0\x0B\"'");
    }
}

$marketplaces = array_keys(ProductMasterMarketplaceMaps::bulletServiceMap());
$startedAt = now();
$ts = $startedAt->format('Y-m-d_His');
$logDir = storage_path('logs');
$outDir = storage_path('app/bullet-push-test');
File::ensureDirectoryExists($outDir);
$logFile = "{$logDir}/bullet-push-all-{$ts}.log";
$jsonFile = "{$outDir}/latest.json";

$log = function (string $line) use ($logFile): void {
    $row = '['.now()->toIso8601String().'] '.$line.PHP_EOL;
    echo $row;
    file_put_contents($logFile, $row, FILE_APPEND | LOCK_EX);
};

$log(($dryRun ? 'DRY RUN' : 'LIVE PUSH')." bullet test SKU: {$sku}");
$log('Marketplaces: '.count($marketplaces).' — '.implode(', ', $marketplaces));
$log('Log file: '.$logFile);

if (! $dryRun) {
    $log('WARNING: This updates live marketplace listings.');
}

$controller = app(BulletPointMasterController::class);
$results = [];

foreach ($marketplaces as $i => $mp) {
    $n = $i + 1;
    $log("--- [{$n}/".count($marketplaces)."] {$mp} ---");

    if ($dryRun) {
        $audit = app(\App\Services\Support\MarketplaceMasterAuditService::class)->auditBullet($sku, $mp, true);
        $r = $audit[$mp] ?? [];
        $ok = (bool) ($r['ready'] ?? false);
        $msg = implode('; ', array_merge($r['issues'] ?? [], $r['warnings'] ?? [])) ?: 'Ready';
        $results[$mp] = ['success' => $ok, 'message' => $msg, 'dry_run' => true];
        $log(sprintf('%s — %s', $ok ? 'OK' : 'FAIL', $msg));
        continue;
    }

    $t0 = microtime(true);
    $response = $controller->update(new Request([
        'sku' => $sku,
        'updates' => [['marketplace' => $mp, 'bullet_points' => '']],
    ]));
    $elapsedMs = (int) round((microtime(true) - $t0) * 1000);
    $payload = $response->getData(true);
    $r = $payload['results'][$mp] ?? ['success' => false, 'message' => 'No result'];
    $results[$mp] = array_merge($r, ['elapsed_ms' => $elapsedMs]);
    $ok = (bool) ($r['success'] ?? false);
    $attempts = (int) ($r['attempts'] ?? 1);
    $retried = (bool) ($r['retried'] ?? false);
    $log(sprintf(
        '%s — %s (attempts=%d, retried=%s, local_saved=%s, %dms)',
        $ok ? 'OK' : 'FAIL',
        $r['message'] ?? '',
        $attempts,
        $retried ? 'yes' : 'no',
        ($r['local_saved'] ?? false) ? 'yes' : 'no',
        $elapsedMs
    ));

    if (! $dryRun && $i < count($marketplaces) - 1) {
        sleep(2);
    }
}

$okCount = count(array_filter($results, fn ($r) => $r['success'] ?? false));
$failCount = count($results) - $okCount;
$summary = [
    'sku' => $sku,
    'dry_run' => $dryRun,
    'started_at' => $startedAt->toIso8601String(),
    'finished_at' => now()->toIso8601String(),
    'total' => count($results),
    'success_count' => $okCount,
    'failed_count' => $failCount,
    'log_file' => $logFile,
    'results' => $results,
];

File::put($jsonFile, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$log('');
$log("Summary: {$okCount}/".count($results)." succeeded, {$failCount} failed.");
$log('JSON: '.$jsonFile);

exit($okCount === count($results) ? 0 : 1);
