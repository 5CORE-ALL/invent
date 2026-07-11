<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$queue = MarketplaceManagerRegistry::QUEUE;
$out = [
    'queue' => $queue,
    'jobs_pending' => null,
    'jobs_by_legacy_queue' => [],
    'failed_recent' => [],
];

if (Schema::hasTable('jobs')) {
    $out['jobs_pending'] = (int) DB::table('jobs')->where('queue', $queue)->count();
    foreach (['aliexpress', 'alibaba', 'reverb'] as $legacy) {
        $count = (int) DB::table('jobs')->where('queue', $legacy)->count();
        if ($count > 0) {
            $out['jobs_by_legacy_queue'][$legacy] = $count;
        }
    }
}

if (Schema::hasTable('failed_jobs')) {
    $out['failed_recent'] = DB::table('failed_jobs')
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
