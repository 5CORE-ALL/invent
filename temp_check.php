<?php
echo 'Top 10 SKUs Check:' . PHP_EOL;
$topSkus = ['CAPO BLUE 1Pc', 'GS EL', 'CAPO WH 1pc', 'CAPO AL BLK', 'CAPO RED', 'MC 123478 6PCS', 'SPONGE', 'GS EL BSS 4PCS', 'EXC BLK 15FT', 'CM MOB 2M'];
$allMatch = true;
foreach ($topSkus as $sku) {
    $metric = \App\Models\Ebay2Metric::where('sku', $sku)->first();
    if (!$metric) continue;
    $metricL30 = $metric->ebay_l30 ?? 0;
    $metricL60 = $metric->ebay_l60 ?? 0;
    $orderL30 = \App\Models\Ebay2OrderItem::where('sku', $sku)->whereHas('order', function($q) {
        $q->where('period', 'l30');
    })->sum('quantity');
    $orderL60 = \App\Models\Ebay2OrderItem::where('sku', $sku)->whereHas('order', function($q) {
        $q->where('period', 'l60');
    })->sum('quantity');
    $match = ($metricL30 == $orderL30 && $metricL60 == $orderL60);
    if (!$match) $allMatch = false;
    echo str_pad($sku, 20) . ' L30:' . str_pad($metricL30.'='.$orderL30, 8) . ' L60:' . str_pad($metricL60.'='.$orderL60, 8) . ' ' . ($match ? 'OK' : 'NO') . PHP_EOL;
}
echo PHP_EOL . ($allMatch ? 'ALL MATCH!' : 'Some mismatches') . PHP_EOL;
