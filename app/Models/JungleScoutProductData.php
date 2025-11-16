<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JungleScoutProductData extends Model
{
    use HasFactory;

    protected $casts = [
        'data' => 'array',
    ];

    protected $table = "junglescout_product_data";

    protected $fillable = ['asin', 'parent', 'sku', 'data'];
}
