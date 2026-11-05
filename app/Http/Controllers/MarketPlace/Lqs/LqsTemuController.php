<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesTemuLqsApi;

class LqsTemuController extends LqsMarketplaceBaseController
{
    use HandlesTemuLqsApi;

    protected function slug(): string
    {
        return 'temu';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.temu';
    }
}
