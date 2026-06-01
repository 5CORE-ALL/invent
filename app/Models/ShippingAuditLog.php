<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingAuditLog extends Model
{
    protected $table = 'shipping_audit_logs';

    protected $fillable = [
        'channel_id',
        // Point 1 — kept
        'all_messages_cleared',
        // Points 2–6 — new shipping-specific items
        'cancelled_orders_not_shipped',
        'required_weight_dimensions_declared',
        'correct_lowest_label_cost_purchased',
        'combined_shipment_message_sent',
        'split_shipment_message_tracking_updated',
        // Legacy columns kept for historical rows; no longer written by the UI.
        'all_messages_replied_correctly',
        'all_messages_noted_in_all_issues',
        'all_followup_created_cleared_on_time',
        'auditor_remarks',
        'audited_at',
        'user_id',
    ];

    protected $casts = [
        'all_messages_cleared' => 'boolean',
        'cancelled_orders_not_shipped' => 'boolean',
        'required_weight_dimensions_declared' => 'boolean',
        'correct_lowest_label_cost_purchased' => 'boolean',
        'combined_shipment_message_sent' => 'boolean',
        'split_shipment_message_tracking_updated' => 'boolean',
        'all_messages_replied_correctly' => 'boolean',
        'all_messages_noted_in_all_issues' => 'boolean',
        'all_followup_created_cleared_on_time' => 'boolean',
        'audited_at' => 'datetime',
    ];

    public function channel()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
