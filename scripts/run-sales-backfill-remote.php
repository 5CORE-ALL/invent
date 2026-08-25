<?php
/**
 * Production sales catch-up: fetch/insert last 2–3 days of marketplace sales,
 * backfill missing marketplace_daily_metrics rows, then recalculate channel master.
 */
$root = '/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com';
chdir($root);

$php = file_exists('/usr/bin/php8.3') ? '/usr/bin/php8.3' : '/usr/bin/php';
$log = $root . '/storage/logs/sales-backfill-' . date('Ymd-His') . '.log';

function sb_out(string $log, string $msg): void
{
    $line = $msg . PHP_EOL;
    echo $line;
    file_put_contents($log, $line, FILE_APPEND);
}

function sb_run(string $php, string $log, string $cmd): int
{
    sb_out($log, '===== START ' . $cmd . ' ' . date('c') . ' =====');
    $ec = 0;
    passthru($php . ' artisan ' . $cmd . ' >> ' . escapeshellarg($log) . ' 2>&1', $ec);
    sb_out($log, '===== END ' . $cmd . ' exit=' . $ec . ' ' . date('c') . ' =====');

    return (int) $ec;
}

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tz = 'America/Los_Angeles';
$nowPt = Carbon::now($tz);
$from5 = $nowPt->copy()->subDays(5)->toDateString();
$metricDates = [];
for ($i = 3; $i >= 0; $i--) {
    $metricDates[] = $nowPt->copy()->subDays($i)->toDateString();
}

sb_out($log, 'start ' . date('c') . ' PT=' . $nowPt->toDateTimeString() . ' php=' . $php);
sb_out($log, '=== GAP CHECK last 5 PT days from ' . $from5 . ' ===');

if (Schema::hasTable('marketplace_daily_metrics')) {
    $have = DB::table('marketplace_daily_metrics')
        ->where('date', '>=', $from5)
        ->select('date', DB::raw('count(*) as channels'), DB::raw('sum(total_sales) as sales'))
        ->groupBy('date')
        ->orderBy('date')
        ->get();
    $haveDates = [];
    foreach ($have as $row) {
        $haveDates[] = $row->date;
        sb_out($log, 'metrics ' . $row->date . ' channels=' . $row->channels . ' sales=' . $row->sales);
    }
    $cursor = Carbon::parse($from5, $tz);
    $end = $nowPt->copy()->startOfDay();
    while ($cursor->lte($end)) {
        $d = $cursor->toDateString();
        if (! in_array($d, $haveDates, true)) {
            sb_out($log, 'MISSING marketplace_daily_metrics for ' . $d);
            if (! in_array($d, $metricDates, true)) {
                $metricDates[] = $d;
            }
        }
        $cursor->addDay();
    }
}

sort($metricDates);
$metricDates = array_values(array_unique($metricDates));
sb_out($log, 'will insert/update marketplace_daily_metrics dates: ' . implode(', ', $metricDates));

if (Schema::hasTable('cron_execution_logs')) {
    $missed = DB::table('cron_execution_logs')
        ->where('created_at', '>=', $from5)
        ->whereIn('status', ['missed', 'failed', 'timed_out', 'stuck'])
        ->orderByDesc('id')
        ->limit(40)
        ->get(['status', 'job_name', 'command', 'started_at', 'error_message']);
    sb_out($log, '=== cron missed/failed last 5d count=' . $missed->count() . ' ===');
    foreach ($missed as $row) {
        sb_out($log, $row->status . ' | ' . $row->started_at . ' | ' . ($row->job_name ?: $row->command));
    }
}

$salesCmds = [
    'app:fetch-amazon-orders --auto-sync --with-items --resync-last-days=3',
    'app:fetch-fba-reports',
    'app:fetch-fba-monthly-sales',
    'fba:collect-metrics',
    'fba:save-daily-metrics',
    'amazon:collect-metrics',
    'amazon:store-listing-daily-metrics',
    'app:fetch-ebay-orders',
    'app:fetch-ebay2-orders',
    'ebay3:daily --days=60',
    'app:fetch-ebay-reports',
    'app:fetch-ebay-table-data',
    'app:fetch-ebay-two-metrics',
    'app:fetch-ebay-three-metrics',
    'ebay:collect-metrics',
    'ebay2:collect-metrics',
    'shopify:sync-orders --days=3',
    'app:fetch-shopify-b2b-metrics --days=60',
    'app:fetch-shopify-b2c-metrics --days=60',
    'wayfair:daily --days=60',
    'sync:wayfair-l30-api',
    'app:fetch-wayfair-data',
    'reverb:daily --days=60',
    'reverb:collect-metrics',
    'mirakl:daily --days=60',
    'app:fetch-temu-orders',
    'app:fetch-temu2-orders',
    'app:fetch-temu-metrics',
    'app:fetch-temu2-metrics',
    'temu:collect-metrics',
    'doba:daily --days=60',
    'app:fetch-doba-metrics',
    'shein:fetch orders --days=30 --target=l30',
    'shein:fetch orders --days=60 --target=l60',
    'app:fetch-pls-data',
    'app:fetch-pls-sales-data --days=90',
    'newegg:orders --days=60 --save',
    'walmart:fetch-orders --days=60',
    'sync:walmart-metrics-data',
    'tiktok:fetch-orders --days=60 --prune',
    'tiktok:fetch-orders --channel=tiktok2 --days=60 --prune',
    'tiktok:collect-metrics',
    'sync:tiktok-api-data',
    'sync:tiktok-api-data --channel=tiktok2',
    'purchasing-power:sync --days=60',
    'app:fetch-macy-products',
    'sof:snapshot-daily --catch-up --backfill=3',
];

foreach ($metricDates as $date) {
    $salesCmds[] = 'app:update-marketplace-daily-metrics --date=' . $date;
}

$salesCmds[] = 'channel:calculate-data --force';
$salesCmds[] = 'channel:calculate-data --force';
$salesCmds[] = 'channel:calculate-data --force';

$total = count($salesCmds);
sb_out($log, 'queue count=' . $total);

foreach ($salesCmds as $i => $cmd) {
    $n = $i + 1;
    echo "[{$n}/{$total}] {$cmd}" . PHP_EOL;
    sb_run($php, $log, $cmd);
}

sb_out($log, 'DONE ' . date('c'));
