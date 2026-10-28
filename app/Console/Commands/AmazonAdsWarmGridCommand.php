<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmazonAdsController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AmazonAdsWarmGridCommand extends Command
{
    protected $signature = 'amazon-ads:warm-grid {--force : Rebuild even if it was rebuilt a moment ago}';

    protected $description = 'Rebuild the cached /amazon-ads/all views (the default one and the ones people opened lately) so opening the page never waits on the database';

    private const STAMP_KEY = 'amz_ads_warm_grid_at';

    public function handle(): int
    {
        @set_time_limit(0);

        // The scheduler, cron and a visitor's page load may all start this; only one runs at a time.
        $lock = null;
        try {
            $lock = Cache::lock('amz_ads_warm_grid_run', 900);
            if (! $lock->get()) {
                $this->info('Amazon Ads grid is already being rebuilt, skipped.');

                return 0;
            }
        } catch (\Throwable) {
            $lock = null;
        }

        try {
            $controller = app(AmazonAdsController::class);
            $ok = true;

            $last = 0;
            if (! $this->option('force')) {
                try {
                    $last = (int) Cache::get(self::STAMP_KEY, 0);
                } catch (\Throwable) {
                    $last = 0;
                }
            }
            if ($last > 0 && time() - $last < 240) {
                $this->info('Amazon Ads default view was rebuilt '.(time() - $last).' s ago, skipped.');
            } else {
                $out = $controller->warmDefaultGrid();
                $ok = (bool) $out['ok'];
                if ($ok) {
                    try {
                        Cache::put(self::STAMP_KEY, time(), 3600);
                    } catch (\Throwable) {
                    }
                }
                $this->info(($ok ? 'Warmed' : 'Failed to warm').' amazon-ads default view: '.$out['rows'].' rows in '.$out['ms'].' ms.');
            }

            $hot = $controller->warmHotViews($this->option('force') ? 0 : 240);
            $this->info('Amazon Ads other views: '.$hot['rebuilt'].' rebuilt of '.$hot['views'].' in '.$hot['ms'].' ms.');

            return $ok ? 0 : 1;
        } finally {
            try {
                $lock?->release();
            } catch (\Throwable) {
            }
        }
    }
}
