<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Extra module access given to one person, on top of their role's permissions.
 */
class UserPermissionGrant extends Model
{
    public const ACTIONS = ['view', 'create', 'edit', 'delete'];

    protected $fillable = ['user_id', 'module', 'actions', 'granted_by'];

    protected $casts = [
        'actions' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
