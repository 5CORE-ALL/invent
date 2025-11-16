<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewMarketplace extends Model
{
    use HasFactory;

    protected $table = 'new_marketplaces';

    protected $fillable = [
        'channel_name',
        'link_customer',
        'type',
        'priority',
        'category_allowed',
        'link_seller',
        'last_year_traffic',
        'current_traffic',
        'us_presence',
        'us_visitors',
        'commission',
        'applied_through',
        'status',
        'applied_id',
        'password',
        'remarks',
        'apply_date',
    ];

}
