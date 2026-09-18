<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

class LqsReverbController extends LqsMarketplaceBaseController
{
    protected function slug(): string
    {
        return 'reverb';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.reverb';
    }
}
