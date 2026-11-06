<?php

namespace App\Http\Controllers\MarketPlace\Lqs;

use App\Http\Controllers\MarketPlace\Lqs\Concerns\HandlesAlibabaLqsApi;

class LqsAlibabaController extends LqsMarketplaceBaseController
{
    use HandlesAlibabaLqsApi;

    protected function slug(): string
    {
        return 'alibaba';
    }

    protected function viewName(): string
    {
        return 'market-places.lqs.alibaba';
    }
}
