<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingFollowupArchive extends Model
{
    protected $fillable = [
        'original_followup_id',
        'shipping_report_line_id',
        'shipping_report_issue_id',
        'shipping_platform_id',
        'report_date',
        'time_slot',
        'order_number',
        'sku',
        'reason',
        'resolved_at',
        'resolved_by',
        'created_by',
        'created_at_followup',
        'archived_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'resolved_at' => 'datetime',
        'created_at_followup' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(ShippingPlatform::class, 'shipping_platform_id');
    }
}
