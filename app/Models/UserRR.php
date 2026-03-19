<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRR extends Model
{
    use HasFactory;

    protected $table = 'user_rr';

    protected $fillable = [
        'user_id',
        'role',
        'responsibilities',
        'goals',
    ];

    /**
     * Get the user that owns the R&R.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get responsibilities as array.
     */
    public function getResponsibilitiesArrayAttribute()
    {
        if (empty($this->responsibilities)) {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->responsibilities)));
    }

    /**
     * Get goals as array.
     */
    public function getGoalsArrayAttribute()
    {
        if (empty($this->goals)) {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->goals)));
    }
}
