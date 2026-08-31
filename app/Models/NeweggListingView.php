<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NeweggListingView extends Model
{
    use HasFactory;

    protected $table = 'newegg_listing_views';

    protected $fillable = [
        'seller_part_number',
        'item_number',
        'title',
        'sbn_inventory',
        'sbs_inventory',
        'sessions',
        'session_pct',
        'page_views',
        'page_view_pct',
        'orders_sold',
        'sales',
        'units_sold',
        'unit_session_pct',
    ];

    protected $casts = [
        'sbn_inventory' => 'integer',
        'sbs_inventory' => 'integer',
        'sessions' => 'integer',
        'session_pct' => 'decimal:2',
        'page_views' => 'integer',
        'page_view_pct' => 'decimal:2',
        'orders_sold' => 'integer',
        'sales' => 'decimal:2',
        'units_sold' => 'integer',
        'unit_session_pct' => 'decimal:2',
    ];
}
