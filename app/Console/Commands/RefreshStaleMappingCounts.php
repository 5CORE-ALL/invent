<?php

namespace App\Console\Commands;

use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Console\Command;

class RefreshStaleMappingCounts extends Command
{
    protected $signature = 'mm:refresh-stale-mapping-counts';

    protected $description = 'Recount Missing Mapping (/map-issues) only for channels that had an inventory push since the last run.';

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '2048M');

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
