<?php

namespace App\Services;

/**
 * Purchasing Power — Mirakl Connect channel (same credentials as Macy's / Best Buy).
 */
class PurchasingPowerApiService extends BestBuyApiService
{
    protected function miraklChannelCode(): string
    {
        return 'purchasingpower';
    }

    protected function miraklMcmConfigKey(): string
    {
        return 'purchasingpower';
    }

    protected function miraklMcmMarketplaceLabel(): string
    {
        return 'Purchasing Power';
    }

    protected function miraklMcmHierarchyTable(): ?string
    {
        return null;
    }
}
