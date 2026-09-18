<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsSheinController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'shein';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.shein';
    }
}
