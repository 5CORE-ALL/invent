<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MarketplaceChannelFulfillmentHub;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;

class FetchMarketplaceShopifyTrackingCommand extends Command
{
    protected $signature = 'marketplace:fetch-shopify-tracking
                            {--limit=2000 : Max Shopify copies + linked marketplace orders to check}
                            {--fresh : Recheck Shopify copies even if recently cached}
                            {--all : Walk newest and oldest unfulfilled/partial copies, not only the latest page}
                            {--amazon= : Fulfill one Shopify copy by Amazon order id}
                            {--name= : Shopify order number, e.g. 331615}
                            {--ids= : Comma-separated marketplace order ids to fulfill now}
                            {--marketplace=bestbuy : Channel slug for --ids}
                            {--budget=0 : Stop picking new orders after this many seconds (0 = no limit)}
                            {--no-progress : Plain summary output (cron logs)}
                            {--push-channels : Afterwards queue tracking pushes to every marketplace}';

    protected $description = 'Fetch Veeqo / GOFO tracking onto unfulfilled Shopify copies for every marketplace.';

    public function handle(VeeqoShopifyFulfillmentService $sync): int
    {
        $idsRaw = trim((string) $this->option('ids'));
        if ($idsRaw !== '') {
            $ids = array_values(array_filter(array_map('trim', explode(',', $idsRaw))));
            $marketplace = strtolower(trim((string) $this->option('marketplace'))) ?: 'bestbuy';
            $this->info('Fulfilling Shopify copies for '.$marketplace.' order ids: '.implode(', ', $ids));
            Cache::forget('mm.label_ssl_broken');
            $rows = $sync->asManualAction()->fulfillShopifyCopiesByOrderRefs($ids, $marketplace);
            $failed = 0;
            foreach ($rows as $row) {
                $ok = ! empty($row['success']);
                if (! $ok) {
                    $failed++;
                }
                $this->line(sprintf(
                    '%s %s sku=%s action=%s tracking=%s — %s',
                    $ok ? 'OK' : 'FAIL',
                    (string) ($row['ref'] ?? ''),
                    (string) ($row['sku'] ?? ''),
                    (string) ($row['action'] ?? ''),
                    (string) ($row['tracking'] ?? ''),
                    (string) ($row['message'] ?? '')
                ));
            }

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        }

        $amazon = trim((string) $this->option('amazon'));
        if ($amazon !== '') {
            $name = trim((string) $this->option('name')) ?: null;
            $this->info('Looking up Veeqo/GOFO tracking for Amazon '.$amazon.' and fulfilling Shopify.');
            $result = $sync->fulfillShopifyAmazonOrder($amazon, $name);
            $this->info($result['message'] ?? 'Done.');
            if (! empty($result['tracking'])) {
                $this->info('Tracking: '.$result['tracking'].' ('.($result['carrier'] ?? '').')');
            }

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $fresh = (bool) $this->option('fresh');
        $all = (bool) $this->option('all');
        $budget = max(0, (int) $this->option('budget'));

        // Scheduled sweeps (15-min, half-hourly, daily) must not hit the same
        // Shopify store in parallel and race on the same orders.
        $lock = Cache::lock('mm:shopify-fulfill-sweep', $budget > 0 ? $budget + 600 : 7200);
        if (! $lock->get()) {
            $this->info('Another Shopify fulfill sweep is still running — skipping this run.');

            return self::SUCCESS;
        }

        try {
            return $this->runSweep($sync, $limit, $fresh, $all, $budget);
        } finally {
            $lock->release();
        }
    }

    protected function runSweep(VeeqoShopifyFulfillmentService $sync, int $limit, bool $fresh, bool $all, int $budget): int
    {
        $sync->withTimeBudget($budget > 0 ? $budget : null);
        $started = microtime(true);

        if ($this->option('no-progress')) {
            $result = $sync->syncPendingUnfulfilled($limit, $fresh, $all);
            $sync->withTimeBudget(null);
            $this->pushChannelsIfRequested();
            $this->info(($result['message'] ?? 'Done.').' ('.(int) round(microtime(true) - $started).'s)');
            $reasons = is_array($result['skip_reasons'] ?? null) ? $result['skip_reasons'] : [];
            if ($reasons !== []) {
                arsort($reasons);
                $this->line('Skip reasons: '.json_encode($reasons));
            }
            Log::info('marketplace:fetch-shopify-tracking completed', $result + ['seconds' => (int) round(microtime(true) - $started)]);

            return self::SUCCESS;
        }

        $this->info('Checking every marketplace: Veeqo and GOFO (4Seller) labels → Shopify fulfill.');
        $this->info('Tracking is attached only after the full marketplace order id and SKU match the Shopify copy.');
        if ($fresh) {
            $this->info('Fresh pass: cached Shopify copies will be rechecked.');
        }
        if ($all) {
            $this->info('All-ages pass: newest and oldest Unfulfilled/Partial copies.');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            Cache::put('mm.label_ssl_broken', 1, now()->addHours(2));
            Cache::put('mm.temu.ip_blocked', 1, now()->addHours(6));
            $this->warn('This Windows machine cannot reach Veeqo/GOFO (SSL) or Temu (IP whitelist). Tracking is taken from the local marketplace row after the full marketplace order id matches.');
        }

        ProgressBar::setFormatDefinition(
            'mm-tracking',
            ' %current%/%max% [%bar%] %percent:3s%% | %message%'
        );
        $bar = $this->output->createProgressBar($limit);
        $bar->setFormat('mm-tracking');
        $bar->setBarCharacter('=');
        $bar->setEmptyBarCharacter(' ');
        $bar->setProgressCharacter('>');
        $bar->setMessage('fulfilled 0  skipped 0  failed 0');
        $bar->start();

        $sync->setProgressReporter(function (array $event) use ($bar): void {
            $type = (string) ($event['type'] ?? '');
            if ($type === 'start') {
                $max = max(1, (int) ($event['max'] ?? 1));
                $bar->setMaxSteps($max);
                $bar->setProgress(0);
                $bar->setMessage('fulfilled 0  skipped 0  failed 0');
                $bar->display();

                return;
            }
            if ($type === 'tick') {
                if (! empty($event['success'])) {
                    $label = trim((string) ($event['label'] ?? 'order'));
                    $tracking = trim((string) ($event['tracking'] ?? ''));
                    $carrier = trim((string) ($event['carrier'] ?? ''));
                    $line = '  Fulfilled '.$label;
                    if ($tracking !== '') {
                        $line .= '  tracking '.$tracking;
                    }
                    if ($carrier !== '') {
                        $line .= ' ('.$carrier.')';
                    }
                    $bar->clear();
                    $this->getOutput()->writeln('<info>'.$line.'</info>');
                    $bar->display();
                }
                $bar->setMessage(sprintf(
                    'fulfilled %d  skipped %d  failed %d',
                    (int) ($event['fulfilled'] ?? 0),
                    (int) ($event['skipped'] ?? 0),
                    (int) ($event['failed'] ?? 0)
                ));
                $checked = (int) ($event['checked'] ?? 0);
                if ($checked > $bar->getMaxSteps()) {
                    $bar->setMaxSteps($checked);
                }
                $bar->setProgress(min($checked, $bar->getMaxSteps()));

                return;
            }
            if ($type === 'finish') {
                $checked = max(1, (int) ($event['checked'] ?? $bar->getProgress()));
                if ($checked !== $bar->getMaxSteps()) {
                    $bar->setMaxSteps($checked);
                }
                $bar->setProgress($checked);
                $bar->setMessage(sprintf(
                    'fulfilled %d  skipped %d  failed %d',
                    (int) ($event['fulfilled'] ?? 0),
                    (int) ($event['skipped'] ?? 0),
                    (int) ($event['failed'] ?? 0)
                ));
            }
        });

        $result = $sync->syncPendingUnfulfilled($limit, $fresh, $all);
        $sync->setProgressReporter(null);
        $sync->withTimeBudget(null);
        $this->pushChannelsIfRequested();
        $bar->finish();
        $this->newLine(2);
        $this->info($result['message'] ?? 'Done.');
        $this->info('Successful fulfillments: '.(int) ($result['fulfilled'] ?? 0));
        $reasons = is_array($result['skip_reasons'] ?? null) ? $result['skip_reasons'] : [];
        if ($reasons !== []) {
            arsort($reasons);
            $this->newLine();
            $this->warn('Why orders were skipped (tracking is never invented):');
            $labels = [
                'tracking_not_found' => 'No Veeqo / GOFO / marketplace tracking yet',
                'already_on_shopify' => 'Shopify already has tracking',
                'recently_checked' => 'Checked recently (use --fresh to recheck)',
                'sku_mismatch' => 'SKU on Shopify does not match',
                'sku_required' => 'SKU missing on the order',
                'order_id_mismatch' => 'Full marketplace order id not on Shopify',
                'order_id_required' => 'Marketplace order id missing',
                'not_linked' => 'Not linked to a Shopify order',
                'shopify_order_missing' => 'Could not load the Shopify order',
            ];
            $rows = [];
            foreach ($reasons as $reason => $count) {
                $rows[] = [$reason, $labels[$reason] ?? $reason, $count];
            }
            $this->table(['Reason', 'Meaning', 'Count'], $rows);
        }

        return self::SUCCESS;
    }

    protected function pushChannelsIfRequested(): void
    {
        if (! $this->option('push-channels')) {
            return;
        }
        try {
            MarketplaceChannelFulfillmentHub::dispatchAllTrackingJobs(40);
        } catch (\Throwable $e) {
            Log::warning('marketplace:fetch-shopify-tracking channel push dispatch failed', ['error' => $e->getMessage()]);
        }
    }
}
