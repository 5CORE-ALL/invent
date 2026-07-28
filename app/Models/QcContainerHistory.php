<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcContainerHistory extends Model
{
    protected $table = 'qc_container_history';

    protected $fillable = [
        'action_type',
        'qc_container_id',
        'from_tab',
        'to_tab',
        'our_sku',
        'details',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qcContainer(): BelongsTo
    {
        return $this->belongsTo(QcContainer::class, 'qc_container_id');
    }
}
