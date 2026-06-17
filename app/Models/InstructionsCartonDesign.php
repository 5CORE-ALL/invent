<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructionsCartonDesign extends Model
{
    protected $table = 'instructions_carton_design';

    protected $fillable = [
        'product_master_id',
        'instructions',
    ];

    public function productMaster(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class, 'product_master_id');
    }
}
