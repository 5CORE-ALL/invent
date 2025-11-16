<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MktplaceOrderItem extends Model
{
    use HasFactory;

    protected $connection = "shiphub";

    protected $table = "order_items";
}
