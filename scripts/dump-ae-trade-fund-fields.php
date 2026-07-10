<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orderId = $argv[1] ?? '8212289062955038';
$trade = app(App\Services\AliExpressApiService::class)->getOrderTradeDetail($orderId);
$data = is_array($trade['data'] ?? null) ? $trade['data'] : [];

$pick = [];
foreach ([
    'order_amount', 'seller_order_amount', 'new_seller_order_amount', 'new_order_amount',
    'payment_amount', 'pay_amount_by_settlement_cur', 'promotion_fee', 'actual_fee',
    'include_tax_fee', 'escrow_fee', 'loan_status', 'fund_status', 'adjust_fee',
] as $key) {
    $pick[$key] = $data[$key] ?? null;
}

$child = $data['child_order_list']['aeop_tp_child_order_dto'][0]
    ?? $data['child_order_list'][0]
    ?? null;
if (is_array($child)) {
    $pick['child_discount_list'] = $child['child_order_discount_detail_list'] ?? null;
    $pick['child_escrow_fee_rate'] = $child['escrow_fee_rate'] ?? null;
}

echo json_encode($pick, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
