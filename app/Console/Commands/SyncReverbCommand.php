<?php

namespace App\Console\Commands;

use App\Services\ReverbApiService;
use Illuminate\Console\Command;

class SyncReverbCommand extends Command
{
    protected $signature = 'reverb:sync';

    protected $description = 'Sync Reverb listings from API (inventory + listing status tables).';

    public function handle(ReverbApiService $reverb): int
    {
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            $this->error('Reverb API token not configured (REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).');

            return self::FAILURE;
        }

        $this->info('Running Reverb listing sync (state=all)...');
        $inventory = $reverb->getInventory();
        $this->info('Done. Listings processed: '.count($inventory));
        $this->comment('Same as: php artisan reverb:sync-listing-statuses');

        return self::SUCCESS;
    }
}
