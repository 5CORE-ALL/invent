<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\MissingListingController;
use Illuminate\Console\Command;

class WarmMissingListingPage extends Command
{
    protected $signature = 'missing-listing:warm-page';

    protected $description = 'Rebuild /missing-listing table cache from local catalogs (no live marketplace API).';

    public function handle(): int
    {
        $payload = app(MissingListingController::class)->rebuildPagePayload();
        $this->info('channels='.($payload['count'] ?? 0).' missing_l='.($payload['total_missing_l'] ?? 0));

        return self::SUCCESS;
    }
}
