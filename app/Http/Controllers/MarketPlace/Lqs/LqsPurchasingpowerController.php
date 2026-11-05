<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesMiraklLqsApi;

class LqsPurchasingpowerController extends LqsMarketplaceBaseController
{
    use HandlesMiraklLqsApi;

    protected function slug(): string
    {
        return 'purchasingpower';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.purchasingpower';
    }
}
