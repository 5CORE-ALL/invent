<?php

namespace App\Console\Commands;

use App\Jobs\SyncGoogleShoppingLiveBidBgt;
use Illuminate\Console\Command;

class SyncGoogleShoppingLiveBidBgtCommand extends Command
{
    protected $signature = 'google-shopping:sync-live-bid-bgt';

    protected $description = 'Verify Google Shopping live bids and budgets against the grid. Runs in this process.';

    public function handle(): int
    {
        // The google-shopping-live worker is not installed, so a queued job never runs
        // and LBid / LBgt stay on the previous day's comparison.
        app()->call([new SyncGoogleShoppingLiveBidBgt, 'handle']);
        $this->info('Verified Google Shopping live bids and budgets.');

        return self::SUCCESS;
    }
}
