<?php

namespace App\Jobs;

use App\Models\ProductMaster;
use App\Services\ReviewFetchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchReviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries   = 2;

    protected string $sku;
    protected string $marketplace;

    public function __construct(string $sku, string $marketplace = 'all')
    {
        $this->sku         = $sku;
        $this->marketplace = $marketplace;
        $this->onQueue('reviews');
    }

    public function handle(ReviewFetchService $service): void
    {
        Log::info("FetchReviewsJob: Starting", [
            'sku'         => $this->sku,
            'marketplace' => $this->marketplace,
        ]);

        $saved = 0;

        if (in_array($this->marketplace, ['amazon', 'all'])) {
            $saved += $service->fetchAmazonReviews($this->sku);
        }

        if (in_array($this->marketplace, ['ebay', 'all'])) {
            $saved += $service->fetchEbayReviews($this->sku);
        }

        Log::info("FetchReviewsJob: Complete", [
            'sku'   => $this->sku,
            'saved' => $saved,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("FetchReviewsJob: Failed", [
            'sku'   => $this->sku,
            'error' => $e->getMessage(),
        ]);
    }
}
