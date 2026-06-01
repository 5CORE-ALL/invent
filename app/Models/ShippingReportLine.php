<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingReportLine extends Model
{
    protected $fillable = [
        'report_date',
        'time_slot',
        'shipping_platform_id',
        'is_cleared',
        'order_number',
        'sku',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'report_date' => 'date',
        'is_cleared' => 'boolean',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(ShippingPlatform::class, 'shipping_platform_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ShippingReportIssue::class, 'shipping_report_line_id')->orderBy('id');
    }
}
