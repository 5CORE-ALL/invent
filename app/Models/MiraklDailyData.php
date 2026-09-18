<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiraklDailyData extends Model
{
    use HasFactory;

    protected $table = 'mirakl_daily_data';

    protected $fillable = [
        'channel_name',
        'channel_id',
        'order_id',
        'channel_order_id',
        'order_line_id',
        'status',
        'order_created_at',
        'order_updated_at',
        'period',
        'sku',
        'product_title',
        'quantity',
        'unit_price',
        'currency',
        'tax_amount',
        'shipping_price',
        'shipping_tax',
        'billing_first_name',
        'billing_last_name',
        'billing_street',
        'billing_city',
        'billing_state',
        'billing_zip',
        'billing_country',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_street',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'shipping_carrier',
        'shipping_method',
        'shopify_order_id',
        'pushed_to_shopify_at',
        'import_status',
        'raw_payload',
    ];

    protected $casts = [
        'order_created_at' => 'datetime',
        'order_updated_at' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_price' => 'decimal:2',
        'shipping_tax' => 'decimal:2',
        'raw_payload' => 'array',
    ];

    /**
     * Scope for Macy's orders
     */
    public function scopeMacys($query)
    {
        return $query->where('channel_name', "Macy's, Inc.");
    }

    /**
     * Scope for Best Buy USA orders
     */
    public function scopeBestBuyUsa($query)
    {
        return $query->where('channel_name', 'Best Buy USA');
    }

    /**
     * Scope for Purchasing Power orders.
     *
     * NOTE: As of writing, Mirakl's order feed (`/api/v2/orders`) does NOT return
     * any Purchasing Power orders for this operator — the live PP sales source is
     * the `purchasing_power_sales` table (CSV upload via PurchasingPowerController).
     * This scope is here so that the day PP orders start appearing in the Mirakl
     * order feed, the standard Mirakl pipeline (and any consumer like a future
     * Sales\PurchasingPowerSalesController) can adopt it without code churn.
     */
    public function scopePurchasingPower($query)
    {
        return $query->where('channel_name', 'Purchasing Power');
    }

    /**
     * Scope for L30 period
     */
    public function scopeL30($query)
    {
        return $query->where('period', 'l30');
    }

    /**
     * Scope for L60 period
     */
    public function scopeL60($query)
    {
        return $query->where('period', 'l60');
    }

    /**
     * Canceled / refunded Mirakl lines — excluded from sales totals.
     */
    public function scopeNotClosed($query)
    {
        return $query->where('status', '!=', 'CLOSED');
    }

    /**
     * Raw stored order_created_at is Pacific wall-clock (app TZ).
     * Bucket by DATE() so a 17th order counts on the 17th — no UTC re-window.
     */
    public static function sumRevenueOnDate(string $channelName, string $ymd): float
    {
        return round((float) static::query()
            ->where('channel_name', $channelName)
            ->whereDate('order_created_at', $ymd)
            ->notClosed()
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue'), 2);
    }

    public static function sumRevenueOnDateRange(string $channelName, string $startYmd, string $endYmd): float
    {
        return round((float) static::query()
            ->where('channel_name', $channelName)
            ->whereDate('order_created_at', '>=', $startYmd)
            ->whereDate('order_created_at', '<=', $endYmd)
            ->notClosed()
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue'), 2);
    }

    /**
     * @return array<string, float> Y-m-d => revenue
     */
    public static function revenueByCalendarDate(string $channelName, string $startYmd, string $endYmd): array
    {
        $rows = static::query()
            ->where('channel_name', $channelName)
            ->whereDate('order_created_at', '>=', $startYmd)
            ->whereDate('order_created_at', '<=', $endYmd)
            ->notClosed()
            ->selectRaw('DATE(order_created_at) as d, COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->groupByRaw('DATE(order_created_at)')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $d = (string) ($row->d ?? '');
            if ($d !== '') {
                $out[$d] = (float) $row->revenue;
            }
        }

        return $out;
    }
}
