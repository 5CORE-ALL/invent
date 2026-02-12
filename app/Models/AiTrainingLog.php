<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTrainingLog extends Model
{
    protected $table = 'ai_training_logs';

    protected $fillable = [
        'question',
        'answer',
        'answered_by',
        'escalation_id',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function answeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function escalation(): BelongsTo
    {
        return $this->belongsTo(AiEscalation::class, 'escalation_id');
    }
}
