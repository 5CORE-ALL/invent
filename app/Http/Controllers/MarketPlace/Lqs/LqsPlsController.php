<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesPlsLqsApi;

class LqsPlsController extends LqsMarketplaceBaseController
{
    use HandlesPlsLqsApi;

    protected function slug(): string
    {
        return 'pls';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.pls';
    }
}
