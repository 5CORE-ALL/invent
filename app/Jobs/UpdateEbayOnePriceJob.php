<?php

namespace App\Jobs;

use App\Services\EbayApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateEbayOnePriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $itemId;
    protected $price;

    /**
     * Create a new job instance.
     */
    public function __construct($itemId, $price)
    {
        $this->itemId = $itemId;
        $this->price = $price;
    }


    /**
     * Execute the job.
     */
    public function handle(EbayApiService $ebayApiService)
    {
        $response = $ebayApiService->reviseFixedPriceItem($this->itemId, $this->price);

        return $response;
    }
}
