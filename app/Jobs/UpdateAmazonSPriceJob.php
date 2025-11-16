<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Services\AmazonSpApiService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateAmazonSPriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sellerId;
    protected $sku;
    protected $price;
    protected $currency;

    /**
     * Create a new job instance.
     */
    public function __construct($sku, $price)
    {
        $this->sku = $sku;
        $this->price = $price;
    }

    public function handle(AmazonSpApiService $amazonService)
    {
        try {
            $response = $amazonService->updateAmazonPriceUS(
                $this->sku,
                $this->price
            );

            Log::info('Amazon Price Update Response', [
                'sku' => $this->sku,
                'response' => $response
            ]);
        } catch (\Throwable $e) {
            Log::error("Amazon Price Update Failed for SKU: {$this->sku}", [
                'error' => $e->getMessage()
            ]);

            // Optional: rethrow to retry
            throw $e;
        }
    }
}
