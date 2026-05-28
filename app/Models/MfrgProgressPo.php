<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfrgProgressPo extends Model
{
    protected $table = 'mfrg_progress_po';

    protected $fillable = [
        'mfrg_progress_id',
        'sku',
        'po_number',
    ];

    public function mfrgProgress(): BelongsTo
    {
        return $this->belongsTo(MfrgProgress::class, 'mfrg_progress_id');
    }
}
