<?php

namespace App\Console\Commands;

use App\Jobs\RunReverbPushBumpJob;
use App\Services\Support\ReverbPushBumpJobStore;
use App\Services\Support\ReverbPushBumpRunner;
use Illuminate\Console\Command;

class RunReverbPushBump extends Command
{
    protected $signature = 'reverb:push-bump-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Reverb Push B% background worker (S Bump% → Reverb Bump bid API)';

    public function handle(): int
    {
        $store = ReverbPushBumpJobStore::for();
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Push B% job. Start one from /reverb-pricing first.');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            try {
                RunReverbPushBumpJob::dispatch();
                $this->info('Dispatched RunReverbPushBumpJob');

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Queue dispatch failed ('.$e->getMessage().') — running --sync instead.');
            }
        }

        $this->info('Running Reverb Push B% synchronously…');

        return ReverbPushBumpRunner::for()->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
