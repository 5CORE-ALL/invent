<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One Daily Activity Report submission made from the Missing Listing page.
 * The submitter is captured via user_id; submitted_at is what the History
 * panel sorts and displays.
 */
class MissingListingDar extends Model
{
    use HasFactory;

    protected $table = 'missing_listing_dars';

    protected $fillable = [
        'user_id',
        'report',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
