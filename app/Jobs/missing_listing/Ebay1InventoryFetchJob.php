<?php

namespace App\Jobs\missing_listing;

use App\Services\EbayApiService;

class  Ebay1InventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'ebay1';

    protected function fetchInventory()
    {
        return (new EbayApiService())->getEbayInventory();
    }
}