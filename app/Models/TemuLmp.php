<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuLmp extends Model
{
    use HasFactory;

    protected $table = 'temu_lmp';

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
