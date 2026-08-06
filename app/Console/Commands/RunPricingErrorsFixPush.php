<?php

namespace App\Console\Commands;

use App\Jobs\RunPricingErrorsFixPushJob;
use App\Services\Support\PricingErrorsFixPushJobStore;
use App\Services\Support\PricingErrorsFixPushRunner;
use Illuminate\Console\Command;

class RunPricingErrorsFixPush extends Command
{
    protected $signature = 'pricing-errors:push-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Pricing Errors Fix price-push worker (queued by default)';

    public function handle(PricingErrorsFixPushRunner $runner, PricingErrorsFixPushJobStore $store): int
    {
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active PEF price push job. Start one from /pricing-errors-fix first.');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            try {
                RunPricingErrorsFixPushJob::dispatch();
                $this->info('Dispatched RunPricingErrorsFixPushJob to queue '.RunPricingErrorsFixPushJob::QUEUE);

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Queue dispatch failed ('.$e->getMessage().') — running --sync instead.');
            }
        }

        $this->info('Running PEF price push synchronously…');

        return $runner->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
