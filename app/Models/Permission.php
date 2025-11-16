<?php

// app/Models/Permission.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['role','permissions','culomn_permission'];

    protected $casts = [
        'permissions' => 'array',
        'culomn_permission' => 'array'
    ];
}