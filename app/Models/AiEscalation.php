<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEscalation extends Model
{
    protected $connection = 'ai';

    protected $table = 'ai_escalations';

    protected $fillable = [
        'question_id',
        'senior_answer',
        'trained',
    ];

    protected $casts = [
        'trained' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(AiQuestion::class, 'question_id');
    }
}
