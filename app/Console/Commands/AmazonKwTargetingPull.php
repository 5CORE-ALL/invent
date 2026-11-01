<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmazonAds\AmazonFbmTargetingController;
use Illuminate\Console\Command;

/**
 * Background worker behind the "Pull Keywords" / "Pull Negative KW" buttons on /amazon-ads/fbm-targeting.
 * Reading every KW campaign from Amazon takes longer than a web request may last, so the page starts this command
 * and polls its progress (AmazonFbmTargetingController::pullStatus()).
 */
class AmazonKwTargetingPull extends Command
{
    protected $signature = 'app:amazon-kw-targeting-pull {kind=kw : kw or neg}';

    protected $description = 'Pull keywords / negative keywords of all KW campaigns from Amazon into the app (background worker for the FBM targeting page)';

    public function handle(AmazonFbmTargetingController $page): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $kind = $this->argument('kind') === 'neg' ? 'neg' : 'kw';

        try {
            $r = $page->runPull($kind);
            $page->recordPullState($kind, ['status' => 'done', 'finished_at' => now()->toDateTimeString()] + $r);
            $this->info($r['message']);

            return 0;
        } catch (\Throwable $e) {
            report($e);
            $page->recordPullState($kind, [
                'status' => 'error',
                'finished_at' => now()->toDateTimeString(),
                'message' => 'Could not fetch from Amazon: '.mb_substr($e->getMessage(), 0, 300),
            ]);
            $this->error($e->getMessage());

            return 1;
        }
    }
}
