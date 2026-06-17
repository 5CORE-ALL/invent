<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NeweggItem extends Model
{
    use HasFactory;

    protected $table = 'newegg_items';

    protected $fillable = [
        'seller_part_number',
        'newegg_item_number',
        'title',
        'manufacturer_part_number',
        'upc',
        'status',
        'platform',
        'item_weight',
        'date_created',
        'raw_json',
    ];

    protected $casts = [
        'item_weight' => 'decimal:2',
        'raw_json'    => 'array',
    ];
}
