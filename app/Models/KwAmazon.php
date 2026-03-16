<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KwAmazon extends Model
{
    use HasFactory;

    protected $table = 'kw_amazon'; 

    protected $fillable = ['sku', 'ra', 'nra', 'running', 'to_pause', 'paused'];
}
