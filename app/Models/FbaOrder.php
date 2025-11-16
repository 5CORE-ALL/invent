<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbaOrder extends Model
{
    use HasFactory;

    protected $fillable = ['amazon_order_id', 'sku', 'order_date', 'dispatch_date', 'quantity', 'status', 'seller_sku'];
}
