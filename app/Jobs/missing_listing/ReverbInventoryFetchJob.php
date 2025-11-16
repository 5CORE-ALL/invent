<?php

namespace App\Jobs\missing_listing;

use App\Services\ReverbApiService;

class  ReverbInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'reverb';

    protected function fetchInventory()
    {
        return (new ReverbApiService())->getinventory();
    }
}