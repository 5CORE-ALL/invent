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
}
