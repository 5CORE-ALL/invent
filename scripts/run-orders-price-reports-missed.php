<?php
/**
 * Production one-shot: ORDERS → PRICE → REPORTS → remaining missed.
 * Skip already-running artisan. Do not start a second copy of this runner.
 */
$root = '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com';
chdir($root);

$php = file_exists('/usr/bin/php8.3') ? '/usr/bin/php8.3' : '/usr/bin/php';
$log = $root . '/storage/logs/orders-price-reports-missed-' . date('Ymd-His') . '.log';
$selfPid = getmypid();

function opr_out(string $log, string $msg): void
{
    $line = $msg . PHP_EOL;
    echo $line;
    file_put_contents($log, $line, FILE_APPEND);
}

function opr_running(string $cmd): bool
{
    $base = explode(' ', $cmd, 2)[0];
    $out = [];
    exec('pgrep -af '.escapeshellarg('artisan '.$base).' 2>/dev/null', $out);
    foreach ($out as $line) {
        if (str_contains($line, 'pgrep') || str_contains($line, 'orders-price-reports-missed')) {
            continue;
        }
        if (str_contains($line, 'artisan '.$base)) {
            return true;
        }
    }

    return false;
}

function opr_run(string $php, string $log, string $cmd): int
{
    opr_out($log, '===== START '.$cmd.' '.date('c').' =====');
    if (opr_running($cmd)) {
        opr_out($log, 'SKIP already running: '.$cmd);
        opr_out($log, '===== SKIP '.$cmd.' '.date('c').' =====');

        return 0;
    }
    $ec = 0;
    passthru($php.' artisan '.$cmd.' >> '.escapeshellarg($log).' 2>&1', $ec);
    opr_out($log, '===== END '.$cmd.' exit='.$ec.' '.date('c').' =====');

    return (int) $ec;
}

$orders = [
    'mm:push-orders-tracking --days=7 --skip-inventory',
    'app:fetch-amazon-orders --auto-sync --with-items',
    'app:fetch-amazon-orders --with-items --resync-last-days=7',
    'app:fetch-ebay-orders',
    'app:fetch-ebay2-orders',
    'ebay3:daily --days=60',
    'shopify:sync-orders --days=60',
    'shopify:sync-orders --days=2',
    'tiktok:fetch-orders --days=60 --prune',
    'tiktok:fetch-orders --channel=tiktok2 --days=60 --prune',
    'newegg:orders --days=60 --save',
    'app:fetch-temu-orders',
    'app:fetch-temu2-orders --days=60',
    'wayfair:daily --days=60',
    'reverb:daily --days=60',
    'doba:daily --days=60',
    'shein:fetch orders --days=30 --target=l30',
    'shein:fetch orders --days=60 --target=l60',
    'app:fetch-pls-sales-data --days=90',
    'app:fetch-macy-products',
    'purchasing-power:sync --days=60',
    'mirakl:daily --days=60',
];

$price = [
    'app:fetch-fba-inventory --insert --prices',
    'newegg:item-data --save --source=catalog',
    'store:sync-prices',
    'app:fetch-aliexpress-metrics --listed',
    'temu:fetch-recommended-prices --both',
    'shein:fetch sync',
    'walmart:fetch-listed-prices',
    'sync:amazon-prices',
    'shopify-b2c:rule-sprice-apply',
    'channel:push-sprice-daily',
    'products:recalc-lp',
];

$reports = [
    'app:fetch-fba-reports',
    'app:fetch-fba-monthly-sales',
    'fba:collect-metrics',
    'fba:save-daily-metrics',
    'amazon:store-listing-daily-metrics',
    'amazon:collect-metrics',
    'app:fetch-ebay-reports',
    'app:fetch-ebay-table-data',
    'app:fetch-ebay-two-metrics',
    'app:fetch-ebay-three-metrics',
    'ebay:collect-metrics',
    'ebay2:collect-metrics',
    'tiktok:collect-metrics',
    'app:fetch-shopify-b2b-metrics --days=60',
    'app:fetch-shopify-b2c-metrics --days=60',
    'app:fetch-shopify-product-views --days=30',
    'sync:wayfair-l30-api',
    'reverb:collect-metrics',
    'app:fetch-temu-metrics',
    'app:fetch-temu2-metrics',
    'temu:collect-metrics',
    'app:fetch-doba-metrics',
    'app:fetch-pls-data',
    'sync:tiktok-api-data',
    'sync:tiktok-api-data --channel=tiktok2',
    'sync:walmart-metrics-data',
    'app:amazon-sp-campaign-reports',
    'app:amazon-sb-campaign-reports',
    'app:amazon-sd-campaign-reports',
    'amazon:ads-pull-product-ads',
    'app:amazon-sp-keyword-reports',
    'app:amazon-sp-negative-keywords --prune',
    'app:ebay-campaign-reports',
    'app:ebay2-campaign-reports',
    'app:ebay3-campaign-reports',
    'app:fetch-google-ads-campaigns',
    'ga4:fetch-campaign-data --days=30',
    'app:fetch-google-ads-negative-keywords --prune',
    'google:save-badge-l30-snapshots',
    'shopify:fetch-meta-campaigns --channel=both',
    'temu:fetch-ads-data --period=L30',
    'temu:fetch-ads-data --period=L60',
    'temu:fetch-ads-api-reports --period=L7',
    'temu2:fetch-ads-data --period=L30',
    'temu2:fetch-ads-data --period=L60',
    'temu2:fetch-ads-api-reports --period=L7',
    'tiktok:sync-gmv-ads --force',
    'app:process-jungle-scout-sheet-data',
    'channel:calculate-data --force',
];

$rest = [
    'amazon:sync-inventory',
    'app:fetch-amazon-listings',
    'amazon:sync-products --enrich --enrich-limit=200',
    'amazon:auto-update-over-kw-bids',
    'amazon:auto-update-under-kw-bids',
    'amazon:auto-update-over-pt-bids',
    'amazon:auto-update-under-pt-bids',
    'amazon:auto-update-over-hl-bids',
    'amazon:auto-update-under-hl-bids',
    'amazon:auto-update-amz-bgt-kw',
    'amazon:auto-update-amz-bgt-pt',
    'amazon:auto-update-amz-bgt-hl',
    'amazon-fba:auto-update-over-pt-bids',
    'amazon-fba:auto-update-over-kw-bids',
    'amazon-fba:auto-update-under-kw-bids',
    'fba:sync-shipment-status',
    'amazon:store-utilization-counts',
    'amazon-fba:store-utilization-counts',
    'channel:collect-yesterday-views',
    'amazon:pull-buybox --lot=40',
    'amazon:collect-reviews',
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
    'sbid:update',
    'sbid:update-serp',
    'budget:update-shopping',
    'budget:update-serp',
    'google:store-shopping-utilization-counts',
    'meta:sync-all-ads',
    'meta-ads:sync',
    'meta-ads:run-automation',
    'temu:refresh-ad-status',
    'temu:auto-pause-ads',
    'temu2:auto-pause-ads',
    'shopify:sync --store=main',
    'sync:shopify-quantity',
    'shopify:sync-customers',
    'reverb:fetch',
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
    'app:snapshot-shopify-b2c-badges',
    'app:sync-sheet',
    'app:sync-mercari-w-ship-sheet',
    'app:sync-mercari-wo-ship-sheet',
    'app:sync-fb-shop-sheet',
    'app:sync-fb-marketplace-sheet',
    'app:top-dawg-shop-sheet',
    'shopify-pls:sync',
    'sync:neweegg-sheet',
    'app:sync-cp-master-to-sheet',
    'stock:update-mapping-daily',
    'inventory:snapshot',
    'badges:save-all',
    'reviews:analyze --batch=100',
    'fulfillment:refresh-shipment-status --skip-tracking --days=30',
    'attendance:analyze',
    'tasks:assign-amz-lvv-mismatch-daily',
    'tasks:assign-missing-mapping-daily',
];

$skipZombie = [
    'amazon-fba:auto-update-under-pt-bids',
];

$seen = [];
$failed = [];
$phase = function (string $name, array $cmds) use ($php, $log, $skipZombie, &$seen, &$failed): void {
    $uniq = [];
    foreach ($cmds as $cmd) {
        $base = explode(' ', $cmd, 2)[0];
        if (in_array($base, $skipZombie, true) || isset($seen[$cmd])) {
            continue;
        }
        $seen[$cmd] = true;
        $uniq[] = $cmd;
    }
    $total = count($uniq);
    opr_out($log, '===== PHASE '.$name.' count='.$total.' =====');
    foreach ($uniq as $i => $cmd) {
        $n = $i + 1;
        echo "[{$name} {$n}/{$total}] {$cmd}".PHP_EOL;
        $ec = opr_run($php, $log, $cmd);
        if ($ec !== 0) {
            $failed[] = $name.': '.$cmd.' exit='.$ec;
        }
    }
};

opr_out($log, 'start '.date('c').' php='.$php.' pid='.$selfPid);
opr_out($log, 'order=ORDERS -> PRICE -> REPORTS -> REST');

$phase('ORDERS', $orders);
$phase('PRICE', $price);
$phase('REPORTS', $reports);
$phase('REST', $rest);

opr_out($log, 'DONE '.date('c').' failed='.count($failed));
if ($failed !== []) {
    opr_out($log, 'FAILED: '.implode(' | ', $failed));
}
echo 'DONE '.date('c').PHP_EOL;
