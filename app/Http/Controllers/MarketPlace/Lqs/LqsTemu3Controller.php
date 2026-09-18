<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsTemu3Controller extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'temu3';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.temu3';
    }
}
