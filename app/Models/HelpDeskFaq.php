<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HelpDeskFaq extends Model
{
    use SoftDeletes;

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
        'created_by_email',
        'updated_by_email',
        'edit_history',
    ];

    protected $casts = [
        'dept' => 'array',
        'edit_history' => 'array',
    ];
}
