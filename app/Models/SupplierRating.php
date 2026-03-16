<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierRating extends Model
{
    protected $table = 'supplier_ratings';

    protected $fillable = [
        'supplier_id',
        'supplier_name',
        'evaluation_date',
        'criteria',     // JSON field
        'final_score',  // Calculated total score
        'rating_level', // eg. Excellent / Good / Average / Poor
    ];

    protected $casts = [
        'criteria' => 'array',
        'skus' => 'array',
    ];

    // Optional: Relationship to Supplier
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
