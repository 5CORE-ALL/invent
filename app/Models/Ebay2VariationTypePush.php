<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ebay2VariationTypePush extends Model
{
    protected $table = 'ebay2_variation_type_pushes';

    protected $fillable = [
        'item_id',
        'parent_sku',
        'current_type',
        'imported_at',
        'import_error',
        'new_type',
        'push_status',
        'push_message',
        'pushed_at',
        'pushed_by',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
        'pushed_at' => 'datetime',
    ];
}
