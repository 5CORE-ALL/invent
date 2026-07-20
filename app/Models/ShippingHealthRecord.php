<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscalatedClaimsLink extends Model
{
    protected $table = 'escalated_claims_links';

    protected $fillable = [
        'channel_id',
        'link',
        'required_parameter',
        'current_parameter',
        'summary_issues',
        'root_cause_found',
        'action_to_fix',
        'updated_by',
    ];

    protected $casts = [
        'required_parameter' => 'float',
        'current_parameter' => 'float',
    ];

    public function channel()
    {
        return $this->belongsTo(ChannelMaster::class, 'channel_id');
    }
}
