<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsPurchasingpowerController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'purchasingpower';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.purchasingpower';
    }
}
