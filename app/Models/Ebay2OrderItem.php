<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ebay2OrderItem extends Model
{
    use HasFactory;

    protected $table = 'ebay2_order_items';

    protected $fillable = [
        'ebay2_order_id',
        'item_id',
        'sku',
        'quantity',
        'price',
        'title',
        'raw_data',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'raw_data' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Ebay2Order::class, 'ebay2_order_id');
    }
}
