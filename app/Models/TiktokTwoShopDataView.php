<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiktokTwoShopDataView extends Model
{
    use HasFactory;

    protected $table = 'tiktok_two_shop_data_views';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
