<?php
require '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/vendor/autoload.php';
$app = require '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ReverbMetric;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use App\Services\MarketplaceManager\ReverbLiveListingsService;
use App\Services\MarketplaceManager\ReverbInventorySyncService;
use App\Services\ReverbApiService;

$doFix = getenv('MM_FIX') === '1';
$limit = (int) getenv('MM_LIMIT');
$logPath = '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com/storage/logs/mm-sku-sync-check.log';
@unlink($logPath);

function logline(string $path, string $msg): void {
    $line = '['.date('c').'] '.$msg.PHP_EOL;
    file_put_contents($path, $line, FILE_APPEND);
}

logline($logPath, 'START fix='.($doFix?'yes':'no').' limit='.($limit>0?$limit:'all'));

$liveService = app(ReverbLiveListingsService::class);
$syncService = app(ReverbInventorySyncService::class);
if (! ReverbApiService::getReverbBearerToken()) {
    logline($logPath, 'FATAL: no Reverb token');
    exit(1);
}

$metrics = ReverbMetric::query()
    ->whereNotNull('product_id')
    ->where('sku', '!=', '')
    ->whereColumn('sku', '!=', 'product_id')
    ->orderBy('sku')
    ->get(['sku', 'product_id']);
if ($limit > 0) {
    $metrics = $metrics->take($limit);
}

$total = $metrics->count();
logline($logPath, "checking {$total} linked SKUs");

$stats = [
    'checked' => 0,
    'match' => 0,
    'mismatch' => 0,
    'shopify_missing' => 0,
    'draft_skip' => 0,
    'sold_restock_needed' => 0,
    'fixed_ok' => 0,
    'fixed_fail' => 0,
    'reverb_fetch_fail' => 0,
];

foreach ($metrics->chunk(15) as $chunkIndex => $chunk) {
    $skus = $chunk->pluck('sku')->map(fn ($s) => (string) $s)->values()->all();
    $shopMap = $liveService->liveShopifyQtyBySkus($skus);
    $ids = $chunk->pluck('product_id')->map(fn ($id) => (string) $id)->values()->all();
    $liveRv = $liveService->liveDetailsByListingIds($ids);

    foreach ($chunk as $m) {
        $sku = (string) $m->sku;
        $pid = (string) $m->product_id;
        $stats['checked']++;

        $upper = strtoupper($sku);
        $shopifyQty = array_key_exists($upper, $shopMap) ? (int) $shopMap[$upper] : null;
        $live = $liveRv[$pid] ?? null;

        if ($live === null) {
            $stats['reverb_fetch_fail']++;
            logline($logPath, "FAIL_FETCH sku={$sku} pid={$pid} shopify=".($shopifyQty === null ? 'null' : $shopifyQty));
            continue;
        }

        $state = strtolower(trim((string) ($live['state'] ?? '')));
        $rvQty = (int) ($live['inventory'] ?? 0);

        if (MarketplaceLiveInventoryRules::reverbIsDraftLike($state)) {
            $stats['draft_skip']++;
            logline($logPath, "SKIP_DRAFT sku={$sku} pid={$pid} state={$state} shopify=".($shopifyQty === null ? 'null' : $shopifyQty)." rv={$rvQty}");
            continue;
        }

        if ($shopifyQty === null) {
            $stats['shopify_missing']++;
            logline($logPath, "SHOPIFY_MISSING sku={$sku} pid={$pid} state={$state} rv={$rvQty}");
            continue;
        }

        $want = MarketplaceLiveInventoryRules::qtyFromLiveShopify($shopifyQty);
        $soldNeeds = $want > 0 && MarketplaceLiveInventoryRules::reverbIsSoldOutLike($state);
        $ok = (! $soldNeeds) && ($want === $rvQty);

        if ($ok) {
            $stats['match']++;
            // log every 25th OK to keep file smaller but still auditable progress
            if ($stats['checked'] % 25 === 0 || $stats['match'] <= 5) {
                logline($logPath, "OK sku={$sku} pid={$pid} state={$state} shopify={$shopifyQty} want={$want} rv={$rvQty}");
            }
            continue;
        }

        if ($soldNeeds) {
            $stats['sold_restock_needed']++;
        }
        $stats['mismatch']++;
        logline($logPath, "MISMATCH sku={$sku} pid={$pid} state={$state} shopify={$shopifyQty} want={$want} rv={$rvQty}");

        if (! $doFix) {
            continue;
        }
        if ($state !== '' && ! MarketplaceLiveInventoryRules::reverbMayUpdateInventory($state)) {
            logline($logPath, "FIX_SKIP_STATE sku={$sku} state={$state}");
            continue;
        }

        $result = $syncService->syncSkusFromShopify([$sku]);
        usleep(200000);
        $verify = $liveService->liveDetailsByListingIds([$pid]);
        $v = $verify[$pid] ?? null;
        $vState = strtolower(trim((string) ($v['state'] ?? '')));
        $vInv = (int) ($v['inventory'] ?? -1);
        $fixed = $v !== null && (int) $vInv === (int) $want && ($want === 0 || $vState === 'live' || ! MarketplaceLiveInventoryRules::reverbIsSoldOutLike($vState));

        if ($fixed) {
            $stats['fixed_ok']++;
            logline($logPath, "FIXED sku={$sku} now_state={$vState} now_rv={$vInv} want={$want}");
        } else {
            $stats['fixed_fail']++;
            logline($logPath, "FIX_FAIL sku={$sku} now_state={$vState} now_rv={$vInv} want={$want} api=".json_encode($result));
        }
    }

    logline($logPath, 'PROGRESS checked='.$stats['checked'].'/'.$total.' match='.$stats['match'].' mismatch='.$stats['mismatch'].' fixed_ok='.$stats['fixed_ok'].' fixed_fail='.$stats['fixed_fail']);
}

logline($logPath, 'SUMMARY '.json_encode($stats));
logline($logPath, 'DONE');