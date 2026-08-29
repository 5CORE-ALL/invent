<?php
/**
 * Production runner: 1) sales  2) price  then stop.
 * Does not run ads / sheets / others.
 */
$root = '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com';
chdir($root);

$php = file_exists('/usr/bin/php8.3') ? '/usr/bin/php8.3' : '/usr/bin/php';
$log = $root . '/storage/logs/kernel-sales-price-' . date('Ymd-His') . '.log';
$selfPid = getmypid();

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

$sales = [
    'app:fetch-amazon-orders --with-items --resync-last-days=7',
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
    'shopify:sync-orders --days=7',
    'shopify:sync-orders --days=60',
    'app:fetch-shopify-b2b-metrics --days=60',
    'app:fetch-shopify-b2c-metrics --days=60',
    'app:snapshot-shopify-b2c-badges',
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
    'sync:tiktok-api-data',
    'sync:tiktok-api-data --channel=tiktok2',
    'sof:snapshot-daily --catch-up --backfill=7',
    'app:update-marketplace-daily-metrics',
    'channel:calculate-data --force',
];

$price = [
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
    'amazon:pull-pushed-prices',
    'walmart:fetch-listed-prices',
    'pef:cvr-cpn-auto-apply',
    'channel:push-sprice-daily',
];

ka_out($log, 'start ' . date('c') . ' php=' . $php . ' pid=' . $selfPid);
ka_out($log, 'order=sales -> price -> STOP');
ka_out($log, 'counts sales=' . count($sales) . ' price=' . count($price));

$failed = [];
$phase = function (string $name, array $cmds) use ($php, $log, &$failed): void {
    $total = count($cmds);
    ka_out($log, '===== PHASE ' . $name . ' count=' . $total . ' =====');
    foreach ($cmds as $i => $cmd) {
        $n = $i + 1;
        echo "[{$name} {$n}/{$total}] {$cmd}" . PHP_EOL;
        $ec = ka_run($php, $log, $cmd);
        if ($ec !== 0) {
            $failed[] = $name . ': ' . $cmd . ' exit=' . $ec;
        }
    }
};

$phase('SALES', $sales);
$phase('PRICE', $price);

ka_out($log, 'DONE ' . date('c') . ' failed=' . count($failed) . ' stopped after PRICE');
if ($failed !== []) {
    ka_out($log, 'FAILED: ' . implode(' | ', $failed));
}
echo 'DONE ' . date('c') . PHP_EOL;
