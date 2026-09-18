<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LqsMarketplaceAuditPrompt extends Model
{
    protected $table = 'lqs_marketplace_audit_prompts';

    protected $fillable = [
        'marketplace',
        'prompt',
    ];
}
