<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SheinLmp extends Model
{
    protected $table = 'shein_lmp';

    protected $fillable = [
        'sku',
        'is_not_found',
        'price_1',
        'url_1',
        'ignored_1',
        'price_2',
        'url_2',
        'ignored_2',
        'price_3',
        'url_3',
        'ignored_3',
        'price_4',
        'url_4',
        'ignored_4',
    ];

    protected $casts = [
        'is_not_found' => 'boolean',
        'price_1' => 'decimal:2',
        'price_2' => 'decimal:2',
        'price_3' => 'decimal:2',
        'price_4' => 'decimal:2',
        'ignored_1' => 'boolean',
        'ignored_2' => 'boolean',
        'ignored_3' => 'boolean',
        'ignored_4' => 'boolean',
    ];
}
