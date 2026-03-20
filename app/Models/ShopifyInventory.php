<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyInventory extends Model
{
    use HasFactory;

    protected $fillable = ['sku', 'parent', 'on_hand', 'committed', 'available_to_sell'];

}
