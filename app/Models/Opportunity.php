<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $table = 'opportunities';

    protected $fillable = [
        'type',
        'channel_name',
        'regn_link',
        'status',
        'aa_stage',
        'priority',
        'item_sold',
        'link_as_customer',
        'last_year_traffic',
        'current_traffic',
        'us_presence',
        'us_visitor_count',
        'comm_chgs',
        'current_status',
        'final',
        'date',
        'email',
        'remarks',
        'sign_up_page_link',
        'followup_dt',
        'masum_comment',
    ];
}
