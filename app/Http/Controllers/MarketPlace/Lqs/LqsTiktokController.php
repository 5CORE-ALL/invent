<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsTiktokController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'tiktok';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.tiktok';
    }
}
