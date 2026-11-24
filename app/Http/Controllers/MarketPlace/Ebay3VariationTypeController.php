<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\Ebay3VariationTypePush;
use App\Services\EbayThreeApiService;

/**
 * eBay 3 Variation Type: the same page as eBay 2 Variation Type, reading and changing the listings of the eBay 3
 * account (ebay_3_metrics for the parent list, EbayThreeApiService for GetItem / ReviseFixedPriceItem).
 */
class Ebay3VariationTypeController extends Ebay2VariationTypeController
{
    protected function channelNo(): int
    {
        return 3;
    }

    protected function label(): string
    {
        return 'eBay 3';
    }

    protected function metricsTable(): string
    {
        return 'ebay_3_metrics';
    }

    protected function pushTable(): string
    {
        return 'ebay3_variation_type_pushes';
    }

    protected function pushModel(): string
    {
        return Ebay3VariationTypePush::class;
    }

    protected function ebayService(): object
    {
        return app(EbayThreeApiService::class);
    }

    /** eBay 3 is read from eBay itself: every live parent is listed even before its SKUs are in ebay_3_metrics. */
    protected function includeLiveParents(): bool
    {
        return true;
    }

    protected function pageConfig(): array
    {
        return [
            'title' => 'Ebay 3 Variation Type',
            'label' => 'eBay 3',
            'route' => 'ebay3.variation.type',
            'store' => 'e3vt_hidden_columns',
        ];
    }
}
