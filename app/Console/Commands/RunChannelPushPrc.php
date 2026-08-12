<?php

namespace App\Console\Commands;

use App\Jobs\RunChannelPushPrcJob;
use App\Services\Support\ChannelPushPrcJobStore;
use App\Services\Support\ChannelPushPrcRunner;
use Illuminate\Console\Command;

class RunChannelPushPrc extends Command
{
    protected $signature = 'channel:push-prc-run {channel=ebay1 : Channel key (ebay1, ebay2, ebay3, …)} {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the channel Push Prc background worker (eBay listing price queue)';

    public function handle(): int
    {
        $channel = strtolower(trim((string) $this->argument('channel'))) ?: 'ebay1';
        $store = ChannelPushPrcJobStore::for($channel);
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Push Prc job for '.$channel.'. Start one from the analytics tabulator first.');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            try {
                RunChannelPushPrcJob::dispatch($channel);
                $this->info('Dispatched RunChannelPushPrcJob for '.$channel);

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Queue dispatch failed ('.$e->getMessage().') — running --sync instead.');
            }
        }

        $this->info('Running '.$channel.' Push Prc synchronously…');

        return ChannelPushPrcRunner::for($channel)->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
