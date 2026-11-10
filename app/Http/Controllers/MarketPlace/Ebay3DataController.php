<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\Ebay3Metric;
use App\Models\EbayThreeDataView;
use App\Services\EbayThreeApiService;

/**
 * "Ebay3 Data": the Ebay1 Data page for the eBay 3 account (its own tables, metrics and listings).
 * Whatever is changed on the eBay 1 page is repeated here by that page; changes made here stay on eBay 3.
 */
class Ebay3DataController extends Ebay1DataController
{
    protected function cfg(): array
    {
        return [
            'key' => 'ebay3',
            'label' => 'eBay 3',
            'prefix' => 'ebay3',
            'registry' => 'ebaythree',
            'base' => '/ebay3-item-data',
            'metric' => Ebay3Metric::class,
            'dataView' => EbayThreeDataView::class,
            'service' => EbayThreeApiService::class,
            'main' => false,
        ];
    }
}
