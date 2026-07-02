<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LmpAmzSkuLink extends Model
{
    protected $table = 'lmp_amz_sku_links';

    protected $fillable = [
        'sku',
        'linked_sku',
        'sku_norm',
        'linked_sku_norm',
        'updated_by',
    ];
}
