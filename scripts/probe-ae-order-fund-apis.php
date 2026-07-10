<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$api = app(App\Services\AliExpressApiService::class);
$formatter = app(App\Services\MarketplaceManager\AliexpressDetailFormatter::class);

$trade = $api->getOrderTradeDetail($orderId);
$loan = $api->getOrderLoanFundList($orderId);
$info = $api->getOrderInfo($orderId);

$order = is_array($info['data'] ?? null) ? $info['data'] : [];
$order['fund_sources'] = [
    'trade_detail' => $trade['data'] ?? null,
    'loan_fund' => $loan['data'] ?? null,
];

$line = App\Models\AliexpressOrderMetric::query()->where('order_id', $orderId)->first();
$lines = App\Models\AliexpressOrderMetric::query()->where('order_id', $orderId)->get();
$funds = $formatter->formatOrder($order, $lines, $line)['funds'] ?? null;

echo json_encode([
    'order_id' => $orderId,
    'trade_success' => $trade['success'] ?? false,
    'trade_error' => $trade['message'] ?? null,
    'loan_success' => $loan['success'] ?? false,
    'loan_error' => $loan['message'] ?? null,
    'trade_keys' => array_keys(is_array($trade['data'] ?? null) ? $trade['data'] : []),
    'loan_son_count' => count(is_array($loan['data']['son_orders'] ?? null) ? $loan['data']['son_orders'] : []),
    'loan_son_sample' => (is_array($loan['data']['son_orders'] ?? null) ? ($loan['data']['son_orders'][0] ?? null) : null),
    'funds' => $funds,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
