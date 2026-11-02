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
    protected $signature = 'app:amazon-kw-targeting-push {--check= : token of a dry-run check started from the page (nothing is pushed)}';

    protected $description = 'Push the app keywords to every KW campaign and each parent\'s HL campaign (background worker for the FBM targeting page)';

    public function handle(AmazonFbmTargetingController $page, AmazonKwTargetingPushService $push): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $token = (string) $this->option('check');
        if ($token !== '') {
            return $this->handleCheck($token, $page, $push);
        }

        try {
            $r = $push->run(
                $page->kwCampaignsForPush($page->pushCampaignIds()), // bulk push from the grid = only the picked campaigns,
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

    /** Dry run for the page's "what is missing" check: same result as before, but off the web request. */
    private function handleCheck(string $token, AmazonFbmTargetingController $page, AmazonKwTargetingPushService $push): int
    {
        try {
            $page->recordCheckState($token, ['status' => 'running', 'message' => 'Checking what is missing on Amazon…']);
            $r = $push->run($page->kwCampaignsForPush($page->checkCampaignIds($token)), true);
            if ($r['errors'] !== [] && $r['rows'] === []) {
                $page->recordCheckState($token, ['status' => 'error', 'message' => implode(' ', $r['errors'])]);

                return 1;
            }
            $page->recordCheckState($token, ['status' => 'done'] + $page->pushPayload($r, true));

            return 0;
        } catch (\Throwable $e) {
            report($e);
            $page->recordCheckState($token, ['status' => 'error', 'message' => 'Check failed: '.mb_substr($e->getMessage(), 0, 300)]);

            return 1;
        }
    }
}
