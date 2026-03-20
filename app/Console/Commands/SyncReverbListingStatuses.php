<?php

namespace App\Console\Commands;

use App\Services\ReverbApiService;
use Illuminate\Console\Command;

class SyncReverbListingStatuses extends Command
{
    protected $signature = 'reverb:sync-listing-statuses';

    protected $description = 'Fetch all Reverb listings (state=all) and update ReverbListingStatus + ProductStockMapping. Run every 6 hours.';

    public function handle(): int
    {
        $token = config('services.reverb.token');
        if (!$token) {
            $this->error('Reverb API token not configured (services.reverb.token).');
            return self::FAILURE;
        }

        $this->info('Syncing all Reverb listing statuses (state=all, per_page=100)...');
        $service = app(ReverbApiService::class);
        $inventory = $service->getInventory();
        $this->info('Done. Total listings synced: ' . count($inventory));
        return self::SUCCESS;
    }
}
