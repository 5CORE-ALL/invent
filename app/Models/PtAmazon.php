<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PtAmazon extends Model
{
    use HasFactory;

    protected $table = 'pt_amazon'; 

    protected $fillable = ['sku', 'ra', 'nra', 'running', 'to_pause', 'paused'];
}

