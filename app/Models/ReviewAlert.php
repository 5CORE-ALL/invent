<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewAlert extends Model
{
    protected $table = 'review_alerts';

    protected $fillable = [
        'sku', 'supplier_id', 'alert_type', 'message', 'status',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function getAlertTypeLabelAttribute(): string
    {
        return match ($this->alert_type) {
            'high_negative_rate' => 'High Negative Rate',
            'top_issue'          => 'Top Issue Spike',
            'spike_detected'     => 'Review Spike',
            default              => ucfirst(str_replace('_', ' ', $this->alert_type)),
        };
    }
}
