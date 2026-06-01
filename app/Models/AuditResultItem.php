<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditResultItem extends Model
{
    protected $table = 'audit_result_items';

    protected $fillable = [
        'audit_result_id',
        'audit_parameter_id',
        'parameter_code',
        'parameter_label',
        'category',
        'score',
        'max_score',
        'weight',
        'is_critical',
        'is_critical_failed',
        'remarks',
    ];

    protected $casts = [
        'score'              => 'decimal:2',
        'max_score'          => 'integer',
        'weight'             => 'decimal:2',
        'is_critical'        => 'boolean',
        'is_critical_failed' => 'boolean',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(AuditResult::class, 'audit_result_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(AuditParameter::class, 'audit_parameter_id');
    }
}
