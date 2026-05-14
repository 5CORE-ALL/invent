<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditAttachment extends Model
{
    protected $table = 'audit_attachments';

    protected $fillable = [
        'audit_result_id',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(AuditResult::class, 'audit_result_id');
    }

    public function getUrlAttribute(): string
    {
        return '/storage/' . ltrim($this->file_path, '/');
    }
}
