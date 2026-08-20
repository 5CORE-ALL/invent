<?php

namespace App\Console\Commands;

use App\Jobs\RunChannelPushSpriceJob;
use App\Services\Support\ChannelPushSpriceJobStore;
use App\Services\Support\ChannelPushSpriceRunner;
use Illuminate\Console\Command;

class RunChannelPushSprice extends Command
{
    protected $signature = 'channel:push-sprice-run {channel=ebay1 : Channel key (ebay1, ebay2, ebay3)} {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the channel S PRC listing-price background worker';

    public function handle(): int
    {
        $channel = strtolower(trim((string) $this->argument('channel'))) ?: 'ebay1';
        $store = ChannelPushSpriceJobStore::for($channel);
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active S PRC push job for '.$channel.'.');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            try {
                RunChannelPushSpriceJob::dispatch($channel);
                $this->info('Dispatched RunChannelPushSpriceJob for '.$channel);

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Queue dispatch failed ('.$e->getMessage().') — running --sync instead.');
            }
        }

        $this->info('Running '.$channel.' S PRC push synchronously…');

        return ChannelPushSpriceRunner::for($channel)->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
