<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookAdType extends Model
{
    protected $table = 'facebook_ad_types';

    protected $fillable = ['name'];
}
