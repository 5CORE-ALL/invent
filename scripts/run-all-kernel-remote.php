<?php
/**
 * Sequential production runner for every unique Kernel artisan command.
 * Waits for an in-flight sales-backfill so Amazon/eBay pulls do not overlap.
 */
$root = '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com';
chdir($root);

$php = file_exists('/usr/bin/php8.3') ? '/usr/bin/php8.3' : '/usr/bin/php';
$log = $root . '/storage/logs/kernel-run-all-' . date('Ymd-His') . '.log';

function ka_out(string $log, string $msg): void
{
    $line = $msg . PHP_EOL;
    echo $line;
    file_put_contents($log, $line, FILE_APPEND);
}

function ka_run(string $php, string $log, string $cmd): int
{
    ka_out($log, '===== START ' . $cmd . ' ' . date('c') . ' =====');
    $ec = 0;
    passthru($php . ' artisan ' . $cmd . ' >> ' . escapeshellarg($log) . ' 2>&1', $ec);
    ka_out($log, '===== END ' . $cmd . ' exit=' . $ec . ' ' . date('c') . ' =====');

    return (int) $ec;
}

$skip = [
    'users:auto-logout',
    'queue:ensure-watchdog-daemon',
    'storage:ensure --fix',
    'cron-monitor:watchdog',
    'cron-monitor:cleanup',
];

$cmds = [
    // Sales / orders / daily metrics (Kernel scheduleSalesCommands)
    'app:fetch-amazon-orders --auto-sync --with-items',
    'app:fetch-fba-reports',
    'app:fetch-fba-monthly-sales',
    'fba:collect-metrics',
    'fba:save-daily-metrics',
    'amazon:store-listing-daily-metrics',
    'amazon:collect-metrics',
    'app:fetch-ebay-orders',
    'app:fetch-ebay2-orders',
    'ebay3:daily --days=60',
    'app:fetch-ebay-reports',
    'app:fetch-ebay-table-data',
    'app:fetch-ebay-two-metrics',
    'app:fetch-ebay-three-metrics',
    'ebay:collect-metrics',
    'ebay2:collect-metrics',
    'tiktok:collect-metrics',
    'shopify:sync-orders --days=2',
    'shopify:sync-orders --days=60',
    'app:fetch-shopify-b2b-metrics --days=60',
    'app:fetch-shopify-b2c-metrics --days=60',
    'wayfair:daily --days=60',
    'sync:wayfair-l30-api',
    'reverb:daily --days=60',
    'reverb:collect-metrics',
    'app:fetch-macy-products',
    'purchasing-power:sync --days=60',
    'app:fetch-wayfair-data',
    'mirakl:daily --days=60',
    'app:fetch-temu-orders',
    'app:fetch-temu2-orders --days=60',
    'app:fetch-pls-sales-data --days=90',
    'app:fetch-temu-metrics',
    'app:fetch-temu2-metrics',
    'temu:collect-metrics',
    'doba:daily --days=60',
    'app:fetch-doba-metrics',
    'shein:fetch orders --days=30 --target=l30',
    'shein:fetch orders --days=60 --target=l60',
    'app:fetch-pls-data',
    'newegg:orders --days=60 --save',
    'sync:walmart-metrics-data',
    'walmart:fetch-orders --days=60',
    'tiktok:fetch-orders --days=60 --prune',
    'tiktok:fetch-orders --channel=tiktok2 --days=60 --prune',
    'app:update-marketplace-daily-metrics',
    'sync:tiktok-api-data',
    'sync:tiktok-api-data --channel=tiktok2',
    'sof:snapshot-daily',
    'sof:snapshot-daily --catch-up --backfill=3',

    // Price / listed / competitor (schedulePriceCommands)
    'app:fetch-fba-inventory --insert --prices',
    'amazon:dil-prmt-auto-push',
    'amazon:cvr-cpn-auto-push',
    'ebay:update-prices',
    'ebay:update-sku-prices',
    'amazon:update-prices',
    'amazon:update-sku-prices',
    'google:update-sku-prices --skip-search-refresh',
    'store:sync-prices',
    'app:fetch-aliexpress-metrics --listed',
    'temu:fetch-recommended-prices --both',
    'shein:fetch sync',
    'products:recalc-lp',
    'sync:amazon-prices',
    'walmart:fetch-listed-prices',
    'pef:dil-prmt-auto-apply',
    'pef:cvr-cpn-auto-apply',
    'channel:push-sprice-daily',

    // Ads / inventory / sheets / other (scheduleOtherCommands + retryFiveTimesUntil)
    'amazon:sync-inventory',
    'app:fetch-amazon-listings',
    'amazon:sync-products --enrich --enrich-limit=200',
    'app:amazon-sp-campaign-reports',
    'app:amazon-sb-campaign-reports',
    'app:amazon-sd-campaign-reports',
    'app:amazon-sp-keyword-reports',
    'app:amazon-sp-negative-keywords --prune',
    'amazon:auto-update-over-kw-bids',
    'amazon:auto-update-under-kw-bids',
    'amazon:auto-update-over-pt-bids',
    'amazon:auto-update-under-pt-bids',
    'amazon:auto-update-over-hl-bids',
    'amazon:auto-update-under-hl-bids',
    'amazon:auto-update-amz-bgt-kw',
    'amazon:auto-update-amz-bgt-pt',
    'amazon:auto-update-amz-bgt-hl',
    'amazon-fba:auto-update-under-pt-bids',
    'amazon-fba:auto-update-over-pt-bids',
    'amazon-fba:auto-update-over-kw-bids',
    'amazon-fba:auto-update-under-kw-bids',
    'fba:sync-shipment-status',
    'amazon:store-utilization-counts',
    'amazon-fba:store-utilization-counts',
    'channel:collect-yesterday-views',
    'amazon:pull-buybox --lot=40',
    'amazon:collect-reviews',
    'app:ebay-campaign-reports',
    'app:ebay2-campaign-reports',
    'app:ebay3-campaign-reports',
    'ebay:sync-campaign-listings',
    'ebay2:sync-campaign-listings',
    'ebay3:sync-campaign-listings',
    'ebay:auto-update-over-bids',
    'ebay:auto-update-under-bids',
    'ebay2:auto-update-utilized-bids',
    'ebay3:auto-update-utilized-bids',
    'ebay3:update-suggestedbid',
    'ebay1:update-budget',
    'ebay2:update-budget',
    'ebay3:update-budget',
    'ebay:store-utilization-counts',
    'app:fetch-google-ads-campaigns',
    'ga4:fetch-campaign-data --days=30',
    'app:fetch-google-ads-negative-keywords --prune',
    'google:save-badge-l30-snapshots',
    'sbid:update',
    'sbid:update-serp',
    'budget:update-shopping',
    'budget:update-serp',
    'google:store-shopping-utilization-counts',
    'meta:sync-all-ads',
    'meta-ads:sync',
    'meta-ads:sync --insights-only',
    'shopify:fetch-meta-campaigns --channel=both',
    'meta-ads:run-automation',
    'shopify:sync --store=main',
    'sync:shopify-quantity',
    'app:fetch-shopify-product-views --days=30',
    'reverb:fetch',
    'reverb:fetch --skip-bump',
    'reverb:sync-listing-statuses',
    'topdawg:fetch',
    'app:fetch-aliexpress-metrics --views',
    'app:fetch-aliexpress-metrics --reviews',
    'aliexpress:sync-link-map',
    'alibaba:sync-link-map',
    'reverb:manager-sync-link-map',
    'newegg:sync-link-map',
    'shein:sync-link-map',
    'amazon:sync-link-map',
    'topdawg:sync-link-map',
    'temu:sync-link-map',
    'temu2:sync-link-map',
    'purchasingpower:sync-link-map',
    'wayfair:sync-link-map',
    'bestbuy:sync-link-map',
    'macy:sync-link-map',
    'doba:sync-link-map',
    'ebay1:sync-link-map',
    'ebay2:sync-link-map',
    'ebay3:sync-link-map',
    'faire:sync-link-map',
    'mm:dispatch-unpushed-shopify',
    'temu:fetch-ads-data --period=L30',
    'temu:fetch-ads-data --period=L60',
    'temu:fetch-ads-api-reports --period=L7',
    'temu:refresh-ad-status',
    'temu:auto-pause-ads',
    'amazon:ads-pause-rule',
    'temu2:fetch-ads-data --period=L30',
    'temu2:fetch-ads-data --period=L60',
    'temu2:fetch-ads-api-reports --period=L7',
    'temu2:auto-pause-ads',
    'app:sync-sheet',
    'app:sync-mercari-w-ship-sheet',
    'app:sync-mercari-wo-ship-sheet',
    'app:sync-fb-shop-sheet',
    'app:sync-fb-marketplace-sheet',
    'app:top-dawg-shop-sheet',
    'shopify-pls:sync',
    'sync:neweegg-sheet',
    'app:sync-cp-master-to-sheet',
    'app:process-jungle-scout-sheet-data',
    'app:aliexpress-sheet-sync',
    'tiktok:sync-gmv-ads --force',
    'stock:update-mapping-daily',
    'inventory:snapshot',
    'badges:save-all',
    'reviews:analyze --batch=100',
    'tracking:sync-status --only-open --repair-quota --catch-up --limit=800',
    'fulfillment:refresh-shipment-status --skip-tracking --days=30',
    'sof:pull-missing-tracking --limit=200 --temu-limit=40',
    'cc:pull-pending-messages',
    'attendance:analyze',
    'payroll:fetch-fx-rates',
    'tasks:generate-daily-automated',
    'tasks:expire-missed-automated',
    'tasks:automated-health-alert',
    'tasks:execute-automated',
    'tasks:assign-amz-lvv-mismatch-daily',
    'tasks:assign-missing-mapping-daily',

    // Recalc channel master after all pulls
    'channel:calculate-data --force',
    'channel:calculate-data --force',
    'channel:calculate-data --force',
];

$toRun = [];
$seen = [];
foreach ($cmds as $cmd) {
    if (in_array($cmd, $skip, true) || isset($seen[$cmd])) {
        continue;
    }
    $seen[$cmd] = true;
    $toRun[] = $cmd;
}

$total = count($toRun);
ka_out($log, 'start ' . date('c') . ' php=' . $php . ' count=' . $total);

$waited = 0;
while (true) {
    $out = [];
    exec("ps -eo pid=,args= | awk '\$2==\"/usr/bin/php\" && \$3==\"/tmp/run-sales-backfill.php\" {print \$1}'", $out);
    $out = array_values(array_filter($out, static fn ($pid) => ctype_digit((string) $pid) && (int) $pid !== getmypid()));
    if ($out === []) {
        break;
    }
    if ($waited === 0) {
        ka_out($log, 'waiting for in-flight sales-backfill pid(s)=' . implode(',', $out));
    }
    sleep(20);
    $waited += 20;
    if ($waited % 120 === 0) {
        ka_out($log, 'still waiting for sales-backfill after ' . $waited . 's');
    }
}
if ($waited > 0) {
    ka_out($log, 'sales-backfill finished after ' . $waited . 's — starting Kernel queue');
}

$failed = [];
foreach ($toRun as $i => $cmd) {
    $n = $i + 1;
    echo "[{$n}/{$total}] {$cmd}" . PHP_EOL;
    $ec = ka_run($php, $log, $cmd);
    if ($ec !== 0) {
        $failed[] = $cmd . ' exit=' . $ec;
    }
}

ka_out($log, 'DONE ' . date('c') . ' failed=' . count($failed));
if ($failed !== []) {
    ka_out($log, 'FAILED: ' . implode(' | ', $failed));
}
echo 'DONE ' . date('c') . PHP_EOL;
