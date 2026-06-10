<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NeweggItemInventory extends Model
{
    use HasFactory;

    protected $table = 'newegg_item_inventory';

    protected $fillable = [
        'seller_part_number',
        'newegg_item_number',
        'active',
        'fulfillment_option',
        'available_quantity',
        'warehouse_allocation',
        'raw_json',
    ];

    protected $casts = [
        'warehouse_allocation' => 'array',
        'raw_json'             => 'array',
    ];
}
