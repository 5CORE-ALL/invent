<?php

namespace App\Console\Commands;

use App\Jobs\RunAmazonPushPrcJob;
use App\Services\Support\AmazonPushPrcJobStore;
use App\Services\Support\AmazonPushPrcRunner;
use Illuminate\Console\Command;

class RunAmazonPushPrc extends Command
{
    protected $signature = 'amazon:push-prc-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Amazon Push Prc background worker';

    public function handle(AmazonPushPrcRunner $runner, AmazonPushPrcJobStore $store): int
    {
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Amazon Push Prc job. Start one from /amazon-tabulator-view first.');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            try {
                RunAmazonPushPrcJob::dispatch();
                $this->info('Dispatched RunAmazonPushPrcJob to queue '.RunAmazonPushPrcJob::QUEUE);

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Queue dispatch failed ('.$e->getMessage().') — running --sync instead.');
            }
        }

        $this->info('Running Amazon Push Prc synchronously…');

        return $runner->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
