<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use Illuminate\Support\Facades\DB;

$sku = $argv[1] ?? 'GSTOOL BLK';

echo "=== Macy title push test: {$sku} ===\n\n";

$row = DB::table('macy_metrics')->where('sku', $sku)->first();
if (! $row) {
    $row = DB::table('macy_metrics')->where('sku', 'like', '%GSTOOL%')->first();
    if ($row) {
        $sku = (string) $row->sku;
        echo "Resolved SKU: {$sku}\n";
    }
}

$title = trim((string) ($row->title60 ?? ''));
if ($title === '') {
    $pm = DB::table('product_master')->where('sku', $sku)->first();
    $title = trim((string) ($pm->title60 ?? $pm->title_60 ?? ''));
}
if ($title === '') {
    echo "ERROR: No title60 in macy_metrics or product_master for {$sku}\n";
    exit(1);
}

echo 'PM title60 ('.mb_strlen($title).' chars): '.$title."\n\n";

$svc = app(MacysApiService::class);
$ref = new ReflectionClass($svc);
$fetch = $ref->getMethod('fetchMacyMiraklProduct');
$fetch->setAccessible(true);

$before = $fetch->invoke($svc, $sku);
$beforeTitle = '';
foreach ((array) ($before['titles'] ?? []) as $t) {
    if (is_array($t) && trim((string) ($t['value'] ?? '')) !== '') {
        $beforeTitle = trim((string) $t['value']);
        break;
    }
}
echo 'Connect BEFORE: '.($beforeTitle !== '' ? $beforeTitle : '(empty)')."\n";
echo 'Match before push: '.(strcasecmp($beforeTitle, $title) === 0 ? 'YES' : 'NO')."\n\n";

echo "Pushing title via MacysApiService::updateTitle...\n";
$result = $svc->updateTitle($sku, $title);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n";

sleep(3);

$after = $fetch->invoke($svc, $sku);
$afterTitle = '';
foreach ((array) ($after['titles'] ?? []) as $t) {
    if (is_array($t) && trim((string) ($t['value'] ?? '')) !== '') {
        $afterTitle = trim((string) $t['value']);
        break;
    }
}
echo 'Connect AFTER: '.($afterTitle !== '' ? $afterTitle : '(empty)')."\n";
echo 'Verify: '.(strcasecmp($afterTitle, $title) === 0 ? 'MATCH' : 'MISMATCH')."\n";
