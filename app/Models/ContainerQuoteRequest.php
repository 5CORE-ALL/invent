<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContainerQuoteRequest extends Model
{
    protected $table = 'container_quote_requests';

    protected $fillable = [
        'container_number', 'forwarder_id', 'forwarder_name', 'forwarder_email',
        'token', 'sent_by', 'sent_at', 'submitted_at', 'last_error',
        'quote_changed_at', 'quote_changes', 'change_ack_by', 'change_ack_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'submitted_at' => 'datetime',
        'quote_changed_at' => 'datetime',
        'change_ack_at' => 'datetime',
    ];
}