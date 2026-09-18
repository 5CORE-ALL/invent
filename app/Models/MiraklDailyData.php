<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * Mirakl created_at is UTC. toDateTimeString() stored that UTC clock naive.
     * Seller pages show US time — a 10:50 PM ET 17th is 02:50 UTC on the 18th.
     * Bucket and display in the given US zone so those orders stay on the 17th.
     *
     * @return array{0: string, 1: string} [utcStart, utcEnd]
     */
    public static function utcBoundsForTimezoneDate(string $ymd, string $tz = 'America/Los_Angeles'): array
    {
        $day = Carbon::parse($ymd, $tz);

        return [
            $day->copy()->startOfDay()->utc()->toDateTimeString(),
            $day->copy()->endOfDay()->utc()->toDateTimeString(),
        ];
    }

    public static function utcBoundsForPacificDate(string $ymd): array
    {
        return self::utcBoundsForTimezoneDate($ymd, 'America/Los_Angeles');
    }

    public static function pacificDateTime(?string $rawUtc): ?string
    {
        if ($rawUtc === null || $rawUtc === '') {
            return null;
        }

        return Carbon::parse($rawUtc, 'UTC')->timezone('America/Los_Angeles')->toDateTimeString();
    }

    public static function pacificYmd(?string $rawUtc): ?string
    {
        if ($rawUtc === null || $rawUtc === '') {
            return null;
        }

        return Carbon::parse($rawUtc, 'UTC')->timezone('America/Los_Angeles')->toDateString();
    }

    public static function sumRevenueOnDate(string $channelName, string $ymd): float
    {
        [$start, $end] = self::utcBoundsForPacificDate($ymd);

        return round((float) DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('order_created_at', '>=', $start)
            ->where('order_created_at', '<=', $end)
            ->where('status', '!=', 'CLOSED')
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue'), 2);
    }

    public static function sumRevenueOnDateRange(string $channelName, string $startYmd, string $endYmd): float
    {
        [$start] = self::utcBoundsForPacificDate($startYmd);
        [, $end] = self::utcBoundsForPacificDate($endYmd);

        return round((float) DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('order_created_at', '>=', $start)
            ->where('order_created_at', '<=', $end)
            ->where('status', '!=', 'CLOSED')
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue'), 2);
    }

    /**
     * @return array<string, float> Pacific Y-m-d => revenue
     */
    public static function revenueByCalendarDate(string $channelName, string $startYmd, string $endYmd): array
    {
        [$start] = self::utcBoundsForPacificDate($startYmd);
        [, $end] = self::utcBoundsForPacificDate($endYmd);

        $rows = DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('order_created_at', '>=', $start)
            ->where('order_created_at', '<=', $end)
            ->where('status', '!=', 'CLOSED')
            ->get(['order_created_at', 'unit_price', 'quantity']);

        $out = [];
        foreach ($rows as $row) {
            $d = self::pacificYmd((string) $row->order_created_at);
            if ($d === null) {
                continue;
            }
            $out[$d] = ($out[$d] ?? 0) + ((float) $row->unit_price * (float) $row->quantity);
        }

        foreach ($out as $d => $amt) {
            $out[$d] = round($amt, 2);
        }

        return $out;
    }
}
