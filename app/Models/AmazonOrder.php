<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
