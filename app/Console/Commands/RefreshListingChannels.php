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
                            {--queue : Dispatch each marketplace as a queued job instead of running here}
                            {--counts-only : Only recount Missing L from the data already in the app (what "Refresh all Missing L now" does); nothing is fetched from the marketplaces}';

    protected $description = 'Refresh listing data (ended/deleted/new listings) and Missing L counts for one or all marketplaces.';

    public function handle(ListingChannelRefresher $refresher): int
    {
        $arg = ListingChannelRefresher::normalizeSlug((string) $this->argument('channel'));
        $slugs = $arg === 'all' ? ListingChannelRefresher::orderedSlugs() : [$arg];
        $manual = (bool) $this->option('force');
        $countsOnly = (bool) $this->option('counts-only');
        $failed = 0;

        foreach ($slugs as $slug) {
            // A marketplace whose refresh is executing right now is left alone.
            if ($countsOnly && ListingChannelRefresher::isRunning($slug)) {
                $this->line("{$slug}: already running");

                continue;
            }
            if ($this->option('queue')) {
                ListingChannelRefresher::markQueued($slug);
                RefreshListingChannelJob::dispatch($slug, $manual || $countsOnly, $countsOnly);
                $this->line("{$slug}: queued");

                continue;
            }

            $status = $refresher->run($slug, $manual || $countsOnly, $countsOnly);
            $this->line("{$slug}: ".($status['state'] ?? '?').' — '.($status['message'] ?? ''));
            foreach ($status['steps'] ?? [] as $step) {
                $this->line('   '.($step['ok'] ? '[ok]  ' : '[FAIL]').' '.$step['label'].': '.$step['message']);
            }
            if (($status['state'] ?? '') === 'failed') {
                $failed++;
            }
        }

        if ($arg === 'all' && ! $countsOnly) {
            Cache::put('listing_refresh_daily_last_run', now()->toIso8601String(), now()->addDays(3));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
