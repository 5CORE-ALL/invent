<?php

namespace App\Console\Commands;

use App\Jobs\RunChannelPushCpnJob;
use App\Services\Support\ChannelPushCpnJobStore;
use App\Services\Support\ChannelPushCpnRunner;
use Illuminate\Console\Command;

class RunChannelPushCpn extends Command
{
    protected $signature = 'channel:push-cpn-run {channel=ebay2 : Channel key (ebay2, ebay2op, ebay3)} {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the channel Push CPN % background worker (coded coupon queue)';

    public function handle(): int
    {
        $channel = strtolower(trim((string) $this->argument('channel'))) ?: 'ebay2';
        $store = ChannelPushCpnJobStore::for($channel);
        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Push CPN% job for '.$channel.'. Start one from the analytics tabulator first.');

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            try {
                RunChannelPushCpnJob::dispatch($channel);
                $this->info('Dispatched RunChannelPushCpnJob for '.$channel);

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn('Queue dispatch failed ('.$e->getMessage().') — running --sync instead.');
            }
        }

        $this->info('Running '.$channel.' Push CPN% synchronously…');

        return ChannelPushCpnRunner::for($channel)->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
