<?php

namespace App\Services\MarketplaceManager;

use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Models\TemuPricing;
use App\Services\TemuApiService;

/**
 * Publish Missing L SKUs to Temu 1 via Open API, including independent (single) listings.
 */
class TemuListingPublishService extends Temu2ListingPublishService
{
    public function __construct(TemuApiService $api)
    {
        $this->api = $api;
    }

    protected function shopConfigKey(): string
    {
        return 'temu';
    }

    protected function shopLabel(): string
    {
        return 'Temu';
    }

    protected function credentialsHelp(): string
    {
        return 'Set TEMU_APP_KEY, TEMU_SECRET_KEY, and TEMU_ACCESS_TOKEN.';
    }

    protected function stdPriceHelp(): string
    {
        return 'Temu Analytics (/temu-decrease)';
    }

    protected function pricingTable(): string
    {
        return 'temu_pricing';
    }

    protected function metricsTable(): string
    {
        return 'temu_metrics';
    }

    protected function pricingClass(): string
    {
        return TemuPricing::class;
    }

    protected function metricClass(): string
    {
        return TemuMetric::class;
    }

    protected function dataViewClass(): string
    {
        return TemuDataView::class;
    }

    protected function costTemplateCacheKey(): string
    {
        return 'temu_cost_template_id_v1';
    }

    /**
     * @return list<string>
     */
    protected function listingCountCacheKeys(): array
    {
        return ['listing_channel_counts_v1:temu'];
    }
}
