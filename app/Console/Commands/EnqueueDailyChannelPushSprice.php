<?php

namespace App\Console\Commands;

use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;

class EnqueueDailyChannelPushSprice extends Command
{
    protected $signature = 'channel:push-sprice-daily {channel=all : ebay1, ebay2, ebay3, or all}';

    protected $description = 'Once-daily: queue S PRC → live listing price for listed SKUs (no page needed)';

    public function handle(ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        $arg = strtolower(trim((string) $this->argument('channel'))) ?: 'all';
        $channels = $arg === 'all'
            ? ChannelPushSpriceDailyEnqueue::CHANNELS
            : [$arg];

        $this->info('Daily S PRC enqueue starting ('.implode(', ', $channels).')…');

        $any = false;
        foreach ($channels as $channel) {
            $res = $enqueue->enqueueChannel($channel);
            $line = strtoupper($channel).': '.$res['message']
                .($res['spawned'] ? ' — worker started' : '');
            if (($res['queued'] ?? 0) > 0) {
                $this->info($line);
                $any = true;
            } else {
                $this->line($line);
            }
        }

        $this->info($any
            ? 'Done. Workers keep running after this command exits.'
            : 'Done. Nothing to push.');

        return self::SUCCESS;
    }
}
