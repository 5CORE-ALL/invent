<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SparePartDetail extends Model
{
    protected $table = 'spare_part_details';

    protected $fillable = [
        'product_master_id',
        'part_name',
        'msl_part',
        'quantity',
        'supplier_id',
    ];

    public function productMaster(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class, 'product_master_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    protected $casts = [
        'quantity' => 'integer',
    ];
}
