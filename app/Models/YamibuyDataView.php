<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YamibuyDataView extends Model
{
    use HasFactory;

    protected $table = 'yamibuy_data_views';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
