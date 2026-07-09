<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$sku = $argv[1] ?? 'SP 12120 4OHM GTR';
$tables = [
    'ebay_metrics', 'ebay_2_metrics', 'ebay_3_metrics', 'amazon_metrics', 'shopify_metrics',
    'shopify_pls_metrics', 'macy_metrics', 'wayfair_metrics', 'reverb_metrics', 'bestbuy_metrics',
    'walmart_metrics', 'temu_metrics', 'temu2_metrics', 'doba_metrics', 'shein_metrics',
    'aliexpress_metric', 'aliexpress_metrics', 'faire_metrics',
];

foreach ($tables as $t) {
    if (! Schema::hasTable($t)) {
        echo "{$t}: MISSING TABLE\n";
        continue;
    }
    $r = DB::table($t)
        ->where('sku', $sku)
        ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
        ->first();
    if (! $r) {
        echo "{$t}: NO ROW\n";
        continue;
    }
    $arr = (array) $r;
    $summary = [
        'sku' => $arr['sku'] ?? null,
        'item_id' => $arr['item_id'] ?? null,
        'product_id' => $arr['product_id'] ?? null,
        'goods_id' => $arr['goods_id'] ?? null,
        'has_bullets' => ! empty($arr['bullet_points'] ?? null),
    ];
    echo "{$t}: ".json_encode($summary)."\n";
}
