<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NeweeggProductSheet extends Model
{
    protected $table = 'neweegg_sheet_data'; // Ensure the model uses the correct table name
     protected $fillable = [
        'sku', 'price', 'pft', 'roi', 'l30', 'dil', 'buy_link'
    ];
    use HasFactory;
}
