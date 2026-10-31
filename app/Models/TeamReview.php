<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single 0-5 star rating given by one team member to another, for one
 * factor inside one review section (personal / team / rr).
 */
class TeamReview extends Model
{
    protected $table = 'team_reviews';

    protected $fillable = [
        'reviewee_id',
        'reviewer_id',
        'section',
        'factor',
        'rating',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];
}
