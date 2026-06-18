<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerFaq extends Model
{
    use SoftDeletes;

    protected $table = 'customer_faqs';

    protected $fillable = [
        'group_name',
        'faq',
        'answers',
        'customer_type',
        'dept',
        'severity',
        'status',
        'type_variant',
        'what',
        'link',
        'link2',
        'sop',
        'video',
        'action',
        'ca',
        'plus_action',
        'messages',
        'escalation_l1_role',
        'escalation_l1_name',
        'escalation_l1_email',
        'escalation_l1_sla',
        'escalation_l2_role',
        'escalation_l2_name',
        'escalation_l2_email',
        'escalation_l2_sla',
        'escalation_l3_role',
        'escalation_l3_name',
        'escalation_l3_email',
        'escalation_l3_sla',
        'current_escalation_level',
        'escalated_at',
        'escalated_by_email',
        'escalated_to_email',
        'escalation_reason',
        'resolved_at',
        'resolved_by_email',
        'resolution_note',
        'escalation_log',
        'created_by_email',
        'updated_by_email',
        'edit_history',
    ];

    protected $casts = [
        'dept' => 'array',
        'edit_history' => 'array',
        'escalation_log' => 'array',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'current_escalation_level' => 'integer',
    ];

    public const STATUS_OPTIONS = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'escalated' => 'Escalated',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public const SEVERITY_OPTIONS = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    public const CUSTOMER_TYPES = [
        'Retail',
        'Wholesale',
        'B2B',
        'Marketplace',
        'Shopify',
        'Amazon',
        'eBay',
        'Walmart',
        'Faire',
        'Newegg',
        'TikTok',
        'Other',
    ];
}
