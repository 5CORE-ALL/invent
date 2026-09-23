<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageAuditHistory extends Model
{
    protected $table = 'image_audit_histories';

    public $timestamps = false;

    protected $fillable = [
        'sku',
        'fixed',
        'details',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'fixed' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
