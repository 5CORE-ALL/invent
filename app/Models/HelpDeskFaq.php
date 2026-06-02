<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpDeskFaq extends Model
{
    protected $table = 'help_desk_faqs';

    protected $fillable = [
        'group_name',
        'faq',
        'dept',
        'type_variant',
        'what',
        'answers',
        'link',
        'link2',
        'sop',
        'video',
        'action',
        'ca',
        'plus_action',
        'messages',
    ];

    protected $casts = [
        'dept' => 'array',
    ];
}
