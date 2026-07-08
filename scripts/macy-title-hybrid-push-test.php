<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MacysApiService;
use Illuminate\Support\Facades\DB;

$sku = $argv[1] ?? 'GSTOOL BLK';
$title = trim((string) (DB::table('product_master')->where('sku', $sku)->value('title60') ?? ''));
if ($title === '') {
    exit("No title60 for {$sku}\n");
}

echo "=== Hybrid title push: {$sku} ===\n";
echo "title60: {$title}\n\n";

$svc = app(MacysApiService::class);
$result = $svc->updateTitle($sku, $title);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n";

sleep(5);
passthru('php '.escapeshellarg(__DIR__.'/macy-title-connect-vs-mcm.php').' '.escapeshellarg($sku));
