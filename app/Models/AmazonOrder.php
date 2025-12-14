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
}
