<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AmazonOrder extends Model
{
    use HasFactory;

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

    /**
     * SQL expression: use order total when Amazon provided it; otherwise sum line items.
     * SP-API often omits OrderTotal for Pending orders (we persist 0), which undercounts vs Seller Central.
     *
     * @param  string  $alias  Table alias for amazon_orders in the outer query (e.g. "o")
     */
    public static function effectiveOrderTotalSql(string $alias = 'o'): string
    {
        return "CASE WHEN COALESCE({$alias}.total_amount, 0) > 0 THEN {$alias}.total_amount ELSE COALESCE((SELECT SUM(li.price) FROM amazon_order_items li WHERE li.amazon_order_id = {$alias}.id), 0) END";
    }

    /** SQL: quantity × price for an order line (table alias = line items). */
    public static function orderItemQtyTimesPriceSql(string $itemsAlias = 'i'): string
    {
        return '(COALESCE('.$itemsAlias.'.quantity, 0) * COALESCE('.$itemsAlias.'.price, 0))';
    }

    /**
     * Sum of (quantity × price) on `amazon_order_items` for orders in `order_date` range, non-canceled.
     * If `price` is already a line total from Amazon (ItemPrice for the full qty), use SUM(price) instead of this product.
     */
    public static function revenueSumQtyTimesPriceByOrderDate(DateTimeInterface $start, DateTimeInterface $end): float
    {
        $expr = self::orderItemQtyTimesPriceSql('i');

        return (float) DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $start)
            ->where('o.order_date', '<=', $end)
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '!=', 'Canceled');
            })
            ->sum(DB::raw($expr));
    }

    /** Per-order sum of (quantity × price) for SELECT/GROUP BY on `amazon_orders` as `o`. */
    public static function orderSumQtyTimesPriceSubquery(string $orderAlias = 'o'): string
    {
        $inner = self::orderItemQtyTimesPriceSql('li');

        return "(SELECT COALESCE(SUM({$inner}), 0) FROM amazon_order_items li WHERE li.amazon_order_id = {$orderAlias}.id)";
    }
}
