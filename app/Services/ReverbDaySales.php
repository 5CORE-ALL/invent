<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One calendar day's Reverb GMV.
 *
 * reverb:daily only runs inside the IST business window, which is overnight
 * Pacific, so today's orders land in reverb_order_metrics first and are
 * missing from reverb_daily_data until that sync. Y Sales and Today Sales
 * must count both, without double-counting an order_number.
 */
class ReverbDaySales
{
    /**
     * @return array{sales: float, qty: int, orders: int}
     */
    public function totalsForDate(string $ymd): array
    {
        return $this->totalsBetween($ymd, $ymd);
    }

    public function sumForDate(string $ymd): float
    {
        return $this->totalsForDate($ymd)['sales'];
    }

    /**
     * @return array{sales: float, qty: int, orders: int}
     */
    public function totalsBetween(string $startYmd, string $endYmd): array
    {
        if ($startYmd === '' || $endYmd === '') {
            return ['sales' => 0.0, 'qty' => 0, 'orders' => 0];
        }

        $sales = 0.0;
        $qty = 0;
        $counted = [];

        if (Schema::hasTable('reverb_daily_data')) {
            $rows = DB::table('reverb_daily_data')
                ->whereDate('order_date', '>=', $startYmd)
                ->whereDate('order_date', '<=', $endYmd)
                ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%cancel%'])
                ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%refund%'])
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('sku')->where('sku', '!=', '');
                    })->orWhere(function ($q2) {
                        $q2->whereNotNull('display_sku')->where('display_sku', '!=', '');
                    });
                })
                ->whereNotNull('order_number')->where('order_number', '!=', '')
                ->get(['order_number', 'amount', 'product_subtotal', 'quantity']);

            foreach ($rows as $row) {
                $num = (string) $row->order_number;
                $counted[$num] = true;
                $amount = (float) ($row->amount ?? 0);
                $sub = (float) ($row->product_subtotal ?? 0);
                $sales += $amount > 0 ? $amount : $sub;
                $qty += (int) ($row->quantity ?? 0);
            }
        }

        if (Schema::hasTable('reverb_order_metrics')) {
            $columns = ['order_number', 'amount', 'quantity'];
            if (Schema::hasColumn('reverb_order_metrics', 'raw_payload')) {
                $columns[] = 'raw_payload';
            }

            $rows = DB::table('reverb_order_metrics')
                ->whereDate('order_date', '>=', $startYmd)
                ->whereDate('order_date', '<=', $endYmd)
                ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%cancel%'])
                ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%refund%'])
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('sku')->where('sku', '!=', '');
                    })->orWhere(function ($q2) {
                        $q2->whereNotNull('display_sku')->where('display_sku', '!=', '');
                    });
                })
                ->whereNotNull('order_number')->where('order_number', '!=', '')
                ->get($columns);

            $fromMetrics = [];
            foreach ($rows as $row) {
                $num = (string) $row->order_number;
                if (isset($counted[$num])) {
                    continue;
                }
                if (! isset($fromMetrics[$num])) {
                    $fromMetrics[$num] = [
                        'sales' => self::orderTotalFromPayload(
                            $row->raw_payload ?? null,
                            (float) ($row->amount ?? 0),
                            (int) ($row->quantity ?? 1)
                        ),
                        'qty' => 0,
                    ];
                }
                $fromMetrics[$num]['qty'] += max(1, (int) ($row->quantity ?? 1));
            }

            foreach ($fromMetrics as $num => $metric) {
                $counted[$num] = true;
                $sales += $metric['sales'];
                $qty += $metric['qty'];
            }
        }

        return [
            'sales' => round($sales, 2),
            'qty' => $qty,
            'orders' => count($counted),
        ];
    }

    /**
     * reverb_order_metrics.amount is the unit price. The order total Reverb
     * shows (product + shipping + tax) is total.amount on the stored payload.
     */
    public static function orderTotalFromPayload(mixed $raw, float $amount, int $quantity): float
    {
        $payload = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : null);
        $order = is_array($payload) ? ($payload['order'] ?? $payload) : [];

        $total = is_array($order) ? ($order['total']['amount'] ?? null) : null;
        if (is_numeric($total) && (float) $total > 0) {
            return (float) $total;
        }

        $sub = is_array($order) ? ($order['amount_product_subtotal']['amount'] ?? null) : null;
        if (is_numeric($sub) && (float) $sub > 0) {
            return (float) $sub;
        }

        $qty = max(1, $quantity);
        if ($amount > 0) {
            return round($amount * $qty, 2);
        }

        return 0.0;
    }
}
