<?php
/**
 * Sequential production runner: 1) sales  2) last-7-day missed sales fill
 * 3) price  4) everything else.
 * Sales start immediately. Price/others wait for any older Kernel runner.
 */
$root = '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com';
chdir($root);

$php = file_exists('/usr/bin/php8.3') ? '/usr/bin/php8.3' : '/usr/bin/php';
$log = $root . '/storage/logs/kernel-run-all-' . date('Ymd-His') . '.log';
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

function ka_wait_pids(string $log, array $pids, string $label): void
{
    $waited = 0;
    $pids = array_values(array_unique(array_filter($pids, static fn ($pid) => ctype_digit((string) $pid) && (int) $pid > 1)));
    if ($pids === []) {
        return;
    }
    ka_out($log, 'waiting for ' . $label . ' pid(s)=' . implode(',', $pids));
    while (true) {
        $alive = [];
        foreach ($pids as $pid) {
            if (file_exists('/proc/' . $pid)) {
                $alive[] = $pid;
            }
        }
        if ($alive === []) {
            break;
        }
        sleep(20);
        $waited += 20;
        if ($waited % 120 === 0) {
            ka_out($log, 'still waiting for ' . $label . ' after ' . $waited . 's pid(s)=' . implode(',', $alive));
        }
    }
    ka_out($log, $label . ' finished after ' . $waited . 's');
}

function ka_other_kernel_pids(int $selfPid): array
{
    $out = [];
    exec("ps -eo pid=,args= | awk '\$2==\"/usr/bin/php\" && \$3==\"/tmp/run-all-kernel.php\" {print \$1}'", $out);

    return array_values(array_filter($out, static fn ($pid) => ctype_digit((string) $pid) && (int) $pid !== $selfPid));
}

$skip = [
    'users:auto-logout',
    'queue:ensure-watchdog-daemon',
    'storage:ensure --fix',
    'cron-monitor:watchdog',
    'cron-monitor:cleanup',
];

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
    'walmart:fetch-listed-prices',
    'pef:cvr-cpn-auto-apply',
    'channel:push-sprice-daily',
];

$others = [
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
    'channel:calculate-data --force',
];

function ka_dedupe(array $cmds, array $skip): array
{
    $toRun = [];
    $seen = [];
    foreach ($cmds as $cmd) {
        if (in_array($cmd, $skip, true) || isset($seen[$cmd])) {
            continue;
        }
        $seen[$cmd] = true;
        $toRun[] = $cmd;
    }

    return $toRun;
}

$sales = ka_dedupe($sales, $skip);
$price = ka_dedupe($price, $skip);
$others = ka_dedupe($others, $skip);

ka_out($log, 'start ' . date('c') . ' php=' . $php . ' pid=' . $selfPid);
ka_out($log, 'order=sales -> 7-day-missed-fill -> price -> others');
ka_out($log, 'counts sales=' . count($sales) . ' price=' . count($price) . ' others=' . count($others));

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

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tz = 'America/Los_Angeles';
$nowPt = Carbon::now($tz);
$from7 = $nowPt->copy()->subDays(7)->toDateString();
$todayPt = $nowPt->toDateString();
$metricDates = [];
$missed = [];

ka_out($log, '=== 7-DAY SALES GAP CHECK PT from ' . $from7 . ' to ' . $todayPt . ' ===');

if (Schema::hasTable('marketplace_daily_metrics')) {
    $have = DB::table('marketplace_daily_metrics')
        ->where('date', '>=', $from7)
        ->select('date', DB::raw('count(*) as channels'), DB::raw('round(sum(total_sales),2) as sales'))
        ->groupBy('date')
        ->orderBy('date')
        ->get();
    $haveDates = [];
    foreach ($have as $row) {
        $d = substr((string) $row->date, 0, 10);
        $haveDates[] = $d;
        ka_out($log, 'metrics ' . $d . ' channels=' . $row->channels . ' sales=' . $row->sales);
        if ((int) $row->channels < 20) {
            $missed[] = $d;
            ka_out($log, 'THIN marketplace_daily_metrics for ' . $d . ' (channels=' . $row->channels . ')');
        }
    }
    $cursor = Carbon::parse($from7, $tz)->startOfDay();
    $end = $nowPt->copy()->startOfDay();
    while ($cursor->lte($end)) {
        $d = $cursor->toDateString();
        $metricDates[] = $d;
        if (! in_array($d, $haveDates, true)) {
            $missed[] = $d;
            ka_out($log, 'MISSING marketplace_daily_metrics for ' . $d);
        }
        $cursor->addDay();
    }
}

if (Schema::hasTable('amazon_daily_syncs')) {
    $syncs = DB::table('amazon_daily_syncs')
        ->where('sync_date', '>=', $from7)
        ->orderBy('sync_date')
        ->get(['sync_date', 'status', 'orders_fetched', 'items_fetched']);
    $syncDates = [];
    foreach ($syncs as $row) {
        $d = substr((string) $row->sync_date, 0, 10);
        $syncDates[] = $d;
        ka_out($log, 'amazon_sync ' . $d . ' status=' . $row->status . ' orders=' . $row->orders_fetched . ' items=' . $row->items_fetched);
        $isToday = $d === $todayPt;
        $thin = (int) $row->orders_fetched <= 5 && ! $isToday;
        $badStatus = in_array((string) $row->status, ['failed', 'pending', 'in_progress'], true) && ! $isToday;
        if ($thin || $badStatus) {
            $missed[] = $d;
            ka_out($log, 'MISSED amazon sales day ' . $d . ' — will refill');
        }
    }
    $cursor = Carbon::parse($from7, $tz)->startOfDay();
    $end = $nowPt->copy()->startOfDay();
    while ($cursor->lte($end)) {
        $d = $cursor->toDateString();
        if (! in_array($d, $syncDates, true) && $d !== $todayPt) {
            $missed[] = $d;
            ka_out($log, 'MISSING amazon_daily_syncs for ' . $d);
        }
        $cursor->addDay();
    }
}

$missed = array_values(array_unique($missed));
sort($missed);
$metricDates = array_values(array_unique($metricDates));
sort($metricDates);

ka_out($log, 'missed sales days last 7d: ' . ($missed === [] ? '(none)' : implode(', ', $missed)));
ka_out($log, 'will write marketplace_daily_metrics for: ' . implode(', ', $metricDates));

$fill = [];
if ($missed !== []) {
    $fill[] = 'app:fetch-amazon-orders --with-items --resync-last-days=7';
    $fill[] = 'app:fetch-ebay-orders';
    $fill[] = 'app:fetch-ebay2-orders';
    $fill[] = 'shopify:sync-orders --days=7';
}
foreach ($metricDates as $date) {
    $fill[] = 'app:update-marketplace-daily-metrics --date=' . $date;
}
$fill[] = 'channel:calculate-data --force';

$phase('FILL-7D', $fill);

ka_wait_pids($log, ka_other_kernel_pids($selfPid), 'older-kernel-runner');

$phase('PRICE', $price);
$phase('OTHERS', $others);

ka_out($log, 'DONE ' . date('c') . ' failed=' . count($failed));
if ($failed !== []) {
    ka_out($log, 'FAILED: ' . implode(' | ', $failed));
}
echo 'DONE ' . date('c') . PHP_EOL;
