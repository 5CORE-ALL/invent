<?php

namespace App\Jobs\missing_listing;

use App\Services\MacysApiService;

class  MacysInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'macy';

    protected function fetchInventory()
    {
        return (new MacysApiService())->getinventory();
    }
}