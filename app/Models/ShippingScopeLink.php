<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingScopeLink extends Model
{
    protected $table = 'shipping_scope_links';

    protected $fillable = [
        'definition_scope',
        'm_link',
        'h_link',
    ];
}
