<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temu2DataView extends Model
{
    use HasFactory;

    protected $table = 'temu2_data_view';

    protected $fillable = ['sku', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
