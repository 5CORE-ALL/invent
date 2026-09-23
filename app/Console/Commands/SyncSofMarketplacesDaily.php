<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketplaceOrdersJob;
use App\Services\MarketplaceManager\AliexpressTrackingSyncService;
use App\Services\MarketplaceManager\AmazonTrackingSyncService;
use App\Services\MarketplaceManager\EbaySellFulfillmentTracking;
use App\Services\MarketplaceManager\Temu2OrderTrackingPullService;
use App\Services\MarketplaceManager\TemuOrderTrackingPullService;
use App\Services\MarketplaceManager\WayfairTrackingSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Once a day: refresh every marketplace's last 30 days, then copy tracking
 * again from Amazon, eBay, Wayfair, AliExpress, and Temu.
 *
 * The 15-minute pull only continues a cursor. This job throws that cursor
 * away so a finished pass cannot leave older orders behind until tomorrow.
 */
class SyncSofMarketplacesDaily extends Command
{
    /** @var resource|null */
    private $singleRunLock = null;

    protected $signature = 'sof:sync-marketplaces-daily';

    protected $description = 'Daily 30-day order sync for every marketplace, then a full tracking copy.';

    /** @var list<string> */
    private array $marketplaces = [
        'aliexpress',
        'alibaba',
        'reverb',
        'newegg',
        'shein',
        'amazon',
        'topdawg',
        'temu',
        'temu2',
        'purchasingpower',
        'wayfair',
        'bestbuy',
        'macy',
        'doba',
        'ebay1',
        'ebay2',
        'ebay3',
        'faire',
        'tiktok',
        'tiktok2',
        'b5cb2b',
    ];

    public function handle(
        TemuOrderTrackingPullService $temuPull,
        Temu2OrderTrackingPullService $temu2Pull,
        AmazonTrackingSyncService $amazon,
        EbaySellFulfillmentTracking $ebay,
        WayfairTrackingSyncService $wayfair,
        AliexpressTrackingSyncService $aliexpress,
    ): int {
        $this->armHardStop(2700);

        foreach ($this->marketplaces as $slug) {
            SyncMarketplaceOrdersJob::dispatch($slug, '', true, 30);
        }
        $this->info('Queued a 30-day order sync for '.count($this->marketplaces).' marketplaces.');

        if (! $this->waitForTrackingLock(90)) {
            $this->warn('The 15-minute tracking pull still holds the lock. Daily tracking copy skipped.');

            return self::SUCCESS;
        }

        foreach ([
            'sof.amazon.package_history',
            'sof.amazon.package_recent',
            'sof.ebay.tracking_sync.ebay1',
            'sof.ebay.tracking_sync.ebay2',
            'sof.ebay.tracking_sync.ebay3',
        ] as $key) {
            Cache::forget($key);
        }

        $started = microtime(true);
        $this->runUntil($started + 700, 'Amazon', 'pages', function () use ($amazon, $started) {
            return $amazon->fillFromAmazonPackages($started + 700);
        });
        $this->runUntil($started + 1400, 'eBay', 'checked', function () use ($ebay, $started) {
            return $ebay->fillMissingSofTracking(200, $started + 1400);
        });
        $this->runUntil($started + 1800, 'Wayfair', 'filled', function () use ($wayfair, $started) {
            return $wayfair->fillMissingSofTracking(80, $started + 1800);
        });
        $this->runUntil($started + 2300, 'AliExpress', 'filled', function () use ($aliexpress, $started) {
            return $aliexpress->fillMissingSofTracking(60, $started + 2300);
        });

        try {
            $temu = $temuPull->pullPending(80, false);
            $this->info('Temu: '.((string) ($temu['message'] ?? 'done')));
        } catch (\Throwable $e) {
            $this->warn('Temu daily pull failed: '.$e->getMessage());
        }

        try {
            $temu2 = $temu2Pull->pullPending(80, false);
            $this->info('Temu 2: '.((string) ($temu2['message'] ?? 'done')));
        } catch (\Throwable $e) {
            $this->warn('Temu 2 daily pull failed: '.$e->getMessage());
        }

        Log::info('sof:sync-marketplaces-daily finished', [
            'marketplaces' => count($this->marketplaces),
            'seconds' => (int) round(microtime(true) - $started),
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  'pages'|'checked'|'filled'  $keepGoing
     * @param  callable(): array<string, mixed>  $step
     */
    private function runUntil(float $deadline, string $label, string $keepGoing, callable $step): void
    {
        $passes = 0;
        $filled = 0;
        while (microtime(true) < $deadline && $passes < 40) {
            $passes++;
            try {
                $result = $step();
            } catch (\Throwable $e) {
                $this->warn($label.' daily sync failed: '.$e->getMessage());
                Log::warning('sof:sync-marketplaces-daily step failed', [
                    'step' => $label,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
            $passFilled = (int) ($result['filled'] ?? 0);
            $filled += $passFilled;
            if (! empty($result['throttled'])) {
                sleep(15);
                continue;
            }
            if (! empty($result['complete'])) {
                break;
            }
            $signal = match ($keepGoing) {
                'pages' => (int) ($result['pages'] ?? 0),
                'checked' => (int) ($result['checked'] ?? 0),
                default => $passFilled,
            };
            if ($signal === 0) {
                break;
            }
        }
        $this->info($label.': '.$passes.' pass(es), tracking saved '.$filled.'.');
    }

    private function waitForTrackingLock(int $seconds): bool
    {
        $path = storage_path('framework/sof-pull-missing-tracking.lock');
        $handle = fopen($path, 'c');
        if ($handle === false) {
            return true;
        }

        $until = microtime(true) + $seconds;
        while (! flock($handle, LOCK_EX | LOCK_NB)) {
            if (microtime(true) >= $until) {
                fclose($handle);

                return false;
            }
            sleep(5);
        }
        $this->singleRunLock = $handle;

        return true;
    }

    private function armHardStop(int $seconds): void
    {
        if (! function_exists('pcntl_async_signals') || ! function_exists('pcntl_alarm')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function () use ($seconds): void {
            Log::warning('sof:sync-marketplaces-daily hard stop', ['seconds' => $seconds]);
            exit(0);
        });
        pcntl_alarm($seconds);
    }
}
