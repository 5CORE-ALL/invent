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
        $token = config('services.reverb.token');
        if (! $token) {
            $this->error('Reverb API token not configured (services.reverb.token).');

            return self::FAILURE;
        }

        $this->info('Running Reverb listing sync (state=all)...');
        $inventory = $reverb->getInventory();
        $this->info('Done. Listings processed: '.count($inventory));
        $this->comment('Same as: php artisan reverb:sync-listing-statuses');

        return self::SUCCESS;
    }
}
