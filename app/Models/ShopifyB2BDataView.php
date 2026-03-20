<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyB2BDataView extends Model
{
    use HasFactory;
    
    protected $table = 'shopifyb2b_data_view';
    
    protected $fillable = ['sku', 'value'];
    
    protected $casts = [
        'value' => 'array',
    ];
}
