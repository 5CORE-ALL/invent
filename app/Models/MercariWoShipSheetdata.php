<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MercariWoShipSheetdata extends Model
{
    use HasFactory;

    protected $table = 'mercari_wo_ship_sheet_data';

    protected $fillable = [
        'sku', 'price', 'l30', 'l60', 'views'
    ];
}
