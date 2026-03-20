<?php

namespace App\Jobs;

use App\Services\EbayThreeApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateEbayThreePriceJob implements ShouldQueue
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

    public function handle(EbayThreeApiService $ebayApiService)
    {


        $response = $ebayApiService->reviseFixedPriceItem($this->itemId,$this->price);

        return $response;
    }
}
