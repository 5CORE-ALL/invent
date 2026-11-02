<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmazonAds\AmazonFbmTargetingController;
use Illuminate\Console\Command;

/**
 * Background worker behind the "Sync with Amazon" buttons on /amazon-ads/fbm-targeting: pulls the live keywords of the
 * KW campaigns and pushes what is missing, chunk by chunk. The page only polls its stored progress.
 */
class AmazonKwTargetingSync extends Command
{
    protected $signature = 'app:amazon-kw-targeting-sync';

    protected $description = 'Pull + push keywords of the KW campaigns in chunks (background worker for the FBM targeting page)';

    public function handle(AmazonFbmTargetingController $page): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        try {
            $r = $page->runSync();
            $page->recordSyncState(['status' => 'done', 'percent' => 100, 'finished_at' => now()->toDateTimeString()] + $r);
            $this->info($r['message']);

            return 0;
        } catch (\Throwable $e) {
            report($e);
            $page->recordSyncState([
                'status' => 'error',
                'finished_at' => now()->toDateTimeString(),
                'message' => 'Sync failed: '.mb_substr($e->getMessage(), 0, 300).' Keywords already sent are kept; start it again to continue.',
            ]);
            $this->error($e->getMessage());

            return 1;
        }
    }
}
