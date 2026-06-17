<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditResult extends Model
{
    protected $table = 'audit_results';

    protected $fillable = [
        'module',
        'channel',
        'channel_master_id',
        'executive_name',
        'executive_id',
        'auditor_id',
        'message_reference',
        'audit_date',
        'core_qa_score',
        'channel_compliance_score',
        'bonus_points',
        'total_score',
        'grade',
        'has_critical_failure',
        'critical_failure_reasons',
        'auditor_notes',
        'status',
    ];

    protected $casts = [
        'audit_date'              => 'date',
        'core_qa_score'           => 'decimal:2',
        'channel_compliance_score' => 'decimal:2',
        'bonus_points'            => 'decimal:2',
        'total_score'             => 'decimal:2',
        'has_critical_failure'    => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AuditResultItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AuditAttachment::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
