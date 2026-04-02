<?php

namespace App\Services;

use App\Models\SkuReview;
use App\Models\ReviewIssuesSummary;
use App\Models\ReviewAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReviewAnalysisService
{
    // Keyword maps for rule-based AI classification
    const NEGATIVE_KEYWORDS = [
        'broken', 'damaged', 'defective', 'poor', 'terrible', 'awful', 'worst',
        'horrible', 'bad', 'waste', 'disappoint', 'useless', 'fail', 'wrong',
        'missing', 'not work', "doesn't work", 'cheap', 'flimsy', 'trash',
    ];

    const POSITIVE_KEYWORDS = [
        'great', 'excellent', 'amazing', 'love', 'perfect', 'fantastic', 'awesome',
        'best', 'wonderful', 'good', 'happy', 'satisfied', 'recommend', 'solid',
        'worth', 'quality', 'well made', 'fast', 'quick', 'easy',
    ];

    const ISSUE_KEYWORDS = [
        'quality'       => ['broken', 'defective', 'poor quality', 'cheap', 'flimsy', 'bad build', 'not durable', 'falls apart', 'cracked', 'scratched'],
        'packaging'     => ['damaged box', 'packaging', 'poorly packed', 'crushed', 'dented', 'not sealed', 'open box', 'wrapping'],
        'shipping'      => ['late', 'delay', 'slow ship', 'not arrived', 'lost', 'wrong address', 'courier', 'delivery', 'tracking'],
        'service'       => ['support', 'customer service', 'no response', 'rude', 'unhelpful', 'ignored', 'refund denied', 'not resolved'],
        'wrong_item'    => ['wrong item', 'wrong product', 'not what i ordered', 'different color', 'different size', 'not as described'],
        'missing_parts' => ['missing', 'incomplete', 'no instructions', 'parts missing', 'not included', 'accessories missing'],
    ];

    /**
     * Analyze a single review: detect sentiment, classify issue, generate summary, map department.
     */
    public function analyzeReview(SkuReview $review): void
    {
        $text = strtolower(($review->review_title ?? '') . ' ' . ($review->review_text ?? ''));

        $sentiment = $this->detectSentiment($text, $review->rating);
        $issue     = $this->classifyIssue($text, $sentiment);
        $summary   = $this->generateSummary($review, $sentiment, $issue);
        $dept      = SkuReview::mapDepartment($issue ?? 'other');
        $flagged   = $this->shouldFlag($sentiment, $review->rating);

        $review->update([
            'sentiment'      => $sentiment,
            'issue_category' => $issue,
            'ai_summary'     => $summary,
            'department'     => $dept,
            'is_flagged'     => $flagged,
        ]);
    }

    /**
     * Analyze a batch of unanalyzed reviews.
     */
    public function analyzeBatch(int $limit = 100): int
    {
        $reviews = SkuReview::whereNull('sentiment')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        foreach ($reviews as $review) {
            try {
                $this->analyzeReview($review);
                $processed++;
            } catch (\Exception $e) {
                Log::error("ReviewAnalysisService: Failed to analyze review #{$review->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Regenerate the aggregated summary table for all SKUs.
     */
    public function refreshSummaryTable(): void
    {
        $skus = SkuReview::select('sku')->distinct()->pluck('sku');

        foreach ($skus as $sku) {
            $this->refreshSkuSummary($sku);
        }

        Log::info("ReviewAnalysisService: Summary table refreshed for {$skus->count()} SKUs");
    }

    public function refreshSkuSummary(string $sku): void
    {
        $reviews = SkuReview::where('sku', $sku)->whereNotNull('sentiment')->get();

        if ($reviews->isEmpty()) {
            return;
        }

        $total    = $reviews->count();
        $negative = $reviews->where('sentiment', 'negative')->count();
        $positive = $reviews->where('sentiment', 'positive')->count();
        $neutral  = $reviews->where('sentiment', 'neutral')->count();

        $supplierId = $reviews->whereNotNull('supplier_id')->first()?->supplier_id;

        ReviewIssuesSummary::updateOrCreate(
            ['sku' => $sku],
            [
                'supplier_id'       => $supplierId,
                'total_reviews'     => $total,
                'negative_reviews'  => $negative,
                'positive_reviews'  => $positive,
                'neutral_reviews'   => $neutral,
                'negative_rate'     => $total > 0 ? round(($negative / $total) * 100, 2) : 0,
                'issue_quality'     => $reviews->where('issue_category', 'quality')->count(),
                'issue_packaging'   => $reviews->where('issue_category', 'packaging')->count(),
                'issue_shipping'    => $reviews->where('issue_category', 'shipping')->count(),
                'issue_service'     => $reviews->where('issue_category', 'service')->count(),
                'issue_wrong_item'  => $reviews->where('issue_category', 'wrong_item')->count(),
                'issue_missing_parts' => $reviews->where('issue_category', 'missing_parts')->count(),
                'issue_other'       => $reviews->where('issue_category', 'other')->count(),
                'avg_rating'        => round($reviews->avg('rating'), 2),
                'updated_at'        => now(),
            ]
        );

        $this->generateAlerts($sku, $supplierId);
    }

    /**
     * Generate a reply suggestion for a review.
     */
    public function generateReply(SkuReview $review): string
    {
        $sentiment = $review->sentiment ?? 'neutral';
        $issue     = $review->issue_category ?? 'other';

        $openers = [
            'negative' => "Thank you for your feedback. We sincerely apologize for the experience you had.",
            'neutral'  => "Thank you for taking the time to share your feedback.",
            'positive' => "Thank you so much for your wonderful review! We're thrilled to hear this.",
        ];

        $issueResponses = [
            'quality'       => "We take product quality very seriously and will investigate this with our quality team immediately.",
            'packaging'     => "We are reviewing our packaging process to prevent this from happening in future shipments.",
            'shipping'      => "We have forwarded your concern to our logistics team to resolve the delivery issue.",
            'service'       => "We are working to improve our customer service and will address your concern right away.",
            'wrong_item'    => "We apologize for sending the incorrect item. Please contact us and we will arrange a replacement immediately.",
            'missing_parts' => "We are sorry to hear parts were missing. Please reach out and we will send the missing components right away.",
            'other'         => "We have noted your concern and our team will review it promptly.",
        ];

        $closer = "We value your business and hope to make this right. Please don't hesitate to contact us directly.";

        return implode(' ', [
            $openers[$sentiment] ?? $openers['neutral'],
            $issueResponses[$issue] ?? $issueResponses['other'],
            $closer,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function detectSentiment(string $text, ?int $rating): string
    {
        // Rating takes priority if provided
        if ($rating !== null) {
            if ($rating <= 2) return 'negative';
            if ($rating == 3) return 'neutral';
            if ($rating >= 4) return 'positive';
        }

        $negScore = 0;
        $posScore = 0;

        foreach (self::NEGATIVE_KEYWORDS as $kw) {
            if (str_contains($text, $kw)) $negScore++;
        }

        foreach (self::POSITIVE_KEYWORDS as $kw) {
            if (str_contains($text, $kw)) $posScore++;
        }

        if ($negScore > $posScore) return 'negative';
        if ($posScore > $negScore) return 'positive';
        return 'neutral';
    }

    private function classifyIssue(string $text, string $sentiment): string
    {
        if ($sentiment === 'positive') {
            return 'other';
        }

        $scores = [];
        foreach (self::ISSUE_KEYWORDS as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    $score++;
                }
            }
            $scores[$category] = $score;
        }

        arsort($scores);
        $top = array_key_first($scores);
        return ($scores[$top] > 0) ? $top : 'other';
    }

    private function generateSummary(SkuReview $review, string $sentiment, string $issue): string
    {
        $text = $review->review_text ?? $review->review_title ?? '';

        if (strlen($text) < 20) {
            return ucfirst($sentiment) . ' review — ' . ucfirst(str_replace('_', ' ', $issue)) . ' concern detected.';
        }

        // Truncate to first 120 chars as summary
        $snippet = mb_substr(strip_tags($text), 0, 120);
        $snippet = rtrim($snippet, '.,;: ');

        $prefix = match ($sentiment) {
            'negative' => 'Issue: ',
            'positive' => 'Praise: ',
            default    => 'Note: ',
        };

        return $prefix . $snippet . (strlen($text) > 120 ? '...' : '');
    }

    private function shouldFlag(string $sentiment, ?int $rating): bool
    {
        return $sentiment === 'negative' && ($rating !== null && $rating <= 2);
    }

    private function generateAlerts(string $sku, ?int $supplierId): void
    {
        // Alert 1: Negative rate > 30%
        $summary = ReviewIssuesSummary::where('sku', $sku)->first();
        if ($summary && $summary->negative_rate > 30) {
            $this->createAlert($sku, $supplierId, 'high_negative_rate',
                "SKU {$sku} has {$summary->negative_rate}% negative reviews (above 30% threshold)."
            );
        }

        // Alert 2: Same issue repeated > 10 times
        $issueCounts = SkuReview::where('sku', $sku)
            ->whereNotNull('issue_category')
            ->where('issue_category', '!=', 'other')
            ->select('issue_category', DB::raw('count(*) as cnt'))
            ->groupBy('issue_category')
            ->having('cnt', '>', 10)
            ->get();

        foreach ($issueCounts as $row) {
            $this->createAlert($sku, $supplierId, 'top_issue',
                "SKU {$sku}: '{$row->issue_category}' issue repeated {$row->cnt} times."
            );
        }

        // Alert 3: Spike — more than 5 reviews in the last 24 hours
        $recentCount = SkuReview::where('sku', $sku)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->count();

        if ($recentCount >= 5) {
            $this->createAlert($sku, $supplierId, 'spike_detected',
                "SKU {$sku}: {$recentCount} reviews in the last 24 hours — possible spike."
            );
        }
    }

    private function createAlert(string $sku, ?int $supplierId, string $type, string $message): void
    {
        // Avoid duplicate open alerts of the same type for same SKU
        $exists = ReviewAlert::where('sku', $sku)
            ->where('alert_type', $type)
            ->where('status', 'open')
            ->exists();

        if (!$exists) {
            ReviewAlert::create([
                'sku'         => $sku,
                'supplier_id' => $supplierId,
                'alert_type'  => $type,
                'message'     => $message,
                'status'      => 'open',
            ]);
        }
    }
}
