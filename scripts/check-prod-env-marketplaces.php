<?php

/**
 * Parse production .env snippet and compare to active channel API requirements.
 * Usage: save production block to storage/app/tmp-prod.env then: php scripts/check-prod-env-marketplaces.php
 */

require __DIR__ . '/../vendor/autoload.php';

$snippetPath = __DIR__ . '/../storage/app/tmp-prod-env-check.txt';
if (! is_file($snippetPath)) {
    fwrite(STDERR, "Missing {$snippetPath}\n");
    exit(1);
}

// Load snippet into env without touching real .env
foreach (file($snippetPath, FILE_IGNORE_NEW_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    if (! str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $k = trim($k);
    $v = trim($v, " \t\n\r\0\x0B\"'");
    putenv("{$k}={$v}");
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
}

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Support\MarketplaceApiConfigService;
use Illuminate\Support\Facades\DB;

$config = app(MarketplaceApiConfigService::class);

$active = DB::table('channel_master')
    ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
    ->orderBy('channel')
    ->pluck('channel');

$noApiSlugs = [
    'depop', 'instagramshop',
    'mercariwship', 'mercariwoship', 'fbmarketplace', 'fbshop',
    'shopifyb2b', 'vintedcom', 'vinted',
];

$set = [];
$missing = [];
$noApi = [];

foreach ($active as $channel) {
    $slug = $config->normalizeChannelKey($channel);
    if (in_array($slug, $noApiSlugs, true)) {
        $noApi[] = $channel;
        continue;
    }
    if ($config->isConfigured($channel)) {
        $set[] = $channel;
    } else {
        $missing[] = $channel;
    }
}

echo 'CONFIGURED=' . count($set) . PHP_EOL;
echo 'MISSING=' . count($missing) . PHP_EOL;
echo 'NO_API=' . count($noApi) . PHP_EOL;
foreach ($missing as $c) {
    echo "MISSING — {$c}\n";
}
