<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonSeoAuditHistory extends Model
{
    use HasFactory;

    protected $table = 'amazon_seo_audit_history';

    protected $fillable = [
        'sku',
        'checklist_text',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
