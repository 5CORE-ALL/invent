<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdvertisementMaster\AdvertisementMasterController;
use Illuminate\Console\Command;

class SnapshotAdvertisementMaster extends Command
{
    protected $signature = 'advertisement-master:snapshot
        {--backfill=40 : Re-check this many past Pacific days and insert any missing Active Channel history}';

    protected $description = 'Save /advertisement-master history (so days are stored even if nobody opens the page)';

    public function handle(): int
    {
        $result = app(AdvertisementMasterController::class)
            ->runScheduledSnapshot((int) $this->option('backfill'));

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error('Advertisement Master snapshot failed: '.$result['message']);

        return self::FAILURE;
    }
}
