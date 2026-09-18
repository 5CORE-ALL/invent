<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsPlsController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'pls';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.pls';
    }
}
