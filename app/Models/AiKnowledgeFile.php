<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiKnowledgeFile extends Model
{
    protected $table = 'ai_knowledge_files';

    protected $fillable = [
        'filename',
        'original_name',
        'file_path',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
