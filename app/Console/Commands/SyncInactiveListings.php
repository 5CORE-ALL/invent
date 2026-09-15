<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\InactiveListingsSyncService;
use Illuminate\Console\Command;

class SyncInactiveListings extends Command
{
    protected $signature = 'inactive-listings:sync';

    protected $description = 'Refresh marketplace listing statuses and rebuild /inactive-listings counts.';

    public function handle(InactiveListingsSyncService $sync): int
    {
        $this->info('Syncing Inactive Listings from marketplace portals…');
        $result = $sync->run();
        $this->info((string) ($result['message'] ?? 'Done.'));
        foreach ($result['channels'] ?? [] as $channel => $status) {
            $this->line('  '.$channel.': '.$status);
        }

        return self::SUCCESS;
    }
}
