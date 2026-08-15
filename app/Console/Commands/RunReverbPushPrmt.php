<?php

namespace App\Console\Commands;

use App\Jobs\RunReverbPushPrmtJob;
use App\Services\Support\ReverbPushPrmtJobStore;
use App\Services\Support\ReverbPushPrmtRunner;
use Illuminate\Console\Command;

class RunReverbPushPrmt extends Command
{
    protected $signature = 'reverb:push-prmt-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Reverb Apply Prmt% background worker (Std − PRMT% → Reverb API)';

    public function handle(): int
    {
        $store = ReverbPushPrmtJobStore::for();
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Push Prmt% job. Start one from /reverb-pricing first.');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            try {
                RunReverbPushPrmtJob::dispatch();
                $this->info('Dispatched RunReverbPushPrmtJob');

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Queue dispatch failed ('.$e->getMessage().') — running --sync instead.');
            }
        }

        $this->info('Running Reverb Push Prmt% synchronously…');

        return ReverbPushPrmtRunner::for()->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
