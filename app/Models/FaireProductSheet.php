<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaireProductSheet extends Model
{
    use HasFactory;

    protected $table = 'faire_products_sheets';

    protected $fillable = [
        'sku', 'price', 'f_l30', 'f_l60', 'views'
    ];
}
