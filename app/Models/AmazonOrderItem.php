<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'amazon_order_id',
        'asin',
        'sku',
        'quantity',
        'price',
        'currency',
        'title',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(AmazonOrder::class, 'amazon_order_id');
    }
}
