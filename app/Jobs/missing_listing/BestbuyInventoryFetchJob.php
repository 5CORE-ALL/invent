<?php

namespace App\Jobs\missing_listing;

use App\Services\BestBuyApiService;

class  BestbuyInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'bestbuy';

    protected function fetchInventory()
    {
        return (new BestBuyApiService())->getinventory();
    }
}