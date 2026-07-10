<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$trade = app(App\Services\AliExpressApiService::class)->getOrderTradeDetail($orderId);
$data = is_array($trade['data'] ?? null) ? $trade['data'] : [];

$flat = [];
$walk = function ($node, $prefix = '') use (&$walk, &$flat) {
    if (! is_array($node)) {
        $flat[$prefix] = $node;
        return;
    }
    foreach ($node as $k => $v) {
        $path = $prefix === '' ? (string) $k : $prefix.'.'.$k;
        if (is_array($v) && count($v) > 25) {
            $flat[$path] = '[array:'.count($v).']';
            continue;
        }
        $walk($v, $path);
    }
};
$walk($data);

ksort($flat);
foreach ($flat as $k => $v) {
    if (preg_match('/fee|tax|commission|loan|paid|offer|service|escrow|promotion|seller|actual|include/i', $k)) {
        echo $k.' = '.(is_scalar($v) ? $v : json_encode($v))."\n";
    }
}

echo "\n--- child0 all keys ---\n";
$child = $data['child_order_list']['aeop_tp_child_order_dto'][0] ?? $data['child_order_list'][0] ?? [];
if (is_array($child)) {
    ksort($child);
    foreach ($child as $k => $v) {
        if (! is_array($v)) {
            echo $k.' = '.$v."\n";
        } else {
            echo $k.' = '.json_encode($v)."\n";
        }
    }
}
