<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesFaireLqsApi;

class LqsFaireController extends LqsMarketplaceBaseController
{
    use HandlesFaireLqsApi;

    protected function slug(): string
    {
        return 'faire';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.faire';
    }
}
