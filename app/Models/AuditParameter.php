<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditParameter extends Model
{
    protected $table = 'audit_parameters';

    protected $fillable = [
        'module',
        'code',
        'label',
        'description',
        'category',
        'max_score',
        'weight',
        'is_critical',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'max_score'   => 'integer',
        'weight'      => 'decimal:2',
        'is_critical' => 'boolean',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForModule($q, string $module)
    {
        return $q->where('module', $module);
    }
}
