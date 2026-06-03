<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpDeskGuru extends Model
{
    protected $table = 'help_desk_gurus';

    protected $fillable = [
        'name',
        'email',
        'created_by_email',
    ];
}
