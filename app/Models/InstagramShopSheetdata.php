<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstagramShopSheetdata extends Model
{
    use HasFactory;

     protected $table = 'instagram_shop_sheet_data';

    protected $fillable = [
        'sku', 'price', 'i_l30', 'i_l60', 'views'
    ];
}
