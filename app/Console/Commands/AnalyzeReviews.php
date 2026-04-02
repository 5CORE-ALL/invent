<?php

namespace App\Console\Commands;

use App\Services\ReviewAnalysisService;
use Illuminate\Console\Command;

class AnalyzeReviews extends Command
{
    protected $signature = 'reviews:analyze {--batch=100 : Number of reviews to process per run}';

    protected $description = 'Analyze unprocessed SKU reviews: detect sentiment, classify issues, generate AI summaries';

    public function handle(ReviewAnalysisService $service): int
    {
        $batch = (int) $this->option('batch');

        $this->info("Starting review analysis (batch size: {$batch})...");

        $processed = $service->analyzeBatch($batch);

        $this->info("Analyzed {$processed} reviews.");

        if ($processed > 0) {
            $this->info("Refreshing summary table...");
            $service->refreshSummaryTable();
            $this->info("Summary table updated.");
        }

        return Command::SUCCESS;
    }
}
