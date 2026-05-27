<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePageInfoNote extends Model
{
    protected $fillable = [
        'page_key',
        'html_content',
        'updated_by',
    ];
}
