<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Ebay2ApiService;

$sku = $argv[1] ?? 'GFF TP BLK';
$bullets = "Comfortable Guitar Stool\nBuilt-In Guitar Holder\nPortable & Foldable\n300 Lbs Capacity\nIdeal for Musicians";

$result = app(Ebay2ApiService::class)->updateBulletPoints($sku, $bullets);
echo json_encode($result, JSON_PRETTY_PRINT)."\n";

$row = Illuminate\Support\Facades\DB::table('ebay_2_metrics')->where('sku', $sku)->first();
if ($row) {
    echo "ebay_2_metrics: item_id={$row->item_id}\n";
}
