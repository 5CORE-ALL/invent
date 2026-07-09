<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$sku = 'GSTOOL BLK';
$pm = DB::table('product_master')->where('sku', $sku)->first();
echo "product_master: ".($pm ? 'yes' : 'no')."\n";
if ($pm) {
    foreach (['description_600','description_800','description_1000','description_1500','product_description'] as $c) {
        $v = trim((string) ($pm->$c ?? ''));
        echo "  {$c}: ".mb_strlen($v)." chars\n";
    }
}
$mm = DB::table('macy_metrics')->where('sku', $sku)->first();
echo "macy_metrics description_master: ".mb_strlen(trim((string)($mm->description_master ?? '')))." chars\n";
