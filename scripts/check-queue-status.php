<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$queues = array_values(array_unique(array_merge(
    [MarketplaceManagerRegistry::QUEUE],
    MarketplaceManagerRegistry::queueNames()
)));

$out = [
    'queues' => [],
    'totals' => [
        'pending' => 0,
        'waiting' => 0,
        'running' => 0,
        'delayed' => 0,
    ],
    'failed_recent' => [],
];

$now = now();

if (Schema::hasTable('jobs')) {
    foreach ($queues as $queue) {
        $pending = (int) DB::table('jobs')->where('queue', $queue)->count();
        $running = (int) DB::table('jobs')->where('queue', $queue)->whereNotNull('reserved_at')->count();
        $delayed = (int) DB::table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '>', $now)
            ->count();
        $waiting = (int) DB::table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now)
            ->count();

        $out['queues'][$queue] = compact('pending', 'waiting', 'running', 'delayed');
        $out['totals']['pending'] += $pending;
        $out['totals']['waiting'] += $waiting;
        $out['totals']['running'] += $running;
        $out['totals']['delayed'] += $delayed;
    }
}

if (Schema::hasTable('failed_jobs')) {
    $out['failed_recent'] = DB::table('failed_jobs')
        ->whereIn('queue', $queues)
        ->orderByDesc('id')
        ->limit(5)
        ->get(['id', 'queue', 'failed_at', 'exception'])
        ->map(function ($row) {
            $row = (array) $row;
            $row['exception'] = mb_substr((string) ($row['exception'] ?? ''), 0, 500);

            return $row;
        })
        ->all();
}

echo json_encode($out, JSON_PRETTY_PRINT)."\n";
