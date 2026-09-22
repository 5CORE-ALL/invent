<?php

namespace App\Jobs;

use App\Http\Controllers\Campaigns\GoogleShoppingCampaignsController;
use App\Services\GoogleShoppingLiveBidBgtService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * One Google Ads pull for Shopping LBid and LBgt.
 * Not dispatched from the shopping grid. A second copy cannot run while this one holds the lock.
 */
class SyncGoogleShoppingLiveBidBgt implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 7200;

    public function __construct()
    {
        // Named queue: the server does not run a worker for the default queue.
        $this->onQueue('google-shopping-live');
    }

    public function uniqueId(): string
    {
        return 'google-shopping-live-bid-bgt';
    }

    public function handle(GoogleShoppingLiveBidBgtService $sync): void
    {
        @ini_set('memory_limit', '512M');
        $customerId = str_replace('-', '', trim((string) config('services.google_ads.login_customer_id')));
        $targets = app(GoogleShoppingCampaignsController::class)->shoppingLiveVerificationTargets();
        $stats = $sync->verifyAndStore($customerId, $targets, 'shopping');
        Log::info('SyncGoogleShoppingLiveBidBgt finished', [
            'campaigns' => count($targets),
            'bid_updated' => $stats['bid_updated'],
            'bgt_updated' => $stats['bgt_updated'],
            'error' => $stats['error'],
        ]);
    }
}
