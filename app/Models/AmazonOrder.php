<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AmazonOrder extends Model
{
    use HasFactory;

    /**
     * Total Sales mode for the daily sales page (see badgeTotalSalesByOrderDate).
     * - order_greatest (default): Σ per order max(line sums, total_amount, JSON OrderTotal) — closest to many Seller Central “sales” views.
     * - lines: Σ line price only (ordered-product style).
     * - qty_times_price: legacy Σ (quantity × price).
     */
    public const SALES_TOTAL_MODE_ORDER_GREATEST = 'order_greatest';

    public const SALES_TOTAL_MODE_LINES = 'lines';

    public const SALES_TOTAL_MODE_QTY_TIMES_PRICE = 'qty_times_price';

    protected $fillable = [
        'amazon_order_id',
        'order_date',
        'status',
        'total_amount',
        'currency',
        'period',
        'raw_data',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'raw_data' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(AmazonOrderItem::class, 'amazon_order_id');
    }

    public static function salesTotalMode(): string
    {
        $m = strtolower(trim((string) env('AMAZON_SALES_TOTAL_MODE', self::SALES_TOTAL_MODE_ORDER_GREATEST)));

        return match ($m) {
            self::SALES_TOTAL_MODE_LINES, 'sum_lines', 'ordered_product_sales' => self::SALES_TOTAL_MODE_LINES,
            self::SALES_TOTAL_MODE_QTY_TIMES_PRICE, 'legacy', 'original' => self::SALES_TOTAL_MODE_QTY_TIMES_PRICE,
            default => self::SALES_TOTAL_MODE_ORDER_GREATEST,
        };
    }

    /**
     * SQL expression: use order total when Amazon provided it; otherwise sum line items.
     *
     * @param  string  $alias  Table alias for amazon_orders in the outer query (e.g. "o")
     */
    public static function effectiveOrderTotalSql(string $alias = 'o'): string
    {
        return "CASE WHEN COALESCE({$alias}.total_amount, 0) > 0 THEN {$alias}.total_amount ELSE COALESCE((SELECT SUM(li.price) FROM amazon_order_items li WHERE li.amazon_order_id = {$alias}.id), 0) END";
    }

    /** SQL: quantity × price for an order line (legacy). */
    public static function orderItemQtyTimesPriceSql(string $itemsAlias = 'i'): string
    {
        return '(COALESCE('.$itemsAlias.'.quantity, 0) * COALESCE('.$itemsAlias.'.price, 0))';
    }

    /** Line revenue in grid: Amazon line total = `price` (do not multiply by qty except in legacy mode). */
    public static function lineRevenueSelectSql(string $itemsAlias = 'i'): string
    {
        return self::salesTotalMode() === self::SALES_TOTAL_MODE_QTY_TIMES_PRICE
            ? self::orderItemQtyTimesPriceSql($itemsAlias)
            : "COALESCE({$itemsAlias}.price, 0)";
    }

    /** Sum of line prices only (subquery on order id). */
    public static function orderSumLinePricesSubquery(string $orderAlias = 'o'): string
    {
        return "(SELECT COALESCE(SUM(COALESCE(li.price, 0)), 0) FROM amazon_order_items li WHERE li.amazon_order_id = {$orderAlias}.id)";
    }

    /** OrderTotal from raw JSON (Pascal + camel), guarded. */
    public static function orderTotalAmountFromRawJsonSql(string $orderAlias = 'o'): string
    {
        $j = "{$orderAlias}.raw_data";
        $pascal = "NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$j}, '$.OrderTotal.Amount')), ''), 'null')";
        $camel = "NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$j}, '$.orderTotal.amount')), ''), 'null')";

        return "IF(COALESCE(JSON_VALID({$j}), 0) = 1, GREATEST(
            COALESCE(CAST({$pascal} AS DECIMAL(12,2)), 0),
            COALESCE(CAST({$camel} AS DECIMAL(12,2)), 0)
        ), 0)";
    }

    /** Per-order revenue = max(line sum, column total, JSON order total). */
    public static function orderReportedRevenuePerOrderSql(string $orderAlias = 'o'): string
    {
        $lines = self::orderSumLinePricesSubquery($orderAlias);
        $col = "COALESCE({$orderAlias}.total_amount, 0)";
        $json = self::orderTotalAmountFromRawJsonSql($orderAlias);

        return "GREATEST({$lines}, {$col}, {$json})";
    }

    /** Per-order total shown on grid / filter (matches badge mode). */
    public static function perOrderTotalForBadgeSelectSql(string $orderAlias = 'o'): string
    {
        return match (self::salesTotalMode()) {
            self::SALES_TOTAL_MODE_LINES => self::orderSumLinePricesSubquery($orderAlias),
            self::SALES_TOTAL_MODE_QTY_TIMES_PRICE => '(SELECT COALESCE(SUM('.self::orderItemQtyTimesPriceSql('li').'), 0) FROM amazon_order_items li WHERE li.amazon_order_id = '.$orderAlias.'.id)',
            default => self::orderReportedRevenuePerOrderSql($orderAlias),
        };
    }

    /**
     * Total Sales for the Amazon daily sales badge: controlled by AMAZON_SALES_TOTAL_MODE.
     */
    public static function badgeTotalSalesByOrderDate(DateTimeInterface $start, DateTimeInterface $end): float
    {
        $nonCancelled = function ($q) {
            $q->whereNull('o.status')
                ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
        };

        return match (self::salesTotalMode()) {
            self::SALES_TOTAL_MODE_LINES => (float) DB::table('amazon_orders as o')
                ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
                ->where('o.order_date', '>=', $start)
                ->where('o.order_date', '<=', $end)
                ->where($nonCancelled)
                ->sum(DB::raw('COALESCE(i.price, 0)')),
            self::SALES_TOTAL_MODE_QTY_TIMES_PRICE => self::revenueSumQtyTimesPriceByOrderDate($start, $end),
            default => (float) (DB::table('amazon_orders as o')
                ->where('o.order_date', '>=', $start)
                ->where('o.order_date', '<=', $end)
                ->where($nonCancelled)
                ->selectRaw('SUM('.self::orderReportedRevenuePerOrderSql('o').') as revenue')
                ->value('revenue') ?? 0),
        };
    }

    /**
     * Legacy: Σ (quantity × price) on joined lines — kept for other callers; use badgeTotalSalesByOrderDate on the sales page.
     */
    public static function revenueSumQtyTimesPriceByOrderDate(DateTimeInterface $start, DateTimeInterface $end): float
    {
        $expr = self::orderItemQtyTimesPriceSql('i');

        return (float) DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $start)
            ->where('o.order_date', '<=', $end)
            ->where(function ($q) {
                $q->whereNull('o.status')
                    ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
            })
            ->sum(DB::raw($expr));
    }

    /** Per-order sum of (quantity × price) for SELECT (legacy grid). */
    public static function orderSumQtyTimesPriceSubquery(string $orderAlias = 'o'): string
    {
        $inner = self::orderItemQtyTimesPriceSql('li');

        return "(SELECT COALESCE(SUM({$inner}), 0) FROM amazon_order_items li WHERE li.amazon_order_id = {$orderAlias}.id)";
    }
}
