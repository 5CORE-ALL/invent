<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsTiktok2Controller extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'tiktok2';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.tiktok2';
    }
}
