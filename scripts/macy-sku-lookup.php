<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = DB::table('macy_metrics')->where('sku', 'like', '%GSTOOL%')->limit(5)->get();
foreach ($rows as $r) {
    echo json_encode($r)."\n";
}

$rows2 = DB::table('macys_price_data')->where('sku', 'GSTOOL BLK')->orWhere('offer_sku', 'GSTOOL BLK')->get();
echo "\nmacys_price_data GSTOOL BLK:\n";
foreach ($rows2 as $r) {
    echo json_encode($r)."\n";
}
