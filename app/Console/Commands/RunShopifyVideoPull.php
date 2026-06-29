<?php

namespace App\Console\Commands;

use App\Jobs\RunShopifyVideoPullJob;
use App\Services\Support\ShopifyVideoPullJobStore;
use App\Services\Support\ShopifyVideoPullRunner;
use Illuminate\Console\Command;

class RunShopifyVideoPull extends Command
{
    protected $signature = 'video-master:shopify-pull-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Shopify video pull worker (queued by default)';

    public function handle(ShopifyVideoPullRunner $runner, ShopifyVideoPullJobStore $store): int
    {
        if (! $this->option('sync')) {
            $state = $store->load();
            if (! $store->isActive($state)) {
                $this->warn('No active Shopify video pull job. Start one from Video Master or create job state first.');

                return self::FAILURE;
            }

            RunShopifyVideoPullJob::dispatch();
            $this->info('Dispatched RunShopifyVideoPullJob to the queue.');

            return self::SUCCESS;
        }

        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Shopify video pull job.');

            return self::FAILURE;
        }

        $this->info('Running Shopify video pull synchronously...');

        return $runner->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
