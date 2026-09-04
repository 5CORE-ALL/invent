<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * One-shot daily catch-up for Marketplace Manager:
 * fetch orders → Shopify import (duplicate-safe) → auto-fulfill Shopify → push tracking.
 *
 * Each marketplace is isolated. A failure on Amazon (or any channel) does not stop the rest.
 */
class PushMarketplaceOrdersTrackingFulfillment extends Command
{
    protected $signature = 'mm:push-orders-tracking
        {--marketplace= : Only this MM slug (amazon, faire, …)}
        {--days=7 : Order lookback days}
        {--skip-fetch : Skip marketplace order fetch; only import + fulfill + tracking}
        {--skip-inventory : Skip Shopify → marketplace inventory push}
        {--tracking-limit=80 : Max orders per channel for tracking push}';

    protected $description = 'Push all marketplace orders to Shopify, auto-fulfill, and update tracking (no duplicates).';

    public function handle(): int
    {
        $only = strtolower(trim((string) $this->option('marketplace')));
        $days = max(1, min(30, (int) $this->option('days')));
        $skipFetch = (bool) $this->option('skip-fetch');
        $skipInventory = (bool) $this->option('skip-inventory');
        $trackingLimit = max(10, min(200, (int) $this->option('tracking-limit')));

        $failed = [];

        $this->info('Ensuring mm-* queue workers are up…');
        $this->runIsolated('queue:ensure-watchdog-daemon', [], $failed, 'watchdog');

        $slugs = $this->orderSlugs();
        if ($only !== '') {
            if (! isset($slugs[$only])) {
                $this->error('Unknown marketplace: '.$only);

                return self::FAILURE;
            }
            $slugs = [$only => $slugs[$only]];
        }

        if (! $skipFetch) {
            $this->info('1/4 Fetch marketplace orders ('.$days.' day lookback)…');
            foreach ($slugs as $slug => $command) {
                $this->runIsolated($command, [
                    '--days' => $days,
                    '--import' => true,
                    '--force' => true,
                ], $failed, $slug.':fetch');
            }
        } else {
            $this->line('Skipping marketplace fetch.');
        }

        $this->info('2/4 Queue Shopify imports for unpushed orders (duplicate-safe)…');
        $this->runIsolated('mm:dispatch-unpushed-shopify', [
            '--passes' => 3,
            ...($only !== '' ? ['--marketplace' => $only] : []),
        ], $failed, 'shopify-import');

        $this->info('3/4 Auto-fulfill Shopify copies from Veeqo/GOFO (full order id + SKU)…');
        $this->runIsolated('marketplace:fetch-shopify-tracking', [
            '--limit' => 500,
        ], $failed, 'shopify-fulfill');

        $this->info('4/4 Push tracking to each marketplace…');
        foreach (array_keys($slugs) as $slug) {
            $trackingCmd = $this->trackingCommand($slug);
            if ($trackingCmd === null) {
                continue;
            }
            $this->runIsolated($trackingCmd, [
                '--limit' => $trackingLimit,
                '--force' => true,
            ], $failed, $slug.':tracking');
        }

        if (! $skipInventory) {
            $this->info('Bonus: push Shopify inventory (zeros included)…');
            foreach (array_keys($slugs) as $slug) {
                $inv = $this->inventoryCommand($slug);
                if ($inv === null) {
                    continue;
                }
                $this->runIsolated($inv, [], $failed, $slug.':inventory');
            }
        }

        if ($failed !== []) {
            $this->warn('Finished with isolated failures: '.implode(', ', $failed));
            Log::warning('mm:push-orders-tracking finished with failures', ['failed' => $failed]);

            return self::SUCCESS;
        }

        $this->info('Done. Orders, fulfillment, and tracking pushed. Duplicates were not created.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  list<string>  $failed
     */
    protected function runIsolated(string $command, array $params, array &$failed, string $label): void
    {
        $this->line('  → '.$label);
        $attempt = $params;
        for ($i = 0; $i < 6; $i++) {
            try {
                $exit = Artisan::call($command, $attempt);
                $out = trim(Artisan::output());
                if ($out !== '') {
                    $this->line('    '.mb_substr($out, 0, 400));
                }
                if ($exit !== 0) {
                    $failed[] = $label;
                }

                return;
            } catch (\Throwable $e) {
                if (preg_match('/The --([A-Za-z0-9_\-]+) option does not exist/', $e->getMessage(), $m)) {
                    unset($attempt['--'.$m[1]]);
                    continue;
                }
                $this->warn('    '.$label.' failed: '.$e->getMessage());
                Log::warning('mm:push-orders-tracking step failed', [
                    'label' => $label,
                    'command' => $command,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = $label;

                return;
            }
        }
        $failed[] = $label;
    }

    /**
     * @return array<string, string>
     */
    protected function orderSlugs(): array
    {
        return [
            'amazon' => 'amazon:sync-orders',
            'aliexpress' => 'aliexpress:sync-orders',
            'alibaba' => 'alibaba:sync-orders',
            'reverb' => 'reverb:manager-sync-orders',
            'newegg' => 'newegg:sync-orders',
            'shein' => 'shein:sync-orders',
            'topdawg' => 'topdawg:sync-orders',
            'temu' => 'temu:sync-orders',
            'temu2' => 'temu2:sync-orders',
            'purchasingpower' => 'purchasingpower:sync-orders',
            'wayfair' => 'wayfair:sync-orders',
            'bestbuy' => 'bestbuy:sync-orders',
            'macy' => 'macy:sync-orders',
            'doba' => 'doba:sync-orders',
            'ebay1' => 'ebay1:sync-orders',
            'ebay2' => 'ebay2:sync-orders',
            'ebay3' => 'ebay3:sync-orders',
            'faire' => 'faire:sync-orders',
            'tiktok' => 'tiktok:sync-orders',
            'tiktok2' => 'tiktok2:sync-orders',
        ];
    }

    protected function trackingCommand(string $slug): ?string
    {
        return match ($slug) {
            'reverb' => 'reverb:sync-tracking',
            default => $slug.':sync-tracking',
        };
    }

    protected function inventoryCommand(string $slug): ?string
    {
        return match ($slug) {
            'amazon' => 'amazon:sync-inventory-from-shopify',
            'aliexpress' => 'aliexpress:sync-inventory-from-shopify',
            'alibaba' => 'alibaba:sync-inventory-from-shopify',
            'reverb' => 'reverb:manager-sync-inventory',
            'newegg' => 'newegg:sync-inventory-from-shopify',
            'shein' => 'shein:sync-inventory-from-shopify',
            'faire' => 'faire:sync-inventory-from-shopify',
            'ebay1' => 'ebay1:sync-inventory-from-shopify',
            'ebay2' => 'ebay2:sync-inventory-from-shopify',
            'ebay3' => 'ebay3:sync-inventory-from-shopify',
            'tiktok' => 'tiktok:sync-inventory-from-shopify',
            'tiktok2' => 'tiktok2:sync-inventory-from-shopify',
            default => null,
        };
    }
}
