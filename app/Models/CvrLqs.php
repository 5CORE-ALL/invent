<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvrLqs extends Model
{
    use HasFactory;

    protected $table = 'cvr_lqs';

    protected $fillable = [
        'sku',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
