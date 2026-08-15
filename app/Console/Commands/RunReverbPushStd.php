<?php

namespace App\Console\Commands;

use App\Jobs\RunReverbPushStdJob;
use App\Services\Support\ReverbPushStdJobStore;
use App\Services\Support\ReverbPushStdRunner;
use Illuminate\Console\Command;

class RunReverbPushStd extends Command
{
    protected $signature = 'reverb:push-std-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Reverb Push Std background worker (Std Prc → Reverb API queue)';

    public function handle(): int
    {
        $store = ReverbPushStdJobStore::for();
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Push Std job. Start one from /reverb-pricing first.');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            try {
                RunReverbPushStdJob::dispatch();
                $this->info('Dispatched RunReverbPushStdJob');

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Queue dispatch failed ('.$e->getMessage().') — running --sync instead.');
            }
        }

        $this->info('Running Reverb Push Std synchronously…');

        return ReverbPushStdRunner::for()->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
