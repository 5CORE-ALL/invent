<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MercariWShipSheetdata extends Model
{
    use HasFactory;

     protected $table = 'mercari_w_ship_sheet_data';

    protected $fillable = [
        'sku', 'price', 'l30', 'l60', 'views'
    ];
}
