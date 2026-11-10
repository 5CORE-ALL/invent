<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\Ebay2Metric;
use App\Models\EbayTwoDataView;
use App\Services\Ebay2ApiService;

/**
 * "Ebay2 Data": the Ebay1 Data page for the eBay 2 account (its own tables, metrics and listings).
 * Whatever is changed on the eBay 1 page is repeated here by that page; changes made here stay on eBay 2.
 */
class Ebay2DataController extends Ebay1DataController
{
    protected function cfg(): array
    {
        return [
            'key' => 'ebay2',
            'label' => 'eBay 2',
            'prefix' => 'ebay2',
            'registry' => 'ebaytwo',
            'base' => '/ebay2-item-data',
            'metric' => Ebay2Metric::class,
            'dataView' => EbayTwoDataView::class,
            'service' => Ebay2ApiService::class,
            'main' => false,
        ];
    }
}
