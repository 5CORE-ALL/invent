<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiKnowledgeBase extends Model
{
    protected $table = 'ai_knowledge_base';

    protected $fillable = [
        'category',
        'subcategory',
        'question_pattern',
        'answer_steps',
        'video_link',
        'tags',
    ];

    protected $casts = [
        'answer_steps' => 'array',
        'tags' => 'array',
    ];
}
