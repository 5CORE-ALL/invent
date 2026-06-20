<?php

namespace App\Console\Commands;

use App\Jobs\RunShopifyBulletPullJob;
use App\Services\Support\ShopifyBulletPullJobStore;
use App\Services\Support\ShopifyBulletPullRunner;
use Illuminate\Console\Command;

class RunShopifyBulletPull extends Command
{
    protected $signature = 'bullet-points:shopify-pull-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Shopify bullet pull worker (queued by default)';

    public function handle(ShopifyBulletPullRunner $runner, ShopifyBulletPullJobStore $store): int
    {
        if (! $this->option('sync')) {
            $state = $store->load();
            if (! $store->isActive($state)) {
                $this->warn('No active Shopify bullet pull job. Start one from Bullet Points Master or create job state first.');

                return self::FAILURE;
            }

            RunShopifyBulletPullJob::dispatch();
            $this->info('Dispatched RunShopifyBulletPullJob to the queue.');

            return self::SUCCESS;
        }

        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Shopify bullet pull job.');

            return self::FAILURE;
        }

        $this->info('Running Shopify bullet pull synchronously...');

        return $runner->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
