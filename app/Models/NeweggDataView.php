<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NeweggDataView extends Model
{
    use HasFactory;

    protected $table = 'newegg_data_views';

    protected $fillable = [
        'sku',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
