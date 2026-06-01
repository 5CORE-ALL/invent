<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingReportIssue extends Model
{
    protected $fillable = [
        'shipping_report_line_id',
        'order_number',
        'sku',
        'reason',
        'hidden_from_report',
    ];

    protected $casts = [
        'hidden_from_report' => 'boolean',
    ];

    public function reportLine(): BelongsTo
    {
        return $this->belongsTo(ShippingReportLine::class, 'shipping_report_line_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(ShippingFollowup::class, 'shipping_report_issue_id');
    }
}
