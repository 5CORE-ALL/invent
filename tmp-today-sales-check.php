<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AmazonOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$estStart = Carbon::now('America/New_York')->startOfDay();
$estEnd = Carbon::now('America/New_York')->endOfDay();
$ptY = Carbon::yesterday('America/Los_Angeles');

echo "now EST: ".Carbon::now('America/New_York')->toDateTimeString()."\n";
echo "amazon today EST: ".AmazonOrder::productSalesByOrderDate($estStart->copy()->utc(), $estEnd->copy()->utc())."\n";
echo "amazon PT yesterday: ".AmazonOrder::productSalesByOrderDate($ptY->copy()->startOfDay()->utc(), $ptY->copy()->endOfDay()->utc())."\n";

if (Schema::hasTable('amazon_orders')) {
    echo "amazon max order_date: ".DB::table('amazon_orders')->max('order_date')."\n";
    echo "amazon count last 3d: ".DB::table('amazon_orders')->where('order_date', '>=', now()->subDays(3))->count()."\n";
}
if (Schema::hasTable('shopify_raw_orders')) {
    echo "shopify max order_date: ".DB::table('shopify_raw_orders')->max('order_date')."\n";
}
if (Schema::hasTable('channel_master_calculated_data')) {
    echo "has today_sales col: ".(Schema::hasColumn('channel_master_calculated_data', 'today_sales') ? 'yes' : 'no')."\n";
    $row = DB::table('channel_master_calculated_data')->where('channel', 'like', '%Amazon%')->first();
    echo "amazon calc y=" . ($row->yesterday_sales ?? 'n/a') . " today=" . ($row->today_sales ?? 'n/a') . "\n";
}
