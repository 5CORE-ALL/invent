<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbaListingStatus extends Model
{
    use HasFactory;

    protected $table = 'fba_listing_status';

    protected $fillable = [
        'sku',
        'status_value',
    ];

    protected $casts = [
        'status_value' => 'array',
    ];

    /**
     * Get the valid status options
     */
    public static function getStatusOptions()
    {
        return ['All', 'FBA', 'FBM', 'NRL'];
    }
}
