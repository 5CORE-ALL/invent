<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingAuditMaster extends Model
{
    use HasFactory;

     protected $table = 'listing_audit_masters';

    protected $fillable = [
        'channel',
        'link',
        'is_ra_checked',
        'not_listed',
        'not_live',
        'category_issue',
        'attr_not_filled',
        'a_plus_issue',
        'video_issue',
        'title_issue',
        'images',
        'description',
        'bullet_points',
        'in_variation',
    ];

    protected $casts = [
        'is_ra_checked' => 'boolean',
    ];

}
