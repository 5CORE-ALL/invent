<?php

namespace App\Console\Commands;

use App\Jobs\RunChannelPushPrmtJob;
use App\Services\Support\ChannelPushPrmtJobStore;
use App\Services\Support\ChannelPushPrmtRunner;
use Illuminate\Console\Command;

class RunChannelPushPrmt extends Command
{
    protected $signature = 'channel:push-prmt-run {channel=ebay2 : Channel key (ebay2, ebay2op, ebay3)} {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the channel Push PRMT % background worker (markdown sale-event queue)';

    public function handle(): int
    {
        $channel = strtolower(trim((string) $this->argument('channel'))) ?: 'ebay2';
        $store = ChannelPushPrmtJobStore::for($channel);
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Push PRMT% job for '.$channel.'. Start one from the analytics tabulator first.');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            try {
                RunChannelPushPrmtJob::dispatch($channel);
                $this->info('Dispatched RunChannelPushPrmtJob for '.$channel);

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Queue dispatch failed ('.$e->getMessage().') — running --sync instead.');
            }
        }

        $this->info('Running '.$channel.' Push PRMT% synchronously…');

        return ChannelPushPrmtRunner::for($channel)->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
