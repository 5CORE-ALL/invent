<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmazonAdsController;
use Illuminate\Console\Command;

class AmazonAdsWarmGridCommand extends Command
{
    protected $signature = 'amazon-ads:warm-grid';

    protected $description = 'Rebuild the cached first page of /amazon-ads/all so opening the page does not wait on the database';

    public function handle(): int
    {
        @set_time_limit(0);
        $out = app(AmazonAdsController::class)->warmDefaultGrid();
        $this->info(($out['ok'] ? 'Warmed' : 'Failed to warm').' amazon-ads grid: '.$out['rows'].' rows in '.$out['ms'].' ms.');

        return $out['ok'] ? 0 : 1;
    }
}
