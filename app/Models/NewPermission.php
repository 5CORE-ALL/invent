<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewPermission extends Model
{
    use HasFactory;

     protected $fillable = [
        'role',
        'permission_key',
        'can_view',
        'can_create',
        'can_edit'
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean'
    ];
}
