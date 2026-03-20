<?php

namespace App\Jobs\missing_listing;

use App\Services\DobaApiService;

class  DobaInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'doba';

    protected function fetchInventory()
    {
        return (new DobaApiService())->getinventory();
    }
}