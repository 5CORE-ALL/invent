<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiQuestion extends Model
{
    protected $table = 'ai_questions';

    protected $fillable = [
        'user_id',
        'question',
        'ai_answer',
        'helpful',
    ];

    protected $casts = [
        'helpful' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
