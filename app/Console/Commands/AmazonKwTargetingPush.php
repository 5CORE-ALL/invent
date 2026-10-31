<?php

namespace App\Console\Commands;

use App\Http\Controllers\AmazonAds\AmazonFbmTargetingController;
use App\Services\AmazonKwTargetingPushService;
use Illuminate\Console\Command;

/**
 * Background worker behind the "Push KW to KW + HL Campaigns" button on /amazon-ads/fbm-targeting.
 * The push sends tens of thousands of keywords, which takes far longer than a web request may last, so the page
 * starts this command and polls its progress (stored by AmazonFbmTargetingController::recordPushState()).
 */
class AmazonKwTargetingPush extends Command
{
    protected $signature = 'app:amazon-kw-targeting-push';

    protected $description = 'Push the app keywords to every KW campaign and each parent\'s HL campaign (background worker for the FBM targeting page)';

    public function handle(AmazonFbmTargetingController $page, AmazonKwTargetingPushService $push): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        try {
            $r = $push->run(
                $page->kwCampaignsForPush(),
                false,
                function (array $rows, array $totals, string $note) use ($page): void {
                    $page->recordPushState(['message' => $note, 'totals' => $totals, 'rows' => $rows]);
                },
                $page->pushSelection() // only the campaigns ticked in the check
            );

            $payload = $page->pushPayload($r, false);
            if ($r['errors'] !== [] && $r['rows'] === []) {
                $page->recordPushState(['status' => 'error', 'message' => implode(' ', $r['errors']), 'errors' => $r['errors']], true);
                $this->error(implode(' ', $r['errors']));

                return 1;
            }
            $page->recordPushState([
                'status' => 'done',
                'finished_at' => now()->toDateTimeString(),
                'message' => $payload['message'],
                'totals' => $payload['totals'],
                'rows' => $payload['rows'],
                'errors' => $payload['errors'],
            ], true);
            $this->info($payload['message']);

            return 0;
        } catch (\Throwable $e) {
            report($e);
            $page->recordPushState(['status' => 'error', 'finished_at' => now()->toDateTimeString(), 'message' => 'Push failed: '.mb_substr($e->getMessage(), 0, 300)], true);
            $this->error($e->getMessage());

            return 1;
        }
    }
}
