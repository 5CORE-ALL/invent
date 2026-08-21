<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\SalesOrderFulfillmentController;
use App\Services\MarketplaceManager\Temu2OrderTrackingPullService;
use App\Services\MarketplaceManager\TemuOrderTrackingPullService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly: fetch missing tracking numbers for Sales Order Fulfillment
 * (Temu OpenAPI + Veeqo / GOFO / 4Seller labels).
 */
class PullSofMissingTracking extends Command
{
    protected $signature = 'sof:pull-missing-tracking
                            {--limit=150 : Max Pending + Label Created rows to look up on Veeqo/GOFO}
                            {--temu-limit=40 : Max Temu parent orders to pull}';

    protected $description = 'Pull missing SOF tracking numbers (Temu API + Veeqo/GOFO) for Pending and Label Created.';

    public function handle(
        SalesOrderFulfillmentController $sof,
        TemuOrderTrackingPullService $temuPull,
        Temu2OrderTrackingPullService $temu2Pull,
    ): int {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $temuLimit = max(1, min(80, (int) $this->option('temu-limit')));

        $temu = ['success' => true, 'message' => 'skipped', 'updated' => 0];
        $temu2 = ['success' => true, 'message' => 'skipped', 'updated' => 0];

        try {
            $temu = $temuPull->pullPending($temuLimit, false);
            $this->info('Temu: '.((string) ($temu['message'] ?? 'done')));
        } catch (\Throwable $e) {
            $this->warn('Temu pull failed: '.$e->getMessage());
            Log::warning('sof:pull-missing-tracking Temu failed', ['error' => $e->getMessage()]);
        }

        try {
            $temu2 = $temu2Pull->pullPending($temuLimit, false);
            $this->info('Temu 2: '.((string) ($temu2['message'] ?? 'done')));
        } catch (\Throwable $e) {
            $this->warn('Temu 2 pull failed: '.$e->getMessage());
            Log::warning('sof:pull-missing-tracking Temu2 failed', ['error' => $e->getMessage()]);
        }

        $label = [
            'checked' => 0,
            'updated' => 0,
            'with_tracking' => 0,
            'candidates' => 0,
            'message' => '',
        ];
        try {
            $label = $sof->pullMissingLabelCreatedTracking($limit);
            $this->info('Veeqo/GOFO: '.((string) ($label['message'] ?? 'done'))
                .' (missing candidates: '.((int) ($label['candidates'] ?? 0)).')');
        } catch (\Throwable $e) {
            $this->error('Label tracking pull failed: '.$e->getMessage());
            Log::error('sof:pull-missing-tracking label pull failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        Log::info('sof:pull-missing-tracking finished', [
            'temu_updated' => (int) ($temu['updated'] ?? 0),
            'temu2_updated' => (int) ($temu2['updated'] ?? 0),
            'label_checked' => (int) ($label['checked'] ?? 0),
            'label_found' => (int) ($label['with_tracking'] ?? 0),
            'label_candidates' => (int) ($label['candidates'] ?? 0),
        ]);

        return self::SUCCESS;
    }
}
