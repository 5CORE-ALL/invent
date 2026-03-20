<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KwWalmart extends Model
{
    use HasFactory;

    protected $table = 'kw_walmart'; 

    protected $fillable = ['sku', 'ra', 'nra', 'running', 'to_pause', 'paused'];
}
