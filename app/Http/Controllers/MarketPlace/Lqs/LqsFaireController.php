<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsFaireController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'faire';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.faire';
    }
}
