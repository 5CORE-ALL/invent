<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$line = App\Models\AliexpressOrderMetric::query()->where('order_id', $orderId)->first();
$order = is_array($line->raw_payload['order'] ?? null) ? $line->raw_payload['order'] : [];

$flat = [];
$walk = function ($data, $prefix = '') use (&$walk, &$flat) {
    if (! is_array($data)) {
        $flat[$prefix] = $data;
        return;
    }
    foreach ($data as $k => $v) {
        $path = $prefix === '' ? (string) $k : $prefix.'.'.$k;
        if (is_array($v) && ($k === 'child_order_list' || str_contains($path, 'child_order_list'))) {
            continue;
        }
        if (is_array($v) && count($v) > 12) {
            $flat[$path] = '[array:'.count($v).']';
            continue;
        }
        $walk($v, $path);
    }
};
$walk($order);

ksort($flat);
foreach ($flat as $k => $v) {
    if (is_string($v) && preg_match('/22\.|20\.|25\.|1\.38|0\.57|0\.30|pay|fee|fund|amount|discount|escrow|loan|settle/i', (string) $k.'='.$v)) {
        echo $k.' = '.(is_scalar($v) ? $v : json_encode($v))."\n";
    }
}
