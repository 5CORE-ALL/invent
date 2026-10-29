<?php

namespace App\Console\Commands;

use App\Jobs\RefreshListingChannelJob;
use App\Services\MarketplaceManager\ListingChannelRefresher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshListingChannels extends Command
{
    protected $signature = 'listing:refresh
                            {channel=all : Listing page slug (ebaytwo, temu, ...) or "all"}
                            {--force : Ignore the "Auto-link listings by SKU" switch (the button always does)}
                            {--queue : Dispatch each marketplace as a queued job instead of running here}';

    protected $description = 'Refresh listing data (ended/deleted/new listings) and Missing L counts for one or all marketplaces.';

    public function handle(ListingChannelRefresher $refresher): int
    {
        $arg = ListingChannelRefresher::normalizeSlug((string) $this->argument('channel'));
        $slugs = $arg === 'all' ? array_keys(ListingChannelRefresher::plan()) : [$arg];
        $manual = (bool) $this->option('force');
        $failed = 0;

        foreach ($slugs as $slug) {
            if ($this->option('queue')) {
                ListingChannelRefresher::markQueued($slug);
                RefreshListingChannelJob::dispatch($slug, $manual);
                $this->line("{$slug}: queued");

                continue;
            }

            $status = $refresher->run($slug, $manual);
            $this->line("{$slug}: ".($status['state'] ?? '?').' — '.($status['message'] ?? ''));
            foreach ($status['steps'] ?? [] as $step) {
                $this->line('   '.($step['ok'] ? '[ok]  ' : '[FAIL]').' '.$step['label'].': '.$step['message']);
            }
            if (($status['state'] ?? '') === 'failed') {
                $failed++;
            }
        }

        if ($arg === 'all') {
            Cache::put('listing_refresh_daily_last_run', now()->toIso8601String(), now()->addDays(3));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
