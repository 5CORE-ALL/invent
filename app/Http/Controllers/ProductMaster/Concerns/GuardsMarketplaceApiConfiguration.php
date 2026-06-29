<?php

namespace App\Http\Controllers\ProductMaster\Concerns;

use App\Services\Support\MarketplaceApiConfigService;

trait GuardsMarketplaceApiConfiguration
{
    /**
     * @return array{success: false, message: string}|null
     */
    protected function marketplaceApiNotConfiguredResult(string $marketplace): ?array
    {
        if (! app(MarketplaceApiConfigService::class)->isConfigured($marketplace)) {
            return [
                'success' => false,
                'message' => 'API not configured.',
            ];
        }

        return null;
    }
}
