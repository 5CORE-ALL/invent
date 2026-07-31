<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmzCvrAuditHistory extends Model
{
    protected $table = 'amz_cvr_audit_histories';

    protected $fillable = [
        'sku',
        'user_id',
        'user_name',
        'task_count',
        'cvr_l30',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'task_count' => 'integer',
        'cvr_l30' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
