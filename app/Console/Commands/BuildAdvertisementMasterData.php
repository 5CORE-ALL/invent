<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdvertisementMaster\AdvertisementMasterController;
use Illuminate\Console\Command;

class BuildAdvertisementMasterData extends Command
{
    protected $signature = 'advertisement-master:build-data
        {--backfill=40 : Re-check this many past Pacific days and insert any missing Active Channel history}';

    protected $description = 'Pre-build the /advertisement-master grid and store it in advertisement_master_data_cache so the page loads instantly';

    public function handle(): int
    {
        $result = app(AdvertisementMasterController::class)
            ->runScheduledSnapshot((int) $this->option('backfill'), true);

        if ($result['ok']) {
            $this->info('Advertisement Master data stored. '.$result['message']);

            return self::SUCCESS;
        }

        $this->error('Advertisement Master data build failed: '.$result['message']);

        return self::FAILURE;
    }
}
