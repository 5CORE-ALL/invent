<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingFollowup extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'shipping_report_line_id',
        'shipping_report_issue_id',
        'shipping_platform_id',
        'report_date',
        'time_slot',
        'order_number',
        'sku',
        'reason',
        'status',
        'resolved_at',
        'resolved_by',
        'created_by',
    ];

    protected $casts = [
        'report_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function reportLine(): BelongsTo
    {
        return $this->belongsTo(ShippingReportLine::class, 'shipping_report_line_id');
    }

    public function reportIssue(): BelongsTo
    {
        return $this->belongsTo(ShippingReportIssue::class, 'shipping_report_issue_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(ShippingPlatform::class, 'shipping_platform_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
