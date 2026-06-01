<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewIssuesSummary extends Model
{
    protected $table = 'review_issues_summary';

    public $timestamps = false;

    protected $fillable = [
        'sku', 'supplier_id', 'total_reviews', 'negative_reviews',
        'positive_reviews', 'neutral_reviews', 'negative_rate',
        'issue_quality', 'issue_packaging', 'issue_shipping', 'issue_service',
        'issue_wrong_item', 'issue_missing_parts', 'issue_other',
        'avg_rating', 'updated_at',
    ];

    protected $casts = [
        'negative_rate' => 'float',
        'avg_rating'    => 'float',
        'updated_at'    => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getTopIssueAttribute(): ?string
    {
        $issues = [
            'quality'       => $this->issue_quality,
            'packaging'     => $this->issue_packaging,
            'shipping'      => $this->issue_shipping,
            'service'       => $this->issue_service,
            'wrong_item'    => $this->issue_wrong_item,
            'missing_parts' => $this->issue_missing_parts,
            'other'         => $this->issue_other,
        ];

        arsort($issues);
        $top = array_key_first($issues);
        return $issues[$top] > 0 ? $top : null;
    }
}
