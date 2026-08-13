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
     * - lines (default): Σ line price only — matches Seller Central "Ordered Product Sales" (tax excluded).
     * - order_greatest: Σ per order max(line sums, total_amount, JSON OrderTotal) — includes tax/shipping.
     * - qty_times_price: legacy Σ (quantity × price).
     */
    public const SALES_TOTAL_MODE_ORDER_GREATEST = 'order_greatest';

    public const SALES_TOTAL_MODE_LINES = 'lines';

    public const SALES_TOTAL_MODE_QTY_TIMES_PRICE = 'qty_times_price';

    /**
     * Previous Amazon→Shopify sync app was stopped on this Pacific date.
     * Orders before this stay local (already in Shopify); only FBM orders on/after this are created.
     */
    public const SHOPIFY_IMPORT_CUTOFF_DATE = '2026-08-06';

    protected $fillable = [
        'amazon_order_id',
        'order_date',
        'status',
        'total_amount',
        'currency',
        'period',
        'raw_data',
        'shopify_order_id',
        'pushed_to_shopify_at',
        'import_status',
        'fulfillment_channel',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'pushed_to_shopify_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(AmazonOrderItem::class, 'amazon_order_id');
    }

    /**
     * Decode amazon_orders.raw_data / item raw_data whether stored as array or (double) JSON string.
     *
     * @return array<string, mixed>
     */
    public static function decodeRawPayload(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function rawPayload(): array
    {
        return self::decodeRawPayload($this->raw_data);
    }

    public function fulfillmentChannel(): string
    {
        $col = strtoupper(trim((string) ($this->fulfillment_channel ?? '')));
        if ($col !== '') {
            return $col;
        }

        $raw = $this->rawPayload();

        return strtoupper(trim((string) ($raw['FulfillmentChannel'] ?? $raw['fulfillmentChannel'] ?? '')));
    }

    public function isFba(): bool
    {
        return $this->fulfillmentChannel() === 'AFN';
    }

    public function isCancelled(): bool
    {
        $status = strtoupper(trim((string) ($this->status ?? '')));

        return in_array($status, ['CANCELED', 'CANCELLED'], true);
    }

    public static function shopifyImportCutoff(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse(self::SHOPIFY_IMPORT_CUTOFF_DATE, 'America/Los_Angeles')->startOfDay();
    }

    public function isOnOrAfterShopifyImportCutoff(): bool
    {
        if (! $this->order_date) {
            return false;
        }

        return \Carbon\Carbon::parse($this->order_date)
            ->timezone('America/Los_Angeles')
            ->gte(self::shopifyImportCutoff());
    }

    /**
     * Eligible to CREATE a new Shopify order (FBM, not cancelled, on/after cutoff, not already linked).
     */
    public function canCreateShopifyOrder(): bool
    {
        if (trim((string) ($this->shopify_order_id ?? '')) !== '') {
            return false;
        }
        if ($this->isFba() || $this->isCancelled()) {
            return false;
        }

        return $this->isOnOrAfterShopifyImportCutoff();
    }

    public static function salesTotalMode(): string
    {
        // Default to `lines` (Σ item price, tax excluded) so the 30-day Total Sales badge
        // matches Amazon Seller Central "Ordered Product Sales" — same formula as the Y Sales
        // badge (productSalesByOrderDate). `order_greatest` inflated totals with tax/shipping.
        $m = strtolower(trim((string) env('AMAZON_SALES_TOTAL_MODE', self::SALES_TOTAL_MODE_LINES)));

        return match ($m) {
            self::SALES_TOTAL_MODE_ORDER_GREATEST, 'greatest', 'order_total' => self::SALES_TOTAL_MODE_ORDER_GREATEST,
            self::SALES_TOTAL_MODE_QTY_TIMES_PRICE, 'legacy', 'original' => self::SALES_TOTAL_MODE_QTY_TIMES_PRICE,
            default => self::SALES_TOTAL_MODE_LINES,
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
     * Product sales (Σ line price, tax excluded) over a UTC order_date window.
     * Closest match to Amazon Seller Central's "Sales" tile (ordered product sales).
     * Pass Pacific-day boundaries already converted to UTC to match Amazon's day grouping.
     */
    public static function productSalesByOrderDate(DateTimeInterface $start, DateTimeInterface $end): float
    {
        return (float) DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $start)
            ->where('o.order_date', '<=', $end)
            ->where(function ($q) {
                $q->whereNull('o.status')
                    ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
            })
            ->sum(DB::raw('COALESCE(i.price, 0)'));
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
