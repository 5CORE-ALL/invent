<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiKnowledge extends Model
{
    protected $connection = 'ai';

    protected $table = 'ai_knowledge';

    protected $fillable = [
        'title',
        'content',
        'source',
        'meta_url',
        'department',
        'embedding',
    ];

    /**
     * Embedding is stored as vector in PostgreSQL; we pass string for raw queries.
     */
    protected $casts = [
        // embedding handled via raw DB in services
    ];

    public const SOURCE_SOP = 'sop';
    public const SOURCE_WEBSITE = 'website';
    public const SOURCE_ADMIN = 'admin';
}
