<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One badge in the curated KPI badge pool.  Shown as a coloured chip
 * (icon + name) inside the Task Summary KPI modal.
 */
class Badge extends Model
{
    use HasFactory;

    protected $table = 'badges';

    protected $fillable = [
        'name',
        'icon',
        'color',
        'description',
        'created_by',
    ];

    public function awards(): HasMany
    {
        return $this->hasMany(UserBadge::class, 'badge_id');
    }
}
