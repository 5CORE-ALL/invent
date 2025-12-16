<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebay2Order extends Model
{
    use HasFactory;

    protected $table = 'ebay2_orders';

    protected $fillable = [
        'ebay_order_id',
        'order_date',
        'status',
        'total_amount',
        'currency',
        'period',
        'raw_data',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'raw_data' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(Ebay2OrderItem::class, 'ebay2_order_id');
    }
}
