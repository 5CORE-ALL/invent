<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'reviewer_id',
        'designation_id',
        'review_period',
        'review_date',
        'period_start_date',
        'period_end_date',
        'total_score',
        'normalized_score',
        'performance_level',
        'overall_feedback',
        'ai_feedback',
        'is_completed',
    ];

    protected $casts = [
        'review_date' => 'date',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'total_score' => 'decimal:2',
        'normalized_score' => 'decimal:2',
        'is_completed' => 'boolean',
    ];

    /**
     * Get the employee being reviewed
     */
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Get the reviewer
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Get the designation
     */
    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get all review items (ratings)
     */
    public function reviewItems()
    {
        return $this->hasMany(PerformanceReviewItem::class, 'review_id');
    }

    /**
     * Calculate and update scores
     */
    public function calculateScores()
    {
        $totalWeightedScore = 0;
        $totalWeight = 0;

        foreach ($this->reviewItems as $item) {
            $itemWeight = $item->checklistItem->weight;
            $weightedScore = $item->rating * $itemWeight;
            
            $item->weighted_score = $weightedScore;
            $item->save();

            $totalWeightedScore += $weightedScore;
            $totalWeight += $itemWeight;
        }

        if ($totalWeight > 0) {
            $this->total_score = $totalWeightedScore / $totalWeight;
            // Normalize to 5 scale (assuming max rating is 5)
            $this->normalized_score = ($this->total_score / 5) * 5;
            
            // Determine performance level
            if ($this->normalized_score >= 4.5) {
                $this->performance_level = 'Excellent';
            } elseif ($this->normalized_score >= 3.5) {
                $this->performance_level = 'Good';
            } elseif ($this->normalized_score >= 2.5) {
                $this->performance_level = 'Average';
            } else {
                $this->performance_level = 'Needs Improvement';
            }
        }

        $this->save();
    }
}
