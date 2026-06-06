<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScopeOfImprovement extends Model
{
    protected $table = 'scope_of_improvements';

    protected $fillable = [
        'user_id',
        'issue',
        'root_cause',
        'fixing_root_cause',
        'history',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'history' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
