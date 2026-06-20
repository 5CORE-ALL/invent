<?php

namespace App\Console\Commands;

use App\Jobs\RunShopifyImagePullJob;
use App\Services\Support\ShopifyImagePullJobStore;
use App\Services\Support\ShopifyImagePullRunner;
use Illuminate\Console\Command;

class RunShopifyImagePull extends Command
{
    protected $signature = 'image-master:shopify-pull-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Shopify image pull worker (queued by default)';

    public function handle(ShopifyImagePullRunner $runner, ShopifyImagePullJobStore $store): int
    {
        if (! $this->option('sync')) {
            $state = $store->load();
            if (! $store->isActive($state)) {
                $this->warn('No active Shopify image pull job. Start one from Image Master or create job state first.');

                return self::FAILURE;
            }

            RunShopifyImagePullJob::dispatch();
            $this->info('Dispatched RunShopifyImagePullJob to the queue.');

            return self::SUCCESS;
        }

        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Shopify image pull job.');

            return self::FAILURE;
        }

        $this->info('Running Shopify image pull synchronously...');

        return $runner->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
