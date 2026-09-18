<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsNeweggController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'newegg';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.newegg';
    }
}
