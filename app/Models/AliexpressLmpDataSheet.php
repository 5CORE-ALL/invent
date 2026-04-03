<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AliexpressLmpDataSheet extends Model
{
    use HasFactory;

    protected $table = 'aliexpress_lmp_data_sheet';

    protected $fillable = [
        'sku',
        'lmp',
        'lmp_link',
        'lmp_2',
        'lmp_link_2',
        'lmp_entries',
    ];

    protected $casts = [
        'lmp' => 'decimal:2',
        'lmp_2' => 'decimal:2',
        'lmp_entries' => 'array',
    ];
}
