<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleYoutubeAttrOption extends Model
{
    protected $table = 'google_youtube_attr_options';

    protected $fillable = [
        'kind',
        'label',
    ];
}
