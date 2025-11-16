<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkedProductData extends Model
{
    use HasFactory;

    protected $table = 'linked_products_data'; 


    protected $fillable = [
        'group_id', 'sku', 'old_qty', 'new_qty', 'old_dil', 'new_dil'
    ];
}
