<?php

namespace App\Console\Commands;

use App\Jobs\SyncGoogleShoppingLiveBidBgt;
use Illuminate\Console\Command;

class SyncGoogleShoppingLiveBidBgtCommand extends Command
{
    protected $signature = 'google-shopping:sync-live-bid-bgt';

    protected $description = 'Queue one Google Shopping live bid and budget verification. This command does not call Google Ads.';

    public function handle(): int
    {
        SyncGoogleShoppingLiveBidBgt::dispatch();
        $this->info('Queued one Google Shopping live bid/budget sync.');

        return self::SUCCESS;
    }
}
