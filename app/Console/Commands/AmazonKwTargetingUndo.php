<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmazonAds\AmazonFbmTargetingController;
use Illuminate\Console\Command;

/**
 * Background worker behind the "Undo a push" button on /amazon-ads/fbm-targeting: archives, on Amazon, the keywords a
 * logged push run sent (and only those). Progress is shown through the page's sync progress bar.
 */
class AmazonKwTargetingUndo extends Command
{
    protected $signature = 'app:amazon-kw-targeting-undo {--run= : run token from amazon_kw_push_log}';

    protected $description = 'Archive the keywords of one logged FBM targeting push run on Amazon (background worker)';

    public function handle(AmazonFbmTargetingController $page): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $token = (string) $this->option('run');
        try {
            $r = $page->runUndo($token);
            $page->recordSyncState(['status' => 'done', 'percent' => 100, 'finished_at' => now()->toDateTimeString()] + $r);
            $this->info($r['message']);

            return 0;
        } catch (\Throwable $e) {
            report($e);
            $page->recordSyncState(['status' => 'error', 'finished_at' => now()->toDateTimeString(), 'message' => 'Undo failed: '.mb_substr($e->getMessage(), 0, 300)]);
            $this->error($e->getMessage());

            return 1;
        }
    }
}
