<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesWayfairLqsApi;

class LqsWayfairController extends LqsMarketplaceBaseController
{
    use HandlesWayfairLqsApi;

    protected function slug(): string
    {
        return 'wayfair';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.wayfair';
    }
}
