<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsTemuController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'temu';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.temu';
    }
}
