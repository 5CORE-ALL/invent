<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceDocRequest extends Model
{
    protected $table = 'compliance_doc_requests';

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
        'sent_at' => 'datetime',
    ];
}
