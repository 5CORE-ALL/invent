<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\SalesOrderFulfillmentController;
use App\Services\FourSellerApiService;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\AliexpressTrackingSyncService;
use App\Services\MarketplaceManager\AmazonTrackingSyncService;
use App\Services\MarketplaceManager\EbaySellFulfillmentTracking;
use App\Services\MarketplaceManager\WayfairTrackingSyncService;
use App\Services\MarketplaceManager\Temu2OrderTrackingPullService;
use App\Services\MarketplaceManager\TemuOrderTrackingPullService;
use App\Services\VeeqoApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Every 15 minutes: fetch missing tracking numbers for Sales Order Fulfillment
 * (Temu OpenAPI + Veeqo / GOFO / 4Seller labels for Pending and Label Created).
 */
class PullSofMissingTracking extends Command
{
    protected $signature = 'sof:pull-missing-tracking
                            {--limit=400 : Max Pending + Label Created rows to look up on Veeqo/GOFO/channel APIs}
                            {--temu-limit=40 : Max Temu parent orders to pull}';

    protected $description = 'Pull missing SOF tracking numbers (Temu API + Veeqo/GOFO) for Pending and Label Created.';

    /** @var resource|null */
    private $singleRunLock = null;

    public function handle(
        SalesOrderFulfillmentController $sof,
        TemuOrderTrackingPullService $temuPull,
        Temu2OrderTrackingPullService $temu2Pull,
    ): int {
        $limit = max(1, min(400, (int) $this->option('limit')));
        $temuLimit = max(1, min(80, (int) $this->option('temu-limit')));
        $this->armHardStop(720);
        $this->stopStaleSiblingPulls();
        if (! $this->acquireSingleRunLock()) {
            return self::SUCCESS;
        }

        $started = microtime(true);
        $amazonDeadline = $started + 180;
        $ebayDeadline = $started + 360;
        $wayfairDeadline = $started + 450;
        $aliexpressDeadline = $started + 540;
        $labelDeadline = $started + 680;
        try {
            app(VeeqoApiService::class)->setTimeout(8);
            app(GofoExpressService::class)->setTimeout(8);
            app(FourSellerApiService::class)->setTimeout(8);
        } catch (\Throwable) {
        }

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

        try {
            $amazon = app(AmazonTrackingSyncService::class)->fillMissingSofTracking($limit, $amazonDeadline);
            $this->info('Amazon: '.((string) ($amazon['message'] ?? 'done')));
        } catch (\Throwable $e) {
            $this->warn('Amazon SOF tracking fill failed: '.$e->getMessage());
            Log::warning('sof:pull-missing-tracking Amazon failed', ['error' => $e->getMessage()]);
        }

        try {
            $ebay = app(EbaySellFulfillmentTracking::class)->fillMissingSofTracking($limit, $ebayDeadline);
            $this->info('eBay: '.((string) ($ebay['message'] ?? 'done')));
        } catch (\Throwable $e) {
            $this->warn('eBay SOF tracking fill failed: '.$e->getMessage());
            Log::warning('sof:pull-missing-tracking eBay failed', ['error' => $e->getMessage()]);
        }

        try {
            $wayfair = app(WayfairTrackingSyncService::class)->fillMissingSofTracking(min(40, $limit), $wayfairDeadline);
            $this->info('Wayfair: '.((string) ($wayfair['message'] ?? 'done')));
        } catch (\Throwable $e) {
            $this->warn('Wayfair SOF tracking fill failed: '.$e->getMessage());
            Log::warning('sof:pull-missing-tracking Wayfair failed', ['error' => $e->getMessage()]);
        }

        try {
            $aliexpress = app(AliexpressTrackingSyncService::class)->fillMissingSofTracking(min(30, $limit), $aliexpressDeadline);
            $this->info('AliExpress: '.((string) ($aliexpress['message'] ?? 'done')));
        } catch (\Throwable $e) {
            $this->warn('AliExpress SOF tracking fill failed: '.$e->getMessage());
            Log::warning('sof:pull-missing-tracking AliExpress failed', ['error' => $e->getMessage()]);
        }

        $label = [
            'checked' => 0,
            'updated' => 0,
            'with_tracking' => 0,
            'candidates' => 0,
            'message' => '',
        ];
        try {
            $label = $sof->pullMissingLabelCreatedTracking($limit, $labelDeadline);
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

    /**
     * SIGALRM interrupts a blocked poll()/curl read. Http::timeout does not.
     */
    private function armHardStop(int $seconds): void
    {
        if (! function_exists('pcntl_async_signals') || ! function_exists('pcntl_alarm')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function () use ($seconds): void {
            Log::warning('sof:pull-missing-tracking hard stop', ['seconds' => $seconds]);
            fwrite(STDERR, "Stopped after {$seconds}s so the next tracking pull can start.\n");
            exit(0);
        });
        pcntl_alarm($seconds);
    }

    /**
     * Copies older than 20 minutes are the pile-up (lock TTL expired, socket still open).
     */
    private function stopStaleSiblingPulls(): void
    {
        $self = getmypid();
        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file) {
            $pid = (int) basename(dirname($file));
            if ($pid <= 1 || $pid === $self) {
                continue;
            }
            $cmd = @file_get_contents($file);
            if (! is_string($cmd) || ! str_contains($cmd, 'sof:pull-missing-tracking')) {
                continue;
            }
            $age = $this->processAgeSeconds($pid);
            if ($age !== null && $age < 1200) {
                continue;
            }
            Log::warning('sof:pull-missing-tracking stopping stale copy', [
                'pid' => $pid,
                'age' => $age,
            ]);
            $this->warn('Stopping stale sof:pull-missing-tracking pid '.$pid.'.');
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGTERM);
            }
        }
    }

    private function processAgeSeconds(int $pid): ?int
    {
        $stat = @file_get_contents('/proc/'.$pid.'/stat');
        $uptime = @file_get_contents('/proc/uptime');
        if (! is_string($stat) || ! is_string($uptime)) {
            return null;
        }
        $rp = strrpos($stat, ')');
        if ($rp === false) {
            return null;
        }
        $parts = preg_split('/\s+/', trim(substr($stat, $rp + 1))) ?: [];
        $startTicks = (int) ($parts[19] ?? 0);
        if ($startTicks <= 0) {
            return null;
        }
        $uptimeSeconds = (float) strtok($uptime, ' ');
        $age = (int) round($uptimeSeconds - ($startTicks / 100));

        return max(0, $age);
    }

    private function acquireSingleRunLock(): bool
    {
        $path = storage_path('framework/sof-pull-missing-tracking.lock');
        $handle = fopen($path, 'c');
        if ($handle === false) {
            return true;
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            $this->warn('Another sof:pull-missing-tracking is still running. Skipping.');
            Log::info('sof:pull-missing-tracking skipped; lock held');

            return false;
        }
        $this->singleRunLock = $handle;

        return true;
    }
}
