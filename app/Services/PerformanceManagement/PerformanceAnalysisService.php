<?php

namespace App\Services\PerformanceManagement;

use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PerformanceAnalysisService
{
    /**
     * Generate AI feedback based on performance data
     */
    public function generateFeedback(PerformanceReview $review, User $employee): string
    {
        $feedback = [];
        
        // Get recent reviews for trend analysis
        $recentReviews = PerformanceReview::where('employee_id', $employee->id)
            ->where('id', '!=', $review->id)
            ->orderBy('review_date', 'desc')
            ->limit(3)
            ->get();

        // Trend analysis
        if ($recentReviews->count() > 0) {
            $previousScore = $recentReviews->first()->normalized_score;
            $currentScore = $review->normalized_score;
            $difference = $currentScore - $previousScore;

            if ($difference > 0.5) {
                $feedback[] = "🚀 Excellent improvement! Your performance has increased significantly (+" . number_format($difference, 1) . " points).";
            } elseif ($difference > 0.2) {
                $feedback[] = "📈 Great progress! You're showing steady improvement (+" . number_format($difference, 1) . " points).";
            } elseif ($difference < -0.5) {
                $feedback[] = "⚠️ Performance decline detected. Let's focus on improvement strategies (-" . number_format(abs($difference), 1) . " points).";
            } elseif ($difference < -0.2) {
                $feedback[] = "📉 Slight decline noticed. Review areas that need attention (-" . number_format(abs($difference), 1) . " points).";
            } else {
                $feedback[] = "➡️ Performance is stable. Maintain consistency and aim for incremental improvements.";
            }
        } else {
            $feedback[] = "🎯 This is your first review! Great start with a score of " . number_format($review->normalized_score, 1) . ".";
        }

        // Performance level feedback
        switch ($review->performance_level) {
            case 'Excellent':
                $feedback[] = "🏆 Outstanding performance! You're exceeding expectations. Keep up the excellent work!";
                break;
            case 'Good':
                $feedback[] = "✅ Good performance! You're meeting expectations. Focus on areas for growth to reach excellence.";
                break;
            case 'Average':
                $feedback[] = "📊 Average performance. There's room for improvement. Let's identify key areas to focus on.";
                break;
            case 'Needs Improvement':
                $feedback[] = "💪 Performance needs improvement. Let's create an action plan to enhance your skills and results.";
                break;
        }

        // Category-wise analysis
        $categoryScores = $this->getCategoryScores($review);
        $weakCategories = $categoryScores->where('avg_score', '<', 3)->take(2);
        
        if ($weakCategories->count() > 0) {
            $categoryNames = $weakCategories->pluck('category_name')->implode(', ');
            $feedback[] = "🎯 Focus Areas: Consider improving performance in: {$categoryNames}.";
        }

        // Moving average trend
        $movingAverage = $this->calculateMovingAverage($employee);
        if ($movingAverage && $review->normalized_score > $movingAverage) {
            $feedback[] = "📊 Your current score is above your 3-month average. Excellent momentum!";
        }

        return implode(' ', $feedback);
    }

    /**
     * Get category-wise scores for a review
     */
    public function getCategoryScores(?PerformanceReview $review)
    {
        if (!$review) {
            return collect([]);
        }

        return DB::table('performance_review_items')
            ->join('checklist_items', 'performance_review_items.checklist_item_id', '=', 'checklist_items.id')
            ->join('checklist_categories', 'checklist_items.category_id', '=', 'checklist_categories.id')
            ->where('performance_review_items.review_id', $review->id)
            ->select(
                'checklist_categories.name as category_name',
                DB::raw('AVG(performance_review_items.rating) as avg_score'),
                DB::raw('COUNT(*) as item_count')
            )
            ->groupBy('checklist_categories.id', 'checklist_categories.name')
            ->get();
    }

    /**
     * Calculate moving average for an employee
     */
    public function calculateMovingAverage(User $employee, int $periods = 3): ?float
    {
        $reviews = PerformanceReview::where('employee_id', $employee->id)
            ->where('is_completed', true)
            ->orderBy('review_date', 'desc')
            ->limit($periods)
            ->pluck('normalized_score');

        if ($reviews->count() < 2) {
            return null;
        }

        return $reviews->avg();
    }

    /**
     * Predict next score based on trend
     */
    public function predictNextScore(User $employee): ?float
    {
        $reviews = PerformanceReview::where('employee_id', $employee->id)
            ->where('is_completed', true)
            ->orderBy('review_date', 'desc')
            ->limit(3)
            ->pluck('normalized_score')
            ->reverse()
            ->values();

        if ($reviews->count() < 2) {
            return null;
        }

        // Simple linear regression for prediction
        $n = $reviews->count();
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($reviews as $index => $score) {
            $x = $index + 1;
            $y = $score;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Predict next period
        $nextX = $n + 1;
        $predicted = $slope * $nextX + $intercept;

        // Clamp between 1 and 5
        return max(1, min(5, round($predicted, 2)));
    }

    /**
     * Get performance trend data for charts
     */
    public function getTrendData(User $employee, int $limit = 12)
    {
        return PerformanceReview::where('employee_id', $employee->id)
            ->where('is_completed', true)
            ->orderBy('review_date', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($review) {
                return [
                    'date' => $review->review_date->format('Y-m-d'),
                    'score' => (float) $review->normalized_score,
                    'period' => $review->review_period,
                ];
            });
    }

    /**
     * Get moving average data
     */
    public function getMovingAverageData(User $employee, int $window = 3)
    {
        $reviews = PerformanceReview::where('employee_id', $employee->id)
            ->where('is_completed', true)
            ->orderBy('review_date', 'asc')
            ->get();

        $movingAverages = [];
        for ($i = $window - 1; $i < $reviews->count(); $i++) {
            $windowScores = $reviews->slice($i - $window + 1, $window)->pluck('normalized_score');
            $movingAverages[] = [
                'date' => $reviews[$i]->review_date->format('Y-m-d'),
                'average' => (float) $windowScores->avg(),
            ];
        }

        return collect($movingAverages);
    }
}
