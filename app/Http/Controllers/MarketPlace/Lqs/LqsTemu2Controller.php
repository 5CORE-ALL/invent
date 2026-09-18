<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsTemu2Controller extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'temu2';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.temu2';
    }
}
