<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsDobaController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'doba';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.doba';
    }
}
