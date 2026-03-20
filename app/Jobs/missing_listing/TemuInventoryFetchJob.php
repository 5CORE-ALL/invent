<?php

namespace App\Jobs\missing_listing;

use App\Services\TemuApiService;

class  TemuInventoryFetchJob extends BaseInventoryFetchJob
{
    protected $platform = 'temu';

    protected function fetchInventory()
    {
        return (new TemuApiService())->getinventory();
    }
}