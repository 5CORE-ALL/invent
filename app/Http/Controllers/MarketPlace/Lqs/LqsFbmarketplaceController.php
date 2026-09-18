<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsFbmarketplaceController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'fbmarketplace';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.fbmarketplace';
    }
}
