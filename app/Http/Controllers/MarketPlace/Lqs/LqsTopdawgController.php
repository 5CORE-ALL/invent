<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsTopdawgController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'topdawg';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.topdawg';
    }
}
