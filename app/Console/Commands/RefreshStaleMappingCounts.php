<?php

namespace App\Console\Commands;

use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshStaleMappingCounts extends Command
{
    protected $signature = 'mm:refresh-stale-mapping-counts';

    protected $description = 'Rebuild Missing Mapping (/map-issues) counts when the 30-minute cache has expired, otherwise recount only channels that had an inventory push since the last run.';

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');

        MappingChannelCounts::seedStoredRows();

        if (! Cache::has(MappingChannelCounts::MASTER_ROWS_CACHE_KEY)) {
            // The full rebuild counts every channel; pushes during it stay marked for the next run.
            Cache::forget(MappingChannelCounts::STALE_CHANNELS_CACHE_KEY);
            MappingChannelCounts::rebuildMasterRowsIfExpired();
            $this->info('Rebuilt every channel count (cache had expired).');

            return self::SUCCESS;
        }

        $counts = MappingChannelCounts::refreshStaleChannels();
        if ($counts === []) {
            $this->info('No channels pushed since the last run.');

            return self::SUCCESS;
        }

        foreach ($counts as $channel => $count) {
            $this->line(sprintf('%-16s %d', $channel, $count));
        }

        return self::SUCCESS;
    }
}
