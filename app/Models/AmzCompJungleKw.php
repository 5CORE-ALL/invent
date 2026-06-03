<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmzCompJungleKw extends Model
{
    protected $table = 'amz_comp_jungle_kws';

    protected $fillable = [
        'sku',
        'search_kw',
        'asins',
    ];

    protected $casts = [
        'asins' => 'array',
    ];
}
