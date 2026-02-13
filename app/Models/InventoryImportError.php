<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryImportError extends Model
{
    protected $fillable = [
        'batch_id',
        'row_number',
        'sku',
        'error_type',
        'error_message',
        'row_data',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(InventoryImportBatch::class, 'batch_id');
    }
}
