<?php

namespace App\Console\Commands;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\FaireLinkMapSyncService;
use Illuminate\Console\Command;

class SyncFaireManagerLinkMap extends Command
{
    protected $signature = 'faire:sync-link-map
                            {--force : Run even if Auto-link listings by SKU is Off}
                            {--sku= : Reconcile one SKU from the Faire products API}';

    protected $description = 'Refresh Faire SKU ↔ product_id link map from Faire API (local only).';

    public function handle(FaireLinkMapSyncService $sync): int
    {
        if (! $this->option('force') && ! MarketplaceSyncSettings::canAutoLinkBySku('faire')) {
            $this->info('Skipped: Auto-link listings by SKU is Off in Faire Marketplace Manager settings.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        $sku = trim((string) $this->option('sku'));
        $result = $sku !== '' ? $sync->syncSku($sku) : $sync->syncAll();
        $this->info($result['message'] ?? 'Done.');

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
