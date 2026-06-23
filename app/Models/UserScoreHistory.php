<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lifetime snapshot of a user's CL score (CL R&R / CL Mgr / CL Gen).
 *
 * Written from the three toggle endpoints in {@see \App\Http\Controllers\TaskController}
 * so the score-history line graph reflects every change the user has made.
 */
class UserScoreHistory extends Model
{
    use HasFactory;

    protected $table = 'user_score_history';

    public const TYPE_CLRR = 'clrr';
    public const TYPE_CLMGR = 'clmgr';
    public const TYPE_CLGEN = 'clgen';

    public const TYPES = [self::TYPE_CLRR, self::TYPE_CLMGR, self::TYPE_CLGEN];

    protected $fillable = [
        'user_id',
        'score_type',
        'percent',
        'captured_at',
    ];

    protected $casts = [
        'percent' => 'integer',
        'captured_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
