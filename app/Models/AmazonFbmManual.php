<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonFbmManual extends Model
{
    use HasFactory;

    protected $table = 'amazon_fbm_manual';

    protected $fillable = ['sku', 'fbm_manual', 'data'];
}
