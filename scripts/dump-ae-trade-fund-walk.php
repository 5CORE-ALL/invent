<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$api = app(App\Services\AliExpressApiService::class);

$trade = $api->getOrderTradeDetail($orderId);
$loan = $api->getOrderLoanFundList($orderId);
$info = $api->getOrderInfo($orderId);

$interesting = [];
$walk = function ($data, $prefix = '') use (&$walk, &$interesting) {
    if (! is_array($data)) {
        return;
    }
    foreach ($data as $k => $v) {
        $path = $prefix === '' ? (string) $k : $prefix.'.'.$k;
        if (! is_array($v)) {
            if (preg_match('/fee|tax|commission|loan|paid|amount|offer|service|escrow|promotion|settlement|seller/i', $path)) {
                $interesting[$path] = $v;
            }
            continue;
        }
        if (count($v) > 40) {
            continue;
        }
        $walk($v, $path);
    }
};

foreach ([
    'trade' => $trade['data'] ?? [],
    'loan' => $loan['data'] ?? [],
    'info' => $info['data'] ?? [],
] as $label => $payload) {
    if (is_array($payload)) {
        $walk($payload, $label);
    }
}

ksort($interesting);
foreach ($interesting as $path => $value) {
    if (is_scalar($value) && preg_match('/0\.(30|57|74)|20\.74|1\.38|22\.2|2\.9/i', (string) $value)) {
        echo "MATCH {$path} = {$value}\n";
    }
}

echo "\n--- fee/tax keys ---\n";
foreach ($interesting as $path => $value) {
    if (preg_match('/service|transaction|offer_tax|tax_fee|include_tax|actual_fee|seller_order|new_seller/i', $path)) {
        echo "{$path} = ".(is_scalar($value) ? $value : json_encode($value))."\n";
    }
}
