<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaOrderMetric;
use App\Services\AlibabaApiService;
use Illuminate\Support\Facades\Log;

class AlibabaOrderDetailService
{
    public function __construct(
        protected AlibabaApiService $aliExpressApi
    ) {}

    /**
     * @return array{success: bool, message?: string, order?: array<string, mixed>}
     */
    public function fetchAndPersistOrderDetail(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Order ID is required.'];
        }

        $info = $this->aliExpressApi->getOrderInfo($orderId);
        if (empty($info['success'])) {
            return [
                'success' => false,
                'message' => $info['message'] ?? 'Could not load Alibaba order details.',
            ];
        }

        $order = is_array($info['data'] ?? null) ? $info['data'] : [];
        $receipt = $this->aliExpressApi->getOrderReceiptInfo($orderId);
        if (! empty($receipt['success']) && is_array($receipt['data'] ?? null)) {
            $order = $this->mergeReceiptAddress($order, $receipt['data']);
        }

        $trade = $this->aliExpressApi->getOrderTradeDetail($orderId);
        $loan = $this->aliExpressApi->getOrderLoanFundList($orderId);
        $order['fund_sources'] = [
            'trade_detail' => ! empty($trade['success']) && is_array($trade['data'] ?? null) ? $trade['data'] : null,
            'loan_fund' => ! empty($loan['success']) && is_array($loan['data'] ?? null) ? $loan['data'] : null,
            'trade_detail_error' => empty($trade['success']) ? ($trade['message'] ?? null) : null,
            'loan_fund_error' => empty($loan['success']) ? ($loan['message'] ?? null) : null,
        ];
        $order['fund_sources_fetched_at'] = now()->toIso8601String();
        $order = $this->mergeFundSnapshotsIntoOrder($order);

        $lines = AlibabaOrderMetric::query()
            ->where('order_id', $orderId)
            ->get();

        if ($lines->isEmpty()) {
            return ['success' => false, 'message' => 'Order not found in local database.'];
        }

        foreach ($lines as $line) {
            $raw = is_array($line->raw_payload) ? $line->raw_payload : [];
            $raw['order'] = $order;
            $raw['order_detail_fetched_at'] = now()->toIso8601String();
            if (is_array($raw['line'] ?? null)) {
                // keep per-line snapshot
            }
            $line->update(['raw_payload' => $raw]);
        }

        Log::info('AlibabaOrderDetailService: persisted order detail', [
            'order_id' => $orderId,
            'lines' => $lines->count(),
        ]);

        return ['success' => true, 'order' => $order];
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $receipt
     * @return array<string, mixed>
     */
    protected function mergeReceiptAddress(array $order, array $receipt): array
    {
        $existing = is_array($order['receipt_address'] ?? null) ? $order['receipt_address'] : [];
        $incoming = is_array($receipt['receipt_address'] ?? null)
            ? $receipt['receipt_address']
            : $receipt;

        foreach ($incoming as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $existing[$key] = $value;
        }

        $order['receipt_address'] = $existing;

        return $order;
    }

    /**
     * Copy settlement fields from trade / loan APIs onto the solution order snapshot.
     *
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    protected function mergeFundSnapshotsIntoOrder(array $order): array
    {
        $sources = is_array($order['fund_sources'] ?? null) ? $order['fund_sources'] : [];
        $trade = is_array($sources['trade_detail'] ?? null) ? $sources['trade_detail'] : [];

        foreach ([
            'pay_amount_by_settlement_cur',
            'settlement_currency',
            'loan_status',
            'escrow_fee',
            'escrow_fee_rate',
            'fund_status',
            'gmt_pay_success',
            'gmt_pay_time',
            'payment_amount',
            'promotion_fee',
            'seller_order_amount',
            'new_seller_order_amount',
        ] as $key) {
            if (! array_key_exists($key, $order) || $order[$key] === null || $order[$key] === '' || $order[$key] === []) {
                if (array_key_exists($key, $trade) && $trade[$key] !== null && $trade[$key] !== '' && $trade[$key] !== []) {
                    $order[$key] = $trade[$key];
                }
            }
        }

        if (! empty($trade['loan_info']) && is_array($trade['loan_info'])) {
            $existingLoan = is_array($order['loan_info'] ?? null) ? $order['loan_info'] : [];
            $loanAmount = $trade['loan_info']['loan_amount'] ?? null;
            if ($loanAmount !== null && $loanAmount !== '' && $loanAmount !== []) {
                $existingLoan['loan_amount'] = $loanAmount;
            }
            if (($trade['loan_info']['loan_time'] ?? null) !== null) {
                $existingLoan['loan_time'] = $trade['loan_info']['loan_time'];
            }
            if ($existingLoan !== []) {
                $order['loan_info'] = $existingLoan;
            }
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrderRoot(AlibabaOrderMetric $line): array
    {
        $raw = is_array($line->raw_payload) ? $line->raw_payload : [];

        return is_array($raw['order'] ?? null) ? $raw['order'] : $raw;
    }
}
