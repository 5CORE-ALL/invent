<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row mapping one manager → one junior.
 *
 * Managers pick their juniors from the CL Mgr modal; the same junior may
 * be linked to multiple managers (matrix teams), so this is a many-to-many
 * pivot rather than a manager_id column on users.
 */
class ManagerJunior extends Model
{
    use HasFactory;

    protected $table = 'manager_juniors';

    protected $fillable = [
        'manager_user_id',
        'junior_user_id',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function junior(): BelongsTo
    {
        return $this->belongsTo(User::class, 'junior_user_id');
    }
}
