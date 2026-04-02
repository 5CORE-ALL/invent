<?php

namespace App\Jobs;

use App\Models\SkuReview;
use App\Services\ReviewAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries   = 3;

    protected int $reviewId;

    public function __construct(int $reviewId)
    {
        $this->reviewId = $reviewId;
        $this->onQueue('reviews');
    }

    public function handle(ReviewAnalysisService $service): void
    {
        $review = SkuReview::find($this->reviewId);

        if (!$review) {
            Log::warning("AnalyzeReviewJob: Review #{$this->reviewId} not found");
            return;
        }

        if ($review->sentiment !== null) {
            // Already analyzed
            return;
        }

        $service->analyzeReview($review);

        // Refresh the summary for this SKU
        $service->refreshSkuSummary($review->sku);

        Log::info("AnalyzeReviewJob: Analyzed review #{$this->reviewId}", [
            'sku'       => $review->sku,
            'sentiment' => $review->sentiment,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("AnalyzeReviewJob: Failed for review #{$this->reviewId}", [
            'error' => $e->getMessage(),
        ]);
    }
}
