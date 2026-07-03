<?php

/**
 * Run dry-run audit for all Product Master modules and save results.
 * Usage: php scripts/marketplace-audit-all-masters.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sku = config('marketplace_testing.bullet_point_sku', 'SP 12120 4OHM GTR');
$audit = app(\App\Services\Support\MarketplaceMasterAuditService::class);
$store = app(\App\Services\Support\MarketplaceMasterAuditResultsStore::class);

$payload = $audit->auditAllMasters($sku);
$store->save($payload);

echo "Audit complete for SKU: {$sku}\n";
foreach ($payload['masters'] as $name => $block) {
    echo ucfirst($name).': '.($block['ready_count'] ?? 0).'/'.($block['total_count'] ?? 0)." ready\n";
}
echo 'Not working: '.count($payload['not_working'] ?? [])."\n";
echo 'Live risks: '.count($payload['live_risks'] ?? [])."\n";
echo 'Saved: '.$store->markdownPath()."\n";

exit(count($payload['not_working'] ?? []) === 0 ? 0 : 1);
