<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkuRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_sku',
        'related_sku',
    ];
}
