<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemuProductSheet extends Model
{
    protected $table = 'temu_sheet_data_total';

    protected $fillable = [
        'sku', 'price', 'pft', 'roi', 'l30', 'dil', 'clicks','l60'
    ];
    use HasFactory;
}
