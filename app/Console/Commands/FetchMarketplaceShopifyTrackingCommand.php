<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Helper\ProgressBar;

class FetchMarketplaceShopifyTrackingCommand extends Command
{
    protected $signature = 'marketplace:fetch-shopify-tracking
                            {--limit=2000 : Max Shopify copies + linked marketplace orders to check}
                            {--fresh : Recheck Shopify copies even if recently cached}
                            {--all : Walk newest and oldest unfulfilled/partial copies, not only the latest page}
                            {--amazon= : Fulfill one Shopify copy by Amazon order id}
                            {--name= : Shopify order number, e.g. 331615}';

    protected $description = 'Fetch Veeqo / GOFO tracking onto unfulfilled Shopify copies for every marketplace.';

    public function handle(VeeqoShopifyFulfillmentService $sync): int
    {
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
        $bar->finish();
        $this->newLine(2);
        $this->info($result['message'] ?? 'Done.');
        $this->info('Successful fulfillments: '.(int) ($result['fulfilled'] ?? 0));

        return self::SUCCESS;
    }
}
