<?php

namespace App\Console\Commands;

use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Console\Command;

class WarmInactiveListingsPage extends Command
{
    protected $signature = 'inactive-listings:warm-page';

    protected $description = 'Rebuild /inactive-listings table cache from local catalogs (no live marketplace API).';

    public function handle(): int
    {
        $rows = MappingChannelCounts::inactiveMasterRows(false);
        $child = (int) collect($rows)->sum('cp_inactive_child');
        $this->info('channels='.count($rows).' inactive_child='.$child);

        return self::SUCCESS;
    }
}
