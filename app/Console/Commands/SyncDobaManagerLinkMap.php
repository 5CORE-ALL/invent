<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\DobaLinkMapSyncService;
use Illuminate\Console\Command;

class SyncDobaManagerLinkMap extends Command
{
    protected $signature = 'doba:sync-link-map';

    protected $description = 'Refresh doba_metrics from Doba OpenAPI goods/detail.';

    public function handle(DobaLinkMapSyncService $sync): int
    {
        $result = $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
